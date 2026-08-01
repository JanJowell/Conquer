<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private readonly FirebaseCloudMessaging $messaging)
    {
    }

    public function index()
    {
        $notifications = PushNotification::latest()->paginate(10);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function create()
    {
        return view('admin.notifications.create', [
            'typeOptions' => $this->typeOptionsFor(auth()->user()),
            'audienceOptions' => $this->audienceOptionsFor(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => ['required', Rule::in(array_keys($this->typeOptionsFor($request->user())))],
            'target_audience' => ['required', Rule::in(array_keys($this->audienceOptionsFor($request->user())))],
            'scheduled_at' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $notification = PushNotification::create($validated);

        // If not scheduled, send immediately when the notification is active.
        if ($validated['is_active'] && !($validated['scheduled_at'] ?? null)) {
            $result = $this->deliver($notification);

            if (! $result['sent']) {
                return redirect()->route('admin.notifications.index')
                    ->with('error', $result['message']);
            }
        }

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification created successfully.');
    }

    public function show(PushNotification $notification)
    {
        return view('admin.notifications.show', compact('notification'));
    }

    public function edit(PushNotification $notification)
    {
        abort_unless($this->canManage($notification, auth()->user()), 403);

        return view('admin.notifications.edit', [
            'notification' => $notification,
            'typeOptions' => $this->typeOptionsFor(auth()->user(), $notification),
            'audienceOptions' => $this->audienceOptionsFor(auth()->user()),
        ]);
    }

    public function update(Request $request, PushNotification $notification)
    {
        abort_unless($this->canManage($notification, $request->user()), 403);

        $scheduleRules = ['nullable', 'date'];

        if (! $notification->sent_at) {
            $scheduleRules[] = 'after:now';
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => ['required', Rule::in(array_keys($this->typeOptionsFor($request->user(), $notification)))],
            'target_audience' => ['required', Rule::in(array_keys($this->audienceOptionsFor($request->user())))],
            'scheduled_at' => $scheduleRules,
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        if ($notification->type === 'community') {
            unset($validated['type'], $validated['target_audience']);
        }

        $notification->update($validated);

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification updated successfully.');
    }

    public function destroy(PushNotification $notification)
    {
        abort_unless($this->canManage($notification, auth()->user()), 403);

        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification deleted successfully.');
    }

    public function sendNow(PushNotification $notification)
    {
        abort_unless($this->canManage($notification, auth()->user()), 403);

        if (! $notification->is_active) {
            return redirect()->back()
                ->with('error', 'Inactive notifications must be activated before they can be sent.');
        }

        if ($notification->sent_at) {
            return redirect()->back()
                ->with('error', 'This notification has already been sent.');
        }

        $result = $this->deliver($notification);

        return redirect()->back()
            ->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    public function deliver(PushNotification $notification): array
    {
        $targetUsers = $this->getTargetUsers($notification->target_audience);

        $result = $this->messaging->sendNotification($notification, $targetUsers);

        if ($result['sent'] > 0 || ($result['processed'] ?? false)) {
            $notification->update(['sent_at' => now()]);
        } elseif (! ($result['retry'] ?? false)) {
            $notification->update(['is_active' => false]);
        }

        return $result;
    }

    private function getTargetUsers($targetAudience)
    {
        switch ($targetAudience) {
            case 'all':
                return User::all();
            case 'runners':
                return User::whereIn('role', [User::ROLE_RUNNER, 'user'])->get();
            case 'participants':
                return User::whereHas('registrations')->get();
            case 'admins':
                return User::whereIn('role', User::storedAdminRoles())->get();
            default:
                return collect();
        }
    }

    private function typeOptionsFor(User $user, ?PushNotification $notification = null): array
    {
        $options = [
            'payment' => 'Payment',
            'reminder' => 'Reminder',
        ];

        if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
            $options['emergency'] = 'Emergency';
        }

        if ($notification?->type === 'announcement') {
            $options['announcement'] = 'Announcement';
        }

        if ($notification?->type === 'community') {
            $options['community'] = 'Community';
        }

        return $options;
    }

    private function audienceOptionsFor(User $user): array
    {
        $options = [
            'all' => 'All Users',
            'runners' => 'All Runners',
            'participants' => 'Event Participants',
        ];

        if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
            $options['admins'] = 'Admin Users';
        }

        return $options;
    }

    private function canManage(PushNotification $notification, User $user): bool
    {
        if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
            return true;
        }

        return $notification->type !== 'emergency'
            && $notification->target_audience !== 'admins';
    }
}
