<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\IssuedEBadge;
use App\Models\Registration;
use App\Services\EBadgeAutoIssuer;
use App\Services\EBadgeNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EBadgeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $events = $this->eventOptions($user);
        $categories = $this->categoryOptions($user);
        $autoIssueRules = EBadgeAutoIssuer::rules();

        $badges = EBadge::query()
            ->with(['event', 'category'])
            ->withCount('issuedBadges')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->latest()
            ->paginate(10, ['*'], 'badges_page')
            ->withQueryString();

        $issuedBadges = IssuedEBadge::query()
            ->with(['badge', 'user', 'event', 'registration.category', 'issuer'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->latest('issued_at')
            ->paginate(10, ['*'], 'issued_page')
            ->withQueryString();

        $summaryBase = IssuedEBadge::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            });

        $summary = [
            'templates' => EBadge::query()
                ->when($user->managesAssignedEventsOnly(), fn ($query) => $query->whereIn('event_id', $user->managedEventIds()))
                ->count(),
            'active_templates' => EBadge::query()
                ->when($user->managesAssignedEventsOnly(), fn ($query) => $query->whereIn('event_id', $user->managedEventIds()))
                ->where('is_active', true)
                ->count(),
            'issued' => (clone $summaryBase)->count(),
        ];

        return view('admin.e-badges.index', compact('badges', 'issuedBadges', 'events', 'categories', 'summary', 'autoIssueRules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBadge($request);

        if ($request->hasFile('image_upload')) {
            $validated['image_path'] = $request->file('image_upload')->store('e-badges', 'public');
        }

        unset($validated['image_upload']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $this->preventDuplicateBadge($validated);

        $badge = EBadge::create($validated);
        $sync = app(EBadgeAutoIssuer::class)->syncForBadge($badge);

        return back()->with('success', $sync['issued'] > 0
            ? "E-badge template created successfully. {$sync['issued']} matching participants received it automatically."
            : 'E-badge template created successfully.');
    }

    public function update(Request $request, EBadge $badge): RedirectResponse
    {
        abort_unless($this->canAccessBadge($badge), 403);

        $validated = $this->validateBadge($request);

        if ($request->hasFile('image_upload')) {
            $validated['image_path'] = $request->file('image_upload')->store('e-badges', 'public');
        }

        unset($validated['image_upload']);
        $validated['is_active'] = $request->boolean('is_active');
        $this->preventDuplicateBadge($validated, $badge);

        $badge->update($validated);
        $sync = app(EBadgeAutoIssuer::class)->syncForBadge($badge->refresh());

        return back()->with('success', $sync['issued'] > 0 || $sync['revoked'] > 0
            ? "E-badge template updated successfully. {$sync['issued']} matching participants received it automatically and {$sync['revoked']} stale automatic badge(s) were revoked."
            : 'E-badge template updated successfully.');
    }

    public function destroy(EBadge $badge): RedirectResponse
    {
        abort_unless($this->canAccessBadge($badge), 403);

        if ($badge->issuedBadges()->exists()) {
            return back()->with('error', 'E-badges that have already been issued cannot be deleted.');
        }

        $badge->delete();

        return back()->with('success', 'E-badge template deleted successfully.');
    }

    public function issue(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($this->canAccessRegistration($registration), 403);

        $validated = $request->validate([
            'e_badge_id' => ['required', Rule::exists('e_badges', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $registration->load(['event', 'user']);
        $badge = EBadge::findOrFail($validated['e_badge_id']);

        if (! $this->canAccessBadge($badge)) {
            abort(403);
        }

        if ($badge->event_id !== null && (int) $badge->event_id !== (int) $registration->event_id) {
            return back()->with('error', 'This e-badge is assigned to a different event.');
        }

        if ($badge->category_id !== null && (int) $badge->category_id !== (int) $registration->category_id) {
            return back()->with('error', 'This e-badge is assigned to a different category.');
        }

        if ($registration->status !== 'completed') {
            return back()->with('error', 'Only completed participants can receive an e-badge.');
        }

        $issuedBadge = IssuedEBadge::updateOrCreate(
            [
                'e_badge_id' => $badge->id,
                'registration_id' => $registration->id,
            ],
            [
                'user_id' => $registration->user_id,
                'event_id' => $registration->event_id,
                'issued_by' => $request->user()->id,
                'issued_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($issuedBadge->wasRecentlyCreated) {
            app(EBadgeNotificationService::class)->notifyIssued($issuedBadge);
        }

        return back()->with('success', 'E-badge issued successfully.');
    }

    public function revoke(IssuedEBadge $issuedBadge): RedirectResponse
    {
        abort_unless($this->canAccessIssuedBadge($issuedBadge), 403);

        $issuedBadge->delete();

        return back()->with('success', 'Issued e-badge revoked successfully.');
    }

    private function validateBadge(Request $request): array
    {
        $user = $request->user();
        $accessibleEventIds = $this->accessibleEventIds($user);

        $validated = $request->validate([
            'event_id' => [
                Rule::requiredIf($user->managesAssignedEventsOnly()),
                'nullable',
                Rule::in($accessibleEventIds),
            ],
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'criteria' => ['nullable', 'string', 'max:255'],
            'auto_issue_rule' => ['required', Rule::in(array_keys(EBadgeAutoIssuer::rules()))],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_upload' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (blank($validated['event_id'] ?? null)) {
            $validated['category_id'] = null;
        } elseif (! blank($validated['category_id'] ?? null)) {
            $categoryBelongsToEvent = Category::query()
                ->whereKey($validated['category_id'])
                ->where('event_id', $validated['event_id'])
                ->exists();

            if (! $categoryBelongsToEvent) {
                throw ValidationException::withMessages([
                    'category_id' => 'The selected category does not belong to the selected event.',
                ]);
            }
        }

        if (($validated['auto_issue_rule'] ?? EBadgeAutoIssuer::MANUAL) !== EBadgeAutoIssuer::MANUAL) {
            $validated['criteria'] = null;
        }

        return $validated;
    }

    private function preventDuplicateBadge(array $data, ?EBadge $currentBadge = null): void
    {
        $duplicateExists = EBadge::query()
            ->when($currentBadge, fn ($query) => $query->whereKeyNot($currentBadge->id))
            ->where('title', trim($data['title']))
            ->where('auto_issue_rule', $data['auto_issue_rule'] ?? EBadgeAutoIssuer::MANUAL)
            ->where('is_active', (bool) ($data['is_active'] ?? false))
            ->where(function ($query) use ($data) {
                if (blank($data['event_id'] ?? null)) {
                    $query->whereNull('event_id');
                } else {
                    $query->where('event_id', $data['event_id']);
                }
            })
            ->where(function ($query) use ($data) {
                if (blank($data['category_id'] ?? null)) {
                    $query->whereNull('category_id');
                } else {
                    $query->where('category_id', $data['category_id']);
                }
            })
            ->exists();

        if (! $duplicateExists) {
            return;
        }

        throw ValidationException::withMessages([
            'title' => 'A matching e-badge template already exists for this event, category, status, and auto issue rule.',
        ]);
    }

    private function accessibleEventIds($user): array
    {
        if (! $user->managesAssignedEventsOnly()) {
            return Event::pluck('id')->all();
        }

        return $user->managedEventIds();
    }

    private function eventOptions($user)
    {
        return Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title', 'event_date']);
    }

    private function categoryOptions($user)
    {
        return Category::query()
            ->with('event:id,title')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            })
            ->orderBy('event_id')
            ->orderBy('name')
            ->get(['id', 'event_id', 'name']);
    }

    private function canAccessBadge(EBadge $badge): bool
    {
        if (! $badge->event) {
            return ! auth()->user()->managesAssignedEventsOnly();
        }

        return auth()->user()->canManageEvent($badge->event);
    }

    private function canAccessIssuedBadge(IssuedEBadge $issuedBadge): bool
    {
        $issuedBadge->loadMissing('event');

        return $issuedBadge->event && auth()->user()->canManageEvent($issuedBadge->event);
    }

    private function canAccessRegistration(Registration $registration): bool
    {
        return $registration->event && auth()->user()->canManageEvent($registration->event);
    }
}
