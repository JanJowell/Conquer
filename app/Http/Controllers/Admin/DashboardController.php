<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Models\User;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RaceResult;
use App\Models\Announcement;
use App\Models\AdminActivityLog;
use App\Models\BannedIP;
use App\Models\Checkpoint;
use App\Models\CommunityPost;
use App\Models\TrainingModule;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $user = auth()->user();
        $dashboardRole = $user->normalizedRole();
        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->subDays(30)->startOfDay();
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();
        $overviewDays = max($startDate->diffInDays($endDate) + 1, 1);
        $growthStart = $startDate->copy();
        $managedEventIds = $user->managedEventIds();

        $eventQuery = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('id', $managedEventIds);
            })
            ->whereBetween('event_date', [$startDate->toDateString(), $endDate->toDateString()]);

        $registrationQuery = Registration::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $resultQuery = RaceResult::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $announcementQuery = Announcement::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $checkpointQuery = Checkpoint::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $stats = [
            'users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'events' => (clone $eventQuery)->count(),
            'registrations' => (clone $registrationQuery)->count(),
            'results' => (clone $resultQuery)->count(),
            'announcements' => (clone $announcementQuery)->count(),
            'training_modules' => TrainingModule::whereBetween('created_at', [$startDate, $endDate])->count(),
            'checkpoints' => (clone $checkpointQuery)->count(),
            'security_alerts' => BannedIP::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_users_in_range' => User::whereBetween('last_login_at', [$startDate, $endDate])->count(),
            'upcoming_events' => (clone $eventQuery)->whereDate('event_date', '>=', now()->toDateString())->count(),
            'pending_registrations' => (clone $registrationQuery)->where('status', 'pending')->count(),
        ];

        $eventStatusCounts = [
            'draft' => (clone $eventQuery)->where('status', 'draft')->count(),
            'published' => (clone $eventQuery)->where('status', 'published')->count(),
            'ongoing' => (clone $eventQuery)->where('status', 'ongoing')->count(),
            'completed' => (clone $eventQuery)->where('status', 'completed')->count(),
            'upcoming' => (clone $eventQuery)->where('status', 'upcoming')->count(),
        ];

        $recentActivities = AdminActivityLog::with('user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->take(5)
            ->get();

        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $overviewSeries = $this->buildSeries($growthStart, $overviewDays, $userGrowth);

        $registrationGrowth = Registration::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $registrationSeries = $this->buildSeries($growthStart, $overviewDays, $registrationGrowth);

        $roleBreakdown = User::selectRaw('role, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('role')
            ->orderByDesc('count')
            ->get()
            ->map(function ($row) use ($stats) {
                return [
                    'role' => $row->role ?: 'user',
                    'label' => str($row->role ?: 'user')->replace('_', ' ')->title(),
                    'count' => (int) $row->count,
                    'percentage' => $stats['users'] > 0 ? round(((int) $row->count / $stats['users']) * 100) : 0,
                ];
            });

        $recentEvents = Event::withCount('registrations')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('id', $managedEventIds);
            })
            ->whereBetween('event_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('event_date')
            ->take(5)
            ->get();

        $eventPerformance = Event::withCount(['registrations', 'raceResults'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('id', $managedEventIds);
            })
            ->whereBetween('event_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->latest()
            ->take(5)
            ->get();

        $completionAverage = round(
            $eventPerformance->map(function ($event) {
                return $event->registrations_count > 0
                    ? ($event->race_results_count / $event->registrations_count) * 100
                    : 0;
            })->avg() ?? 0,
            1
        );

        $recentFeedback = CommunityPost::with(['user', 'event'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->take(6)
            ->get();

        $moderationActivities = AdminActivityLog::with('user')
            ->where(function ($query) {
                $query->where('action', 'like', '%community-posts%')
                    ->orWhere('action', 'like', '%announcements%')
                    ->orWhere('action', 'like', '%training-modules%')
                    ->orWhere('action', 'like', '%notifications%');
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->take(6)
            ->get();

        $feedbackInsights = $this->buildFeedbackInsights($managedEventIds, $user->managesAssignedEventsOnly(), $startDate, $endDate);

        return view('admin.dashboard', compact(
            'dashboardRole',
            'stats',
            'eventStatusCounts',
            'recentActivities',
            'moderationActivities',
            'overviewSeries',
            'registrationSeries',
            'roleBreakdown',
            'recentEvents',
            'eventPerformance',
            'completionAverage',
            'recentFeedback',
            'feedbackInsights',
            'startDate',
            'endDate'
        ));
    }

    public function analytics()
    {
        return view('admin.analytics', $this->analyticsData('Analytics Dashboard'));
    }

    public function reports()
    {
        return view('admin.analytics', $this->analyticsData('Reports'));
    }

    public function export(string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['users', 'events', 'summary'], true), 404);

        $user = auth()->user();
        abort_if($type === 'users' && ! $user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EXECUTIVE]), 403);
        $filename = "racetech-{$type}-report-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($type, $user) {
            $handle = fopen('php://output', 'w');

            match ($type) {
                'users' => $this->writeUsersExport($handle),
                'events' => $this->writeEventsExport($handle, $user),
                'summary' => $this->writeSummaryExport($handle, $user),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function feedbackInsights()
    {
        $user = auth()->user();
        $recentFeedback = CommunityPost::with(['user', 'event'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            })
            ->latest()
            ->paginate(12);

        $feedbackInsights = $this->buildFeedbackInsights(
            $user->managedEventIds(),
            $user->managesAssignedEventsOnly()
        );

        return view('admin.feedback-insights', compact('recentFeedback', 'feedbackInsights'));
    }

    private function buildSeries($growthStart, int $overviewDays, $source)
    {
        return collect(range(0, $overviewDays - 1))->map(function ($offset) use ($growthStart, $source) {
            $date = $growthStart->copy()->addDays($offset);
            $formattedDate = $date->toDateString();

            return [
                'date' => $formattedDate,
                'label' => $date->format('M d'),
                'count' => (int) ($source[$formattedDate] ?? 0),
            ];
        });
    }

    private function buildFeedbackInsights(array $managedEventIds = [], bool $scopeToManagedEvents = false, ?CarbonInterface $startDate = null, ?CarbonInterface $endDate = null): array
    {
        $postQuery = CommunityPost::query()
            ->when($scopeToManagedEvents, function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            });

        $posts = (clone $postQuery)->latest()->take(150)->get();

        $complaintKeywords = ['late', 'delay', 'confusing', 'issue', 'problem', 'crowded', 'spam'];
        $suggestionKeywords = ['improve', 'suggest', 'better', 'please add', 'would like', 'more'];
        $positiveKeywords = ['good', 'great', 'smooth', 'excellent', 'love', 'organized'];

        $countMatches = function ($keywords) use ($posts) {
            return $posts->filter(function ($post) use ($keywords) {
                $content = str($post->content)->lower()->value();

                foreach ($keywords as $keyword) {
                    if (str_contains($content, $keyword)) {
                        return true;
                    }
                }

                return false;
            })->count();
        };

        return [
            'total_feedback' => (clone $postQuery)->count(),
            'flagged_feedback' => (clone $postQuery)->where('is_flagged', true)->count(),
            'deleted_feedback' => CommunityPost::onlyTrashed()
                ->when($scopeToManagedEvents, function ($query) use ($managedEventIds) {
                    $query->whereIn('event_id', $managedEventIds);
                })
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('deleted_at', [$startDate, $endDate]);
                })
                ->count(),
            'feedback_events' => (clone $postQuery)->whereNotNull('event_id')->distinct('event_id')->count('event_id'),
            'complaints' => $countMatches($complaintKeywords),
            'suggestions' => $countMatches($suggestionKeywords),
            'positive_mentions' => $countMatches($positiveKeywords),
            'ratings_available' => false,
        ];
    }

    private function analyticsData(string $pageTitle): array
    {
        $user = auth()->user();
        $managedEventIds = $user->managedEventIds();

        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $eventPerformance = Event::withCount(['registrations', 'raceResults'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('id', $managedEventIds);
            })
            ->get()
            ->map(function ($event) {
                return [
                    'name' => $event->title,
                    'registrations' => $event->registrations_count,
                    'results' => $event->race_results_count,
                    'completion_rate' => $event->registrations_count > 0
                        ? ($event->race_results_count / $event->registrations_count) * 100
                        : 0,
                ];
            });

        $dailyActiveUsers = User::selectRaw('DATE(last_login_at) as date, COUNT(*) as count')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return compact('userGrowth', 'eventPerformance', 'dailyActiveUsers', 'pageTitle');
    }

    private function writeUsersExport($handle): void
    {
        fputcsv($handle, ['Name', 'Email', 'Role', 'Verified At', 'Last Login']);

        User::orderBy('name')->get()->each(function ($user) use ($handle) {
            fputcsv($handle, [
                $user->name,
                $user->email,
                $user->roleLabel(),
                optional($user->email_verified_at)->format('Y-m-d H:i:s'),
                optional($user->last_login_at)->format('Y-m-d H:i:s'),
            ]);
        });
    }

    private function writeEventsExport($handle, $user): void
    {
        fputcsv($handle, ['Event', 'Date', 'Venue', 'Status', 'Manager', 'Registrations', 'Results']);

        Event::with(['manager'])
            ->withCount(['registrations', 'raceResults'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get()
            ->each(function ($event) use ($handle) {
                fputcsv($handle, [
                    $event->title,
                    optional($event->event_date)->format('Y-m-d'),
                    $event->venue,
                    $event->status,
                    $event->manager?->name,
                    $event->registrations_count,
                    $event->race_results_count,
                ]);
            });
    }

    private function writeSummaryExport($handle, $user): void
    {
        $managedEventIds = $user->managedEventIds();

        $eventQuery = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('id', $managedEventIds);
            });

        $registrationQuery = Registration::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            });

        $resultQuery = RaceResult::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            });

        fputcsv($handle, ['Metric', 'Value']);
        fputcsv($handle, ['Events', (clone $eventQuery)->count()]);
        fputcsv($handle, ['Registrations', (clone $registrationQuery)->count()]);
        fputcsv($handle, ['Pending Registrations', (clone $registrationQuery)->where('status', 'pending')->count()]);
        fputcsv($handle, ['Checked In', (clone $registrationQuery)->where('status', 'checked_in')->count()]);
        fputcsv($handle, ['Completed', (clone $registrationQuery)->where('status', 'completed')->count()]);
        fputcsv($handle, ['Results Published', (clone $resultQuery)->count()]);
    }
}
