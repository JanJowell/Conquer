<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $term = trim((string) $request->query('q', ''));
        $managedEventIds = $user->managedEventIds();
        $scopeManagedEvents = $user->managesAssignedEventsOnly();

        $users = collect();
        $events = collect();
        $registrations = collect();
        $announcements = collect();
        $communityPosts = collect();

        if ($term !== '') {
            if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EXECUTIVE])) {
                $users = User::query()
                    ->where(function ($query) use ($term) {
                        $query->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    })
                    ->orderBy('name')
                    ->take(6)
                    ->get();
            }

            if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EXECUTIVE, User::ROLE_EVENT_MANAGER])) {
                $events = Event::query()
                    ->when($scopeManagedEvents, function ($query) use ($managedEventIds) {
                        $query->whereIn('id', $managedEventIds);
                    })
                    ->where(function ($query) use ($term) {
                        $query->where('title', 'like', "%{$term}%")
                            ->orWhere('venue', 'like', "%{$term}%")
                            ->orWhere('organized_by', 'like', "%{$term}%");
                    })
                    ->orderBy('event_date')
                    ->take(6)
                    ->get();
            }

            if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
                $registrations = Registration::query()
                    ->with(['user', 'event'])
                    ->when($scopeManagedEvents, function ($query) use ($managedEventIds) {
                        $query->whereIn('event_id', $managedEventIds);
                    })
                    ->where(function ($query) use ($term) {
                        $query->where('bib_number', 'like', "%{$term}%")
                            ->orWhere('status', 'like', "%{$term}%")
                            ->orWhereHas('user', function ($userQuery) use ($term) {
                                $userQuery->where('name', 'like', "%{$term}%")
                                    ->orWhere('email', 'like', "%{$term}%");
                            })
                            ->orWhereHas('event', function ($eventQuery) use ($term) {
                                $eventQuery->where('title', 'like', "%{$term}%");
                            });
                    })
                    ->latest()
                    ->take(6)
                    ->get();
            }

            if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_CONTENT_MODERATOR, User::ROLE_EVENT_MANAGER])) {
                $announcements = Announcement::query()
                    ->with('event')
                    ->when($scopeManagedEvents, function ($query) use ($managedEventIds) {
                        $query->whereIn('event_id', $managedEventIds);
                    })
                    ->where(function ($query) use ($term) {
                        $query->where('title', 'like', "%{$term}%")
                            ->orWhere('content', 'like', "%{$term}%");
                    })
                    ->latest()
                    ->take(6)
                    ->get();
            }

            if ($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_CONTENT_MODERATOR])) {
                $communityPosts = CommunityPost::query()
                    ->with(['user', 'event'])
                    ->where(function ($query) use ($term) {
                        $query->where('content', 'like', "%{$term}%")
                            ->orWhereHas('user', function ($userQuery) use ($term) {
                                $userQuery->where('name', 'like', "%{$term}%");
                            })
                            ->orWhereHas('event', function ($eventQuery) use ($term) {
                                $eventQuery->where('title', 'like', "%{$term}%");
                            });
                    })
                    ->latest()
                    ->take(6)
                    ->get();
            }
        }

        return view('admin.search.index', compact(
            'term',
            'users',
            'events',
            'registrations',
            'announcements',
            'communityPosts'
        ));
    }
}
