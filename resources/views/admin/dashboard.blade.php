@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $roleLabels = \App\Models\User::roleLabels();
    $dashboardLabel = $roleLabels[$dashboardRole] ?? str($dashboardRole)->replace('_', ' ')->title();
    $selectedRangeLabel = $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y');
    $selectedRangeDays = max($startDate->diffInDays($endDate) + 1, 1);

    $breakdownPalette = ['#eceef1', '#d9dde3', '#b9bec7', '#8e96a3', '#5d6777'];

    if ($dashboardRole === \App\Models\User::ROLE_EXECUTIVE) {
        $metricCards = [
            ['label' => 'Total Events', 'value' => number_format($stats['events']), 'icon' => 'fa-calendar-days', 'delta' => number_format($stats['upcoming_events']) . ' upcoming'],
            ['label' => 'Total Participants', 'value' => number_format($stats['registrations']), 'icon' => 'fa-users', 'delta' => 'Registered participants'],
            ['label' => 'Completed Events', 'value' => number_format($eventStatusCounts['completed']), 'icon' => 'fa-flag-checkered', 'delta' => number_format($eventStatusCounts['ongoing'] + $eventStatusCounts['published'] + $eventStatusCounts['upcoming']) . ' active or scheduled'],
            ['label' => 'Avg Completion', 'value' => number_format($completionAverage, 1) . '%', 'icon' => 'fa-chart-line', 'delta' => 'Results versus registrations'],
        ];

        $chartTitle = 'Registration Trends';
        $chartSeries = $registrationSeries;
        $chartMetricLabel = 'Registrations';

        $breakdownTitle = 'Event Status Overview';
        $breakdownItems = collect([
            ['label' => 'Draft', 'count' => $eventStatusCounts['draft']],
            ['label' => 'Published', 'count' => $eventStatusCounts['published']],
            ['label' => 'Ongoing', 'count' => $eventStatusCounts['ongoing']],
            ['label' => 'Completed', 'count' => $eventStatusCounts['completed']],
            ['label' => 'Upcoming', 'count' => $eventStatusCounts['upcoming']],
        ]);

        $primaryTitle = 'Event Performance Comparison';
        $primaryType = 'event-performance';
        $secondaryTitle = 'Feedback Snapshot';
        $secondaryType = 'feedback-summary';
    } elseif ($dashboardRole === \App\Models\User::ROLE_CONTENT_MODERATOR) {
        $metricCards = [
            ['label' => 'Flagged Feedback', 'value' => number_format($feedbackInsights['flagged_feedback']), 'icon' => 'fa-flag', 'delta' => 'Needs moderation review'],
            ['label' => 'Published Notices', 'value' => number_format($stats['announcements']), 'icon' => 'fa-bullhorn', 'delta' => 'Public announcements live'],
            ['label' => 'Training Content', 'value' => number_format($stats['training_modules']), 'icon' => 'fa-book-open', 'delta' => 'Modules available for review'],
            ['label' => 'Deleted Feedback', 'value' => number_format($feedbackInsights['deleted_feedback']), 'icon' => 'fa-trash', 'delta' => 'Removed or archived items'],
        ];

        $chartTitle = 'Platform Activity';
        $chartSeries = $overviewSeries;
        $chartMetricLabel = 'New users';

        $breakdownTitle = 'Content Moderation Summary';
        $breakdownItems = collect([
            ['label' => 'Flagged Feedback', 'count' => $feedbackInsights['flagged_feedback']],
            ['label' => 'Suggestions', 'count' => $feedbackInsights['suggestions']],
            ['label' => 'Complaints', 'count' => $feedbackInsights['complaints']],
            ['label' => 'Positive Notes', 'count' => $feedbackInsights['positive_mentions']],
        ]);

        $primaryTitle = 'Recent Feedback Queue';
        $primaryType = 'feedback-feed';
        $secondaryTitle = 'Moderation Activity';
        $secondaryType = 'moderation-activity';
    } elseif ($dashboardRole === \App\Models\User::ROLE_EVENT_MANAGER) {
        $metricCards = [
            ['label' => 'Upcoming Events', 'value' => number_format($stats['upcoming_events']), 'icon' => 'fa-calendar-days', 'delta' => 'Scheduled events'],
            ['label' => 'Pending Registrations', 'value' => number_format($stats['pending_registrations']), 'icon' => 'fa-id-card', 'delta' => 'Awaiting action'],
            ['label' => 'Published Results', 'value' => number_format($stats['results']), 'icon' => 'fa-trophy', 'delta' => 'Race results encoded'],
            ['label' => 'Checkpoints', 'value' => number_format($stats['checkpoints']), 'icon' => 'fa-location-dot', 'delta' => 'Route support locations'],
        ];

        $chartTitle = 'Participant Registration Trends';
        $chartSeries = $registrationSeries;
        $chartMetricLabel = 'Registrations';

        $breakdownTitle = 'Event Status Overview';
        $breakdownItems = collect([
            ['label' => 'Draft', 'count' => $eventStatusCounts['draft']],
            ['label' => 'Published', 'count' => $eventStatusCounts['published']],
            ['label' => 'Ongoing', 'count' => $eventStatusCounts['ongoing']],
            ['label' => 'Completed', 'count' => $eventStatusCounts['completed']],
            ['label' => 'Upcoming', 'count' => $eventStatusCounts['upcoming']],
        ]);

        $primaryTitle = 'Recent Events';
        $primaryType = 'recent-events';
        $secondaryTitle = 'Event Feedback Snapshot';
        $secondaryType = 'feedback-summary';
    } else {
        $metricCards = [
            ['label' => 'Total Users', 'value' => number_format($stats['users']), 'icon' => 'fa-users', 'delta' => number_format($stats['active_users_in_range']) . ' active in range'],
            ['label' => 'Events', 'value' => number_format($stats['events']), 'icon' => 'fa-calendar-days', 'delta' => number_format($stats['upcoming_events']) . ' upcoming'],
            ['label' => 'Registrations', 'value' => number_format($stats['registrations']), 'icon' => 'fa-id-card', 'delta' => number_format($stats['pending_registrations']) . ' pending review'],
            ['label' => 'Results', 'value' => number_format($stats['results']), 'icon' => 'fa-trophy', 'delta' => 'Published race records'],
            ['label' => 'Training', 'value' => number_format($stats['training_modules']), 'icon' => 'fa-book-open', 'delta' => number_format($stats['checkpoints']) . ' checkpoints live'],
            ['label' => 'Alerts', 'value' => number_format($stats['security_alerts']), 'icon' => 'fa-shield-halved', 'delta' => 'Security watchlist items'],
        ];

        $chartTitle = 'Overview';
        $chartSeries = $overviewSeries;
        $chartMetricLabel = 'Users';

        $breakdownTitle = 'Users by Role';
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
    $segments = [];
    $angle = 0;
    foreach ($breakdownItems->values() as $index => $item) {
        $start = $angle;
        $angle += $breakdownTotal > 0 ? (($item['count'] / $breakdownTotal) * 360) : 0;
        $segments[] = $breakdownPalette[$index % count($breakdownPalette)] . ' ' . number_format($start, 2, '.', '') . 'deg ' . number_format($angle, 2, '.', '') . 'deg';
    }
    $breakdownChart = count($segments) > 0 ? 'conic-gradient(' . implode(', ', $segments) . ')' : 'conic-gradient(#eceef1 0deg 360deg)';
@endphp

<div class="space-y-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">{{ $dashboardLabel }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">
                {{ $dashboardRole === \App\Models\User::ROLE_EXECUTIVE ? 'Executive Dashboard' : 'Dashboard' }}
            </h1>
            <p class="mt-2 text-sm text-[#6d7685]">Showing data for {{ strtolower($selectedRangeDays === 1 ? '1 day' : number_format($selectedRangeDays) . ' days') }}.</p>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-3 rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-medium text-[#202733] shadow-sm">
            <div class="flex items-center gap-3 text-[#202733]">
                <i class="fas fa-calendar-days text-[#677180]"></i>
                <span class="hidden text-sm font-medium text-[#4f5968] sm:inline">{{ $selectedRangeLabel }}</span>
            </div>
            <input
                type="date"
                name="start_date"
                value="{{ $startDate->toDateString() }}"
                class="rounded-lg border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#202733] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
            <span class="text-[#7d8694]">to</span>
            <input
                type="date"
                name="end_date"
                value="{{ $endDate->toDateString() }}"
                class="rounded-lg border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#202733] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#111827] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Apply
            </button>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-[#d9dee7] px-4 py-2 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Reset
            </a>
        </form>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 {{ $dashboardRole === \App\Models\User::ROLE_SUPER_ADMIN ? '2xl:grid-cols-6' : '' }}">
        @foreach ($metricCards as $card)
            <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-[#d9dee7] bg-[#f8f9fb] text-lg text-[#4d5664]">
                        <i class="fas {{ $card['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-[#6d7685]">{{ $card['label'] }}</p>
                        <p class="mt-1 text-[2rem] font-semibold leading-none tracking-tight text-[#111827]">{{ $card['value'] }}</p>
                    </div>
                </div>
                <p class="mt-5 text-sm text-[#4f5968]">{{ $card['delta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
        <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-[1.65rem] font-semibold tracking-tight text-[#111827]">{{ $chartTitle }}</h2>
                <div class="inline-flex items-center gap-3 rounded-xl border border-[#d9dee7] px-4 py-2 text-sm text-[#202733]">
                    <span>{{ $chartMetricLabel }}</span>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-[56px_minmax(0,1fr)] gap-3">
                <div class="flex h-[260px] flex-col justify-between pb-7 text-sm text-[#6d7685]">
                    <span>{{ number_format($maxChartValue) }}</span>
                    <span>{{ number_format((int) round($maxChartValue * 0.75)) }}</span>
                    <span>{{ number_format((int) round($maxChartValue * 0.5)) }}</span>
                    <span>{{ number_format((int) round($maxChartValue * 0.25)) }}</span>
                    <span>0</span>
                </div>

                <div class="min-w-0">
                    <div class="relative h-[260px] overflow-hidden">
                        <div class="absolute inset-0 grid grid-rows-4">
                            @for ($i = 0; $i < 4; $i++)
                                <div class="border-b border-[#eef1f4]"></div>
                            @endfor
                        </div>

                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 h-full w-full">
                            <defs>
                                <linearGradient id="overview-fill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#d1d5db" stop-opacity="0.45"></stop>
                                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                            <polygon points="{{ $areaPoints }}" fill="url(#overview-fill)"></polygon>
                            <polyline points="{{ $polylinePoints }}" fill="none" stroke="#8b929d" stroke-width="2"></polyline>
                            @foreach ($chartSeries as $index => $point)
                                @php
                                    $x = ($index / $pointCount) * 100;
                                    $y = 100 - (($point['count'] / $maxChartValue) * 100);
                                @endphp
                                <circle cx="{{ number_format($x, 2, '.', '') }}" cy="{{ number_format($y, 2, '.', '') }}" r="1.4" fill="#8b929d"></circle>
                            @endforeach
                        </svg>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-2 text-sm text-[#6d7685]">
                        @foreach ($chartLabels as $point)
                            <span>{{ $point['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </article>

        <article class="rounded-xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <h2 class="text-[1.65rem] font-semibold tracking-tight text-[#111827]">{{ $breakdownTitle }}</h2>

            <div class="mt-8 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-1 2xl:grid-cols-[220px_minmax(0,1fr)]">
                <div class="mx-auto flex h-[196px] w-[196px] items-center justify-center rounded-full" style="background: {{ $breakdownChart }}">
                    <div class="flex h-[116px] w-[116px] items-center justify-center rounded-full bg-white">
                        <div class="text-center">
                            <p class="text-xs uppercase tracking-[0.22em] text-[#7a8392]">Total</p>
                            <p class="mt-1 text-2xl font-semibold text-[#111827]">{{ number_format($breakdownTotal) }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    @forelse ($breakdownItems as $index => $item)
                        <div class="grid grid-cols-[18px_minmax(0,1fr)_auto] items-center gap-4 text-sm text-[#202733]">
                            <span class="h-4 w-4 rounded-full" style="background-color: {{ $breakdownPalette[$index % count($breakdownPalette)] }}"></span>
                            <span>{{ $item['label'] }}</span>
                            <span>{{ number_format($item['count']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-[#6d7685]">No breakdown data available.</p>
                    @endforelse
                </div>
            </div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_380px]">
        <article class="rounded-xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#e9edf2] px-5 py-4">
                <h2 class="text-[1.65rem] font-semibold tracking-tight text-[#111827]">{{ $primaryTitle }}</h2>
            </div>

            @if ($primaryType === 'event-performance')
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead>
                            <tr class="border-b border-[#e9edf2] text-sm text-[#4f5968]">
                                <th class="px-5 py-4 font-medium">Event</th>
                                <th class="px-5 py-4 font-medium">Registrations</th>
                                <th class="px-5 py-4 font-medium">Results</th>
                                <th class="px-5 py-4 font-medium">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($eventPerformance as $event)
                                @php
                                    $completionRate = $event->registrations_count > 0 ? round(($event->race_results_count / $event->registrations_count) * 100, 1) : 0;
                                @endphp
                                <tr class="border-b border-[#eef1f4] text-sm text-[#202733]">
                                    <td class="px-5 py-4 font-medium">{{ $event->title }}</td>
                                    <td class="px-5 py-4">{{ $event->registrations_count }}</td>
                                    <td class="px-5 py-4">{{ $event->race_results_count }}</td>
                                    <td class="px-5 py-4">{{ $completionRate }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-[#6d7685]">No event performance data available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif ($primaryType === 'feedback-feed')
                <div class="space-y-1 px-5 py-4">
                    @forelse ($recentFeedback as $feedback)
                        <div class="border-b border-[#eef1f4] py-4 last:border-b-0">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-[#202733]">{{ $feedback->user?->name ?? 'Participant' }}</p>
                                    <p class="mt-1 text-sm text-[#4f5968]">{{ $feedback->event?->title ?? 'General Feedback' }}</p>
                                </div>
                                <span class="rounded-lg px-3 py-1 text-xs font-medium {{ $feedback->is_flagged ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $feedback->is_flagged ? 'Flagged' : 'Visible' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-[#5a6473]">{{ \Illuminate\Support\Str::limit($feedback->content, 150) }}</p>
                        </div>
                    @empty
                        <div class="py-8 text-sm text-[#6d7685]">No feedback queue available.</div>
                    @endforelse
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead>
                            <tr class="border-b border-[#e9edf2] text-sm text-[#4f5968]">
                                <th class="px-5 py-4 font-medium">Event Name</th>
                                <th class="px-5 py-4 font-medium">Date</th>
                                <th class="px-5 py-4 font-medium">Location</th>
                                <th class="px-5 py-4 font-medium">Status</th>
                                <th class="px-5 py-4 font-medium">Registrations</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentEvents as $event)
                                <tr class="border-b border-[#eef1f4] text-sm text-[#202733]">
                                    <td class="px-5 py-4 font-medium">{{ $event->title }}</td>
                                    <td class="px-5 py-4">{{ optional($event->event_date)->format('M d, Y') ?? 'TBD' }}</td>
                                    <td class="px-5 py-4">{{ $event->venue ?: 'Location TBD' }}</td>
                                    <td class="px-5 py-4">{{ str($event->effective_status ?? 'scheduled')->replace('_', ' ')->title() }}</td>
                                    <td class="px-5 py-4">{{ $event->registrations_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-[#6d7685]">No recent events available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </article>

        <article class="rounded-xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="border-b border-[#e9edf2] px-5 py-4">
                <h2 class="text-[1.65rem] font-semibold tracking-tight text-[#111827]">{{ $secondaryTitle }}</h2>
            </div>

            @if ($secondaryType === 'feedback-summary')
                <div class="space-y-4 px-5 py-4">
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="text-sm font-semibold text-[#111827]">Complaint Signals</p>
                        <p class="mt-1 text-sm leading-6 text-[#5a6473]">{{ number_format($feedbackInsights['complaints']) }} posts contain complaint-oriented language.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="text-sm font-semibold text-[#111827]">Suggestions</p>
                        <p class="mt-1 text-sm leading-6 text-[#5a6473]">{{ number_format($feedbackInsights['suggestions']) }} posts contain improvement suggestions.</p>
                    </div>
                    <div class="rounded-xl border border-[#eef1f4] bg-[#f8f9fb] px-4 py-4">
                        <p class="text-sm font-semibold text-[#111827]">Ratings</p>
                        <p class="mt-1 text-sm leading-6 text-[#5a6473]">Structured ratings are not yet stored in the current schema.</p>
                    </div>
                </div>
            @elseif ($secondaryType === 'moderation-activity')
                <div class="space-y-1 px-5 py-4">
                    @forelse ($moderationActivities as $activity)
                        <div class="flex items-start gap-4 border-b border-[#eef1f4] py-4 last:border-b-0">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full border border-[#cfd5de] bg-[#f5f6f8] text-sm font-semibold text-[#606978]">
                                {{ $activity->user?->initials() ?: 'AD' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-[#202733]">{{ $activity->user?->name ?? 'System' }}</p>
                                <p class="text-sm leading-6 text-[#4f5968]">{{ $activity->action }}</p>
                            </div>
                            <p class="shrink-0 text-sm text-[#7d8694]">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <div class="py-8 text-sm text-[#6d7685]">No moderation actions logged yet.</div>
                    @endforelse
                </div>
            @else
                <div class="space-y-1 px-5 py-4">
                    @forelse ($recentActivities as $activity)
                        <div class="flex items-start gap-4 border-b border-[#eef1f4] py-4 last:border-b-0">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full border border-[#cfd5de] bg-[#f5f6f8] text-sm font-semibold text-[#606978]">
                                {{ $activity->user?->initials() ?: 'AD' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-[#202733]">{{ $activity->user?->name ?? 'System' }}</p>
                                <p class="text-sm leading-6 text-[#4f5968]">{{ $activity->action }}</p>
                            </div>
                            <p class="shrink-0 text-sm text-[#7d8694]">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <div class="py-8 text-sm text-[#6d7685]">No recent activity yet.</div>
                    @endforelse
                </div>
            @endif
        </article>
    </section>
</div>
@endsection
