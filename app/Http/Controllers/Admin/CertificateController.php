<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\Registration;
use App\Services\CertificateIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $eventIds = $user->managedEventIds();
        $scope = fn ($query) => $query->when(
            $user->managesAssignedEventsOnly(),
            fn ($query) => $query->whereIn('event_id', $eventIds)
        );

        $certificates = Certificate::query()
            ->with(['user', 'event', 'category', 'registration.raceResult'])
            ->when($user->managesAssignedEventsOnly(), fn ($query) => $query->whereIn('event_id', $eventIds))
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->integer('event_id')))
            ->when($request->input('status') === 'valid', fn ($query) => $query->whereNull('revoked_at'))
            ->when($request->input('status') === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('certificate_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('event', fn ($query) => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), fn ($query) => $query->whereIn('id', $eventIds))
            ->orderByDesc('event_date')
            ->get(['id', 'title']);

        $summary = [
            'total' => $scope(Certificate::query())->count(),
            'valid' => $scope(Certificate::query())->whereNull('revoked_at')->count(),
            'revoked' => $scope(Certificate::query())->whereNotNull('revoked_at')->count(),
            'awaiting_certificate' => $scope(Registration::query())
                ->where('status', 'completed')
                ->has('raceResult')
                ->doesntHave('certificate')
                ->count(),
        ];

        return view('admin.certificates.index', compact('certificates', 'events', 'summary'));
    }

    public function sync(Request $request, Registration $registration, CertificateIssuer $issuer): RedirectResponse
    {
        $registration->loadMissing(['event', 'raceResult', 'certificate']);
        $this->authorizeEvent($request, $registration->event);

        if (! $issuer->isEligible($registration)) {
            return back()->with('error', 'A certificate requires a completed registration with an official result.');
        }

        $certificate = $issuer->syncForRegistration($registration, $request->user()->id);

        return back()->with('success', "E-Certificate {$certificate->certificate_number} is ready.");
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $certificate->loadMissing('event');
        $this->authorizeEvent($request, $certificate->event);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if ($certificate->revoked_at) {
            return back()->with('error', 'This E-Certificate is already revoked.');
        }

        $certificate->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
            'revocation_reason' => $validated['reason'],
        ]);

        return back()->with('success', 'E-Certificate revoked. Its verification history was preserved.');
    }

    public function reinstate(Request $request, Certificate $certificate, CertificateIssuer $issuer): RedirectResponse
    {
        $certificate->loadMissing(['event', 'registration.raceResult']);
        $this->authorizeEvent($request, $certificate->event);

        if (! $issuer->isEligible($certificate->registration)) {
            return back()->with('error', 'This certificate cannot be reinstated until all eligibility requirements are satisfied.');
        }

        $certificate->update([
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
        ]);

        return back()->with('success', 'E-Certificate reinstated successfully.');
    }

    private function authorizeEvent(Request $request, ?Event $event): void
    {
        abort_unless($event && $request->user()->canManageEvent($event), 403);
    }
}
