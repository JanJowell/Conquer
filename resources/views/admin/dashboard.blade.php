@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $roleLabels = \App\Models\User::roleLabels();
    $dashboardLabel = $roleLabels[$dashboardRole] ?? str($dashboardRole)->replace('_', ' ')->title();
    $selectedRangeLabel = $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y');
    $selectedRangeDays = max($startDate->diffInDays($endDate) + 1, 1);

    $breakdownPalette = ['#818cf8', '#7dd3fc', '#86efac', '#fcd34d', '#fca5a5'];

    if ($dashboardRole === \App\Models\User::ROLE_EXECUTIVE) {
        $metricCards = [
            ['label' => 'Events in Range', 'value' => number_format($stats['events']), 'icon' => 'fa-calendar-days', 'delta' => number_format($stats['upcoming_events']) . ' upcoming'],
            ['label' => 'Participants in Range', 'value' => number_format($stats['registrations']), 'icon' => 'fa-users', 'delta' => number_format($stats['pending_registrations']) . ' awaiting approval'],
            ['label' => 'Ready for Check-in', 'value' => number_format($stats['ready_for_check_in']), 'icon' => 'fa-clipboard-check', 'delta' => number_format($stats['checked_in_registrations']) . ' already checked in'],
            ['label' => 'Avg Completion', 'value' => number_format($completionAverage, 1) . '%', 'icon' => 'fa-chart-line', 'delta' => 'Results versus registrations'],
        ];

        $chartTitle = 'Registration Trends';
        $chartSeries = $registrationSeries;
        $chartMetricLabel = 'Registrations';

        $breakdownTitle = 'Event Status in Range';
        $breakdownItems = collect([
            ['label' => 'Draft', 'count' => $eventStatusCounts['draft']],
            ['label' => 'Upcoming', 'count' => $eventStatusCounts['upcoming']],
            ['label' => 'Ongoing', 'count' => $eventStatusCounts['ongoing']],
            ['label' => 'Completed', 'count' => $eventStatusCounts['completed']],
        ]);

        $primaryTitle = 'Event Health Summary';
        $primaryType = 'event-performance';
        $secondaryTitle = 'Feedback Snapshot';
        $secondaryType = 'feedback-summary';
    } elseif ($dashboardRole === \App\Models\User::ROLE_CONTENT_MODERATOR) {
        $metricCards = [
            ['label' => 'Flagged Posts', 'value' => number_format($feedbackInsights['flagged_feedback']), 'icon' => 'fa-flag', 'delta' => 'Needs moderation review', 'url' => route('admin.content.community-posts', ['status' => 'flagged'])],
            ['label' => 'Flagged Comments', 'value' => number_format($feedbackInsights['flagged_comments']), 'icon' => 'fa-comment-dots', 'delta' => 'Comment-level review items'],
            ['label' => 'Training Drafts', 'value' => number_format($stats['training_drafts']), 'icon' => 'fa-book-open', 'delta' => number_format($stats['training_modules']) . ' modules changed in range', 'url' => route('admin.content.training-modules', ['status' => 'draft'])],
            ['label' => 'Deleted Items', 'value' => number_format($feedbackInsights['deleted_feedback'] + $feedbackInsights['deleted_comments']), 'icon' => 'fa-trash', 'delta' => 'Posts and comments removed'],
        ];

        $chartTitle = 'Community Feedback Activity';
        $chartSeries = $contentActivitySeries;
        $chartMetricLabel = 'Posts';

        $breakdownTitle = 'Content Moderation Summary';
        $breakdownItems = collect([
            ['label' => 'Flagged Posts', 'count' => $feedbackInsights['flagged_feedback']],
            ['label' => 'Flagged Comments', 'count' => $feedbackInsights['flagged_comments']],
            ['label' => 'Suggestions', 'count' => $feedbackInsights['suggestions']],
            ['label' => 'Complaints', 'count' => $feedbackInsights['complaints']],
        ]);

        $primaryTitle = 'Flagged-First Feedback Queue';
        $primaryType = 'feedback-feed';
        $secondaryTitle = 'Moderation Activity';
        $secondaryType = 'moderation-activity';
    } elseif ($dashboardRole === \App\Models\User::ROLE_EVENT_MANAGER) {
        $metricCards = [
            ['label' => 'Upcoming Events', 'value' => number_format($stats['upcoming_events']), 'icon' => 'fa-calendar-days', 'delta' => 'Scheduled events'],
            ['label' => 'Pending Registrations', 'value' => number_format($stats['pending_registrations']), 'icon' => 'fa-id-card', 'delta' => 'Awaiting action'],
            ['label' => 'Ready for Check-in', 'value' => number_format($stats['ready_for_check_in']), 'icon' => 'fa-clipboard-check', 'delta' => 'Approved participants'],
            ['label' => 'Checked In', 'value' => number_format($stats['checked_in_registrations']), 'icon' => 'fa-person-running', 'delta' => number_format($stats['results']) . ' results published'],
        ];

        $chartTitle = 'Participant Registration Trends';
        $chartSeries = $registrationSeries;
        $chartMetricLabel = 'Registrations';

        $breakdownTitle = 'Event Status in Range';
        $breakdownItems = collect([
            ['label' => 'Draft', 'count' => $eventStatusCounts['draft']],
            ['label' => 'Upcoming', 'count' => $eventStatusCounts['upcoming']],
            ['label' => 'Ongoing', 'count' => $eventStatusCounts['ongoing']],
            ['label' => 'Completed', 'count' => $eventStatusCounts['completed']],
        ]);

        $primaryTitle = 'Recent Events';
        $primaryType = 'recent-events';
        $secondaryTitle = 'Event Feedback Snapshot';
        $secondaryType = 'feedback-summary';
    } else {
        $metricCards = [
            ['label' => 'New Users', 'value' => number_format($stats['users']), 'icon' => 'fa-users', 'delta' => number_format($stats['active_users_in_range']) . ' active in range'],
            ['label' => 'Events in Range', 'value' => number_format($stats['events']), 'icon' => 'fa-calendar-days', 'delta' => number_format($stats['upcoming_events']) . ' upcoming'],
            ['label' => 'Pending Registrations', 'value' => number_format($stats['pending_registrations']), 'icon' => 'fa-id-card', 'delta' => 'Needs approval before check-in'],
            ['label' => 'Ready for Check-in', 'value' => number_format($stats['ready_for_check_in']), 'icon' => 'fa-clipboard-check', 'delta' => number_format($stats['checked_in_registrations']) . ' already checked in'],
            ['label' => 'Results Published', 'value' => number_format($stats['results']), 'icon' => 'fa-trophy', 'delta' => number_format($stats['registrations']) . ' registrations in range'],
            ['label' => 'Flagged Feedback', 'value' => number_format($feedbackInsights['flagged_feedback']), 'icon' => 'fa-flag', 'delta' => number_format($stats['security_alerts']) . ' security alerts'],
        ];

        $chartTitle = 'Overview';
        $chartSeries = $overviewSeries;
        $chartMetricLabel = 'Users';

        $breakdownTitle = 'New Users by Role';
        $breakdownItems = $roleBreakdown->map(fn ($role) => ['label' => $role['label'], 'count' => $role['count']]);

        $primaryTitle = 'Recent Events';
        $primaryType = 'recent-events';
        $secondaryTitle = 'Recent Activity';
        $secondaryType = 'recent-activity';
    }

    $chartCounts = collect($chartSeries)->pluck('count');
    $maxChartValue = max($chartCounts->max() ?: 0, 1);
    $pointCount = max(collect($chartSeries)->count() - 1, 1);

    $polylinePoints = collect($chartSeries)
        ->values()
        ->map(function ($point, $index) use ($maxChartValue, $pointCount) {
            $x = ($index / $pointCount) * 100;
            $y = 100 - (($point['count'] / $maxChartValue) * 100);

            return number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        })
        ->implode(' ');

    $areaPoints = trim('0,100 ' . $polylinePoints . ' 100,100');
    $chartLabels = collect($chartSeries)->filter(fn ($point, $index) => $index % 7 === 0 || $index === collect($chartSeries)->count() - 1);

    $breakdownTotal = $breakdownItems->sum('count');
