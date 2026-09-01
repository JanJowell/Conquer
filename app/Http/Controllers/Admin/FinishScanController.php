<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\Registration;
use App\Services\FinishScanToken;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinishScanController extends Controller
{
    public function __construct(private readonly FinishScanToken $tokens) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $eventIds = $user->managesAssignedEventsOnly()
            ? $user->managedEventIds()
            : Event::query()->pluck('id')->all();

        $events = Event::query()
            ->whereIn('id', $eventIds)
            ->orderByDesc('event_date')
            ->get(['id', 'title']);
        $categories = Category::query()
            ->with('event:id,title')
            ->whereIn('event_id', $eventIds)
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->integer('event_id')))
            ->orderBy('event_id')
            ->orderBy('name')
            ->get(['id', 'event_id', 'name']);

        $baseQuery = FinishScan::query()->whereIn('event_id', $eventIds);
        $scans = (clone $baseQuery)
            ->with(['registration.user', 'event', 'category', 'scannedBy'])
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->integer('event_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();

                $query->where(function ($inner) use ($search) {
                    $inner->where('bib_number', 'like', "%{$search}%")
                        ->orWhereHas('registration.user', fn ($users) => $users->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('scanned_at')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'provisional' => (clone $baseQuery)->where('status', FinishScan::STATUS_PROVISIONAL)->count(),
            'published' => (clone $baseQuery)->where('status', FinishScan::STATUS_PUBLISHED)->count(),
        ];

        return view('admin.finish-scans.index', compact('scans', 'events', 'categories', 'summary'));
    }

    public function qr(Request $request, Registration $registration): Response
    {
        $registration->loadMissing(['user', 'event', 'category']);
        abort_unless($registration->event && $request->user()->canManageEvent($registration->event), 403);
        abort_unless($registration->status === 'checked_in', 409, 'Only checked-in registrations can receive a finish QR code.');
        abort_unless($registration->category && filled($registration->bib_number), 409, 'Assign a category and BIB number before generating a finish QR code.');

        $token = $this->tokens->issue($registration);
        $qrCode = new QrCode(
            data: $token,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $qrDataUri = (new PngWriter)->write($qrCode)->getDataUri();

        return response()
            ->view('admin.finish-scans.qr', compact('registration', 'qrDataUri'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
