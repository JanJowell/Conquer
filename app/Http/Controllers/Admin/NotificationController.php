<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = PushNotification::latest()->paginate(10);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function create()
    {
        return view('admin.notifications.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:payment,reminder,announcement,emergency',
            'target_audience' => 'required|in:all,participants,admins',
            'scheduled_at' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ]);

        $notification = PushNotification::create($validated);

        // If not scheduled, send immediately
        if (!$validated['scheduled_at']) {
            $this->sendNotification($notification);
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
        return view('admin.notifications.edit', compact('notification'));
    }

    public function update(Request $request, PushNotification $notification)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:payment,reminder,announcement,emergency',
            'target_audience' => 'required|in:all,participants,admins',
            'scheduled_at' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ]);

        $notification->update($validated);

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification updated successfully.');
    }

    public function destroy(PushNotification $notification)
    {
        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification deleted successfully.');
    }

    public function sendNow(PushNotification $notification)
    {
        $this->sendNotification($notification);
        $notification->update(['sent_at' => now()]);

        return redirect()->back()
            ->with('success', 'Notification sent successfully.');
    }

    private function sendNotification($notification)
    {
        // Implementation would depend on your SMS service provider
        // This is a placeholder for the actual SMS sending logic
        
        $targetUsers = $this->getTargetUsers($notification->target_audience);
        
        foreach ($targetUsers as $user) {
            // Send SMS logic here
            // Example: SMS service integration
        }
    }

    private function getTargetUsers($targetAudience)
    {
        switch ($targetAudience) {
            case 'all':
                return \App\Models\User::all();
            case 'participants':
                return \App\Models\User::whereHas('registrations')->get();
            case 'admins':
                return \App\Models\User::whereIn('role', \App\Models\User::storedAdminRoles())->get();
            default:
                return collect();
        }
    }
}