@endphp

<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1400px] space-y-6">

        {{-- PAGE HEADER --}}
        <div class="flex flex-col gap-4 rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.15)] lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">{{ $dashboardLabel }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-700">
                    {{ $dashboardRole === \App\Models\User::ROLE_EXECUTIVE ? 'Executive Dashboard' : 'Dashboard' }}
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    Showing activity from <span class="font-medium text-slate-600">{{ $selectedRangeLabel }}</span>
                </p>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input
                    type="date"
                    name="start_date"
                    value="{{ $startDate->toDateString() }}"
                    class="rounded-full border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                >
                <span class="text-sm text-slate-300">to</span>
                <input
                    type="date"
                    name="end_date"
                    value="{{ $endDate->toDateString() }}"
                    class="rounded-full border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                >
                <button type="submit" class="rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Apply
                </button>
                <a href="{{ route('admin.dashboard') }}" class="rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                    Reset
                </a>
            </form>
        </div>

        {{-- METRIC CARDS --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($metricCards as $card)
                @php $tag = !empty($card['url']) ? 'a' : 'div'; @endphp
                <{{ $tag }} @if(!empty($card['url'])) href="{{ $card['url'] }}" @endif
                    class="flex items-start gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition hover:shadow-[0_10px_28px_rgba(148,163,184,0.18)]">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-400">
                        <i class="fas {{ $card['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-400">{{ $card['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-700">{{ $card['value'] }}</p>
                        <p class="mt-1 text-xs text-slate-300">{{ $card['delta'] }}</p>
                    </div>
                </{{ $tag }}>
            @endforeach
        </section>

        {{-- CHARTS --}}
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
            <article class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-700">{{ $chartTitle }}</h2>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-400">{{ $chartMetricLabel }}</span>
                </div>

                <div class="mt-6 grid grid-cols-[44px_minmax(0,1fr)] gap-2">
                    <div class="flex h-[220px] flex-col justify-between pb-6 text-xs text-slate-300">
                        <span>{{ number_format($maxChartValue) }}</span>
                        <span>{{ number_format((int) round($maxChartValue * 0.5)) }}</span>
                        <span>0</span>
                    </div>

                    <div class="min-w-0">
                        <div class="relative h-[220px] rounded-2xl bg-[#f7f8fc]">
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-3 h-[calc(100%-1.5rem)] w-[calc(100%-1.5rem)]">
                                <defs>
                                    <linearGradient id="overview-fill" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#818cf8" stop-opacity="0.22"></stop>
                                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0"></stop>
                                    </linearGradient>
                                </defs>
                                <polygon points="{{ $areaPoints }}" fill="url(#overview-fill)"></polygon>
                                <polyline points="{{ $polylinePoints }}" fill="none" stroke="#818cf8" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></polyline>
                            </svg>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-slate-300">
                            @foreach ($chartLabels as $point)
                                <span>{{ $point['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <h2 class="text-base font-semibold text-slate-700">{{ $breakdownTitle }}</h2>

                <div class="mt-5 space-y-3">
                    @forelse ($breakdownItems as $index => $item)
                        @php
                            $pct = $breakdownTotal > 0 ? round(($item['count'] / $breakdownTotal) * 100) : 0;
                            $color = $breakdownPalette[$index % count($breakdownPalette)];
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-600">{{ $item['label'] }}</span>
                                <span class="font-semibold text-slate-700">{{ number_format($item['count']) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" style="width: {{ $pct }}%; background-color: {{ $color }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">No breakdown data available.</p>
                    @endforelse
                </div>

                <div class="mt-5 border-t border-slate-50 pt-4 text-sm text-slate-400">
                    Total: <span class="font-semibold text-slate-700">{{ number_format($breakdownTotal) }}</span>
                </div>
            </article>
        </section>

        {{-- TABLE / FEED AREA --}}
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_360px]">
            <article class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="border-b border-slate-50 px-6 py-5">
                    <h2 class="text-base font-semibold text-slate-700">{{ $primaryTitle }}</h2>
                </div>

                @if ($primaryType === 'event-performance')
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="text-slate-400">
                                    <th class="px-6 py-3 font-medium">Event</th>
                                    @if ($dashboardRole === \App\Models\User::ROLE_EXECUTIVE)
                                        <th class="px-6 py-3 font-medium">Setup</th>
                                        <th class="px-6 py-3 font-medium">Registrations</th>
                                        <th class="px-6 py-3 font-medium">Race Day</th>
                                        <th class="px-6 py-3 font-medium">Status</th>
                                    @else
                                        <th class="px-6 py-3 font-medium">Registrations</th>
                                        <th class="px-6 py-3 font-medium">Results</th>
                                        <th class="px-6 py-3 font-medium">Completion Rate</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @if ($dashboardRole === \App\Models\User::ROLE_EXECUTIVE)
                                    @forelse ($eventHealth as $event)
                                        @php
                                            $missingItems = collect([
                                                'Categories' => $event->categories_count === 0,
                                                'Announcements' => $event->published_announcements_count === 0,
                                                'Results' => $event->checked_in_registrations_count > 0 && $event->race_results_count === 0,
                                            ])->filter()->keys();
                                        @endphp
                                        <tr class="transition hover:bg-[#f7f8fc]">
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-slate-700">{{ $event->title }}</p>
                                                <p class="mt-0.5 text-xs text-slate-300">{{ optional($event->event_date)->format('M d, Y') ?? 'TBD' }}</p>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-slate-400">
                                                {{ number_format($event->categories_count) }} categories<br>
                                                {{ number_format($event->published_announcements_count) }} announcements
                                            </td>
                                            <td class="px-6 py-4 font-medium text-slate-700">{{ number_format($event->registrations_count) }}</td>
                                            <td class="px-6 py-4 text-xs text-slate-400">
                                                {{ number_format($event->checked_in_registrations_count) }} checked in<br>
                                                {{ number_format($event->race_results_count) }} results
                                            </td>
                                            <td class="px-6 py-4">
                                                @if ($missingItems->isEmpty())
                                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-500">Healthy</span>
                                                @else
                                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-500">
                                                        Needs {{ $missingItems->take(2)->implode(', ') }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-400">No upcoming events available for health review.</td></tr>
                                    @endforelse
                                @else
                                    @forelse ($eventPerformance as $event)
                                        @php
                                            $completionRate = $event->registrations_count > 0 ? round(($event->race_results_count / $event->registrations_count) * 100, 1) : 0;
                                        @endphp
                                        <tr class="transition hover:bg-[#f7f8fc]">
                                            <td class="px-6 py-4 font-medium text-slate-700">{{ $event->title }}</td>
                                            <td class="px-6 py-4 text-slate-500">{{ $event->registrations_count }}</td>
                                            <td class="px-6 py-4 text-slate-500">{{ $event->race_results_count }}</td>
                                            <td class="px-6 py-4 font-medium text-slate-700">{{ $completionRate }}%</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400">No event performance data available.</td></tr>
                                    @endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                @elseif ($primaryType === 'feedback-feed')
                    <div class="divide-y divide-slate-50">
                        @forelse ($recentFeedback as $feedback)
                            <div class="px-6 py-4 transition hover:bg-[#f7f8fc]">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-slate-700">{{ $feedback->user?->name ?? 'Participant' }}</p>
                                        <p class="text-xs text-slate-300">{{ $feedback->event?->title ?? 'General Feedback' }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $feedback->is_flagged ? 'bg-amber-50 text-amber-500' : 'bg-emerald-50 text-emerald-500' }}">
                                        {{ $feedback->is_flagged ? 'Flagged' : 'Visible' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($feedback->content ?: 'Media-only post', 150) }}</p>
                                <a href="{{ route('admin.content.community-posts.show', $feedback) }}" class="mt-2 inline-block text-sm font-medium text-indigo-400 hover:text-indigo-500">
                                    Open item →
                                </a>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-slate-400">No feedback queue available.</div>
                        @endforelse
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="text-slate-400">
                                    <th class="px-6 py-3 font-medium">Event Name</th>
                                    <th class="px-6 py-3 font-medium">Date</th>
                                    <th class="px-6 py-3 font-medium">Location</th>
                                    <th class="px-6 py-3 font-medium">Status</th>
                                    <th class="px-6 py-3 font-medium">Registrations</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentEvents as $event)
                                    <tr class="transition hover:bg-[#f7f8fc]">
                                        <td class="px-6 py-4 font-medium text-slate-700">{{ $event->title }}</td>
                                        <td class="px-6 py-4 text-slate-500">{{ optional($event->event_date)->format('M d, Y') ?? 'TBD' }}</td>
                                        <td class="px-6 py-4 text-slate-500">{{ $event->venue ?: 'Location TBD' }}</td>
                                        <td class="px-6 py-4 text-slate-500">{{ str($event->effective_status ?? 'scheduled')->replace('_', ' ')->title() }}</td>
                                        <td class="px-6 py-4 font-medium text-slate-700">{{ $event->registrations_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-400">No recent events available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>

            {{-- SECONDARY PANEL --}}
            <article class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="border-b border-slate-50 px-6 py-5">
                    <h2 class="text-base font-semibold text-slate-700">{{ $secondaryTitle }}</h2>
                </div>

                @if ($secondaryType === 'feedback-summary')
                    <div class="space-y-3 px-6 py-5">
                        <div class="rounded-2xl bg-rose-50 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">Complaint Signals</p>
                            <p class="mt-1 text-sm text-slate-500">{{ number_format($feedbackInsights['complaints']) }} posts contain complaint-oriented language.</p>
                        </div>
                        <div class="rounded-2xl bg-indigo-50 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">Suggestions</p>
                            <p class="mt-1 text-sm text-slate-500">{{ number_format($feedbackInsights['suggestions']) }} posts contain improvement suggestions.</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">Ratings</p>
                            <p class="mt-1 text-sm text-slate-500">Structured ratings are not yet stored in the current schema.</p>
                        </div>
                    </div>
                @elseif ($secondaryType === 'moderation-activity')
                    <div class="divide-y divide-slate-50">
                        @forelse ($moderationActivities as $activity)
                            <div class="flex items-start gap-3 px-6 py-4 transition hover:bg-[#f7f8fc]">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-400 text-xs font-bold text-white">
                                    {{ $activity->user?->initials() ?: 'AD' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-700">{{ $activity->user?->name ?? 'System' }}</p>
                                    <p class="text-sm text-slate-500">{{ $activity->action }}</p>
                                </div>
                                <p class="shrink-0 text-xs text-slate-300">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-slate-400">No moderation actions logged yet.</div>
                        @endforelse
                    </div>
                @else
                    <div class="divide-y divide-slate-50">
                        @forelse ($recentActivities as $activity)
                            <div class="flex items-start gap-3 px-6 py-4 transition hover:bg-[#f7f8fc]">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-400 text-xs font-bold text-white">
                                    {{ $activity->user?->initials() ?: 'AD' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-700">{{ $activity->user?->name ?? 'System' }}</p>
                                    <p class="text-sm text-slate-500">{{ $activity->action }}</p>
                                </div>
                                <p class="shrink-0 text-xs text-slate-300">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-slate-400">No recent activity yet.</div>
                        @endforelse
                    </div>
                @endif
            </article>
        </section>
    </div>
</div>
@endsection