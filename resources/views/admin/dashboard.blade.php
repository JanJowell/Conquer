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
    $segments = [];
    $angle = 0;
    foreach ($breakdownItems->values() as $index => $item) {
        $start = $angle;
        $angle += $breakdownTotal > 0 ? (($item['count'] / $breakdownTotal) * 360) : 0;
        $segments[] = $breakdownPalette[$index % count($breakdownPalette)] . ' ' . number_format($start, 2, '.', '') . 'deg ' . number_format($angle, 2, '.', '') . 'deg';
    }
    $breakdownChart = count($segments) > 0 ? 'conic-gradient(' . implode(', ', $segments) . ')' : 'conic-gradient(#eceef1 0deg 360deg)';
@endphp

<div class="relative min-h-screen overflow-hidden bg-[#e9f1f8] px-3 py-4 sm:px-5 lg:px-7">
    {{-- Glassmorphism background blobs --}}
    <div class="pointer-events-none absolute -top-32 left-10 h-80 w-80 rounded-full bg-sky-300/40 blur-3xl"></div>
    <div class="pointer-events-none absolute top-40 right-0 h-96 w-96 rounded-full bg-cyan-300/30 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-20 left-1/3 h-96 w-96 rounded-full bg-indigo-300/25 blur-3xl"></div>

    <div class="relative mx-auto max-w-[1600px] space-y-6">

        {{-- PAGE HEADER --}}
        <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-white/35 p-4 shadow-[0_24px_80px_rgba(15,23,42,0.12)] backdrop-blur-2xl">
            <div class="rounded-[1.75rem] border border-white/60 bg-white/35 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl sm:p-6">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-4 py-2 text-xs font-bold uppercase tracking-[0.22em] text-slate-700 shadow-sm backdrop-blur-xl">
                            <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_14px_rgba(14,165,233,0.75)]"></span>
                            {{ $dashboardLabel }}
                        </div>

                        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">
                            {{ $dashboardRole === \App\Models\User::ROLE_EXECUTIVE ? 'Executive Dashboard' : 'Dashboard' }}
                        </h1>

                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Showing activity from <span class="font-semibold text-slate-900">{{ $selectedRangeLabel }}</span>.
                        </p>
                    </div>

                    <form method="GET" class="flex flex-wrap items-center gap-3 rounded-[1.5rem] border border-white/60 bg-white/45 p-3 text-sm font-medium text-slate-700 shadow-sm backdrop-blur-xl">
                        <div class="flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-2 text-slate-600 backdrop-blur-xl">
                            <i class="fas fa-calendar-days text-sky-600"></i>
                            <span class="hidden text-sm font-semibold sm:inline">{{ $selectedRangeLabel }}</span>
                        </div>

                        <input
                            type="date"
                            name="start_date"
                            value="{{ $startDate->toDateString() }}"
                            class="rounded-xl border border-white/60 bg-white/50 px-3 py-2 text-sm text-slate-700 outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                        >

                        <span class="text-sm font-semibold text-slate-500">to</span>

                        <input
                            type="date"
                            name="end_date"
                            value="{{ $endDate->toDateString() }}"
                            class="rounded-xl border border-white/60 bg-white/50 px-3 py-2 text-sm text-slate-700 outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                        >

                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950/90 px-4 py-2 text-sm font-bold text-white shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                            Apply
                        </button>

                        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 py-2 text-sm font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                            Reset
                        </a>
                    </form>
                </div>
            </div>
        </div>

        {{-- METRIC CARDS --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($metricCards as $card)
                @if (! empty($card['url']))
                    <a href="{{ $card['url'] }}" class="group block overflow-hidden rounded-[1.45rem] border border-white/60 bg-white/35 p-4 shadow-[0_14px_40px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40 transition hover:-translate-y-0.5 hover:bg-white/45 hover:shadow-[0_20px_55px_rgba(15,23,42,0.13)]">
                @else
                    <article class="group overflow-hidden rounded-[1.45rem] border border-white/60 bg-white/35 p-4 shadow-[0_14px_40px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40 transition hover:-translate-y-0.5 hover:bg-white/45 hover:shadow-[0_20px_55px_rgba(15,23,42,0.13)]">
                @endif

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="max-w-[160px] text-sm font-semibold leading-5 text-slate-600">
                                {{ $card['label'] }}
                            </p>

                            <p class="mt-3 text-3xl font-bold leading-none tracking-tight text-slate-950">
                                {{ $card['value'] }}
                            </p>
                        </div>

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/20 bg-slate-950/90 text-base text-white shadow-[0_12px_30px_rgba(15,23,42,0.25)] backdrop-blur-xl transition group-hover:scale-105">
                            <i class="fas {{ $card['icon'] }}"></i>
                        </div>
                    </div>

                    <p class="mt-4 min-h-[36px] text-sm leading-6 text-slate-600">
                        {{ $card['delta'] }}
                    </p>

                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/50 shadow-inner">
                        <div class="h-full w-2/3 rounded-full bg-gradient-to-r from-slate-950 via-sky-600 to-cyan-400"></div>
                    </div>

                @if (! empty($card['url']))
                    </a>
                @else
                    </article>
                @endif
            @endforeach
        </section>

        {{-- CHARTS --}}
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
            <article class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-700">Overview</p>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $chartTitle }}</h2>
                    </div>

                    <div class="inline-flex items-center gap-3 rounded-full border border-white/60 bg-white/45 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl">
                        <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                        <span>{{ $chartMetricLabel }}</span>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-[56px_minmax(0,1fr)] gap-3">
                    <div class="flex h-[280px] flex-col justify-between pb-7 text-sm font-medium text-slate-500">
                        <span>{{ number_format($maxChartValue) }}</span>
                        <span>{{ number_format((int) round($maxChartValue * 0.75)) }}</span>
                        <span>{{ number_format((int) round($maxChartValue * 0.5)) }}</span>
                        <span>{{ number_format((int) round($maxChartValue * 0.25)) }}</span>
                        <span>0</span>
                    </div>

                    <div class="min-w-0">
                        <div class="relative h-[280px] overflow-hidden rounded-[1.4rem] border border-white/60 bg-white/35 p-2 shadow-inner backdrop-blur-xl">
                            <div class="absolute inset-4 grid grid-rows-4">
                                @for ($i = 0; $i < 4; $i++)
                                    <div class="border-b border-dashed border-slate-300/60"></div>
                                @endfor
                            </div>

                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-4 h-[calc(100%-2rem)] w-[calc(100%-2rem)]">
                                <defs>
                                    <linearGradient id="overview-fill" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#0ea5e9" stop-opacity="0.28"></stop>
                                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0"></stop>
                                    </linearGradient>
                                </defs>

                                <polygon points="{{ $areaPoints }}" fill="url(#overview-fill)"></polygon>
                                <polyline points="{{ $polylinePoints }}" fill="none" stroke="#0f172a" stroke-width="0.9" stroke-linecap="round" stroke-linejoin="round"></polyline>

                                @foreach ($chartSeries as $index => $point)
                                    @php
                                        $x = ($index / $pointCount) * 100;
                                        $y = 100 - (($point['count'] / $maxChartValue) * 100);
                                    @endphp
                                    <circle cx="{{ number_format($x, 2, '.', '') }}" cy="{{ number_format($y, 2, '.', '') }}" r="0.9" fill="#0ea5e9"></circle>
                                @endforeach
                            </svg>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2 text-xs font-semibold text-slate-500 sm:text-sm">
                            @foreach ($chartLabels as $point)
                                <span>{{ $point['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>

            <article class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-700">Breakdown</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $breakdownTitle }}</h2>
                </div>

                <div class="mt-8 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-1 2xl:grid-cols-[220px_minmax(0,1fr)]">
                    <div class="mx-auto flex h-[210px] w-[210px] items-center justify-center rounded-full border border-white/50 shadow-[inset_0_1px_0_rgba(255,255,255,0.8),0_18px_45px_rgba(15,23,42,0.12)] backdrop-blur-xl" style="background: {{ $breakdownChart }}">
                        <div class="flex h-[124px] w-[124px] items-center justify-center rounded-full border border-white/70 bg-white/60 shadow-sm backdrop-blur-2xl">
                            <div class="text-center">
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Total</p>
                                <p class="mt-1 text-3xl font-bold text-slate-950">{{ number_format($breakdownTotal) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @forelse ($breakdownItems as $index => $item)
                            <div class="grid grid-cols-[18px_minmax(0,1fr)_auto] items-center gap-4 rounded-2xl border border-white/60 bg-white/40 px-4 py-3 text-sm text-slate-700 shadow-sm backdrop-blur-xl">
                                <span class="h-4 w-4 rounded-full shadow-sm" style="background-color: {{ $breakdownPalette[$index % count($breakdownPalette)] }}"></span>
                                <span class="font-semibold">{{ $item['label'] }}</span>
                                <span class="font-bold text-slate-950">{{ number_format($item['count']) }}</span>
                            </div>
                        @empty
                            <p class="rounded-2xl border border-white/60 bg-white/40 px-4 py-5 text-sm text-slate-600 backdrop-blur-xl">
                                No breakdown data available.
                            </p>
                        @endforelse
                    </div>
                </div>
            </article>
        </section>

        {{-- TABLE / FEED AREA --}}
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_380px]">
            <article class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="border-b border-white/50 px-5 py-5">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-700">Primary View</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $primaryTitle }}</h2>
                </div>

                @if ($primaryType === 'event-performance')
                    <div class="overflow-x-auto p-3">
                        <table class="min-w-full border-separate border-spacing-y-3 text-left">
                            <thead>
                                <tr class="text-sm text-slate-600">
                                    <th class="px-4 py-3 font-bold">Event</th>
                                    @if ($dashboardRole === \App\Models\User::ROLE_EXECUTIVE)
                                        <th class="px-4 py-3 font-bold">Setup</th>
                                        <th class="px-4 py-3 font-bold">Registrations</th>
                                        <th class="px-4 py-3 font-bold">Race Day</th>
                                        <th class="px-4 py-3 font-bold">Status</th>
                                    @else
                                        <th class="px-4 py-3 font-bold">Registrations</th>
                                        <th class="px-4 py-3 font-bold">Results</th>
                                        <th class="px-4 py-3 font-bold">Completion Rate</th>
                                    @endif
                                </tr>
                            </thead>

                            <tbody>
                                @if ($dashboardRole === \App\Models\User::ROLE_EXECUTIVE)
                                    @forelse ($eventHealth as $event)
                                        @php
                                            $missingItems = collect([
                                                'Categories' => $event->categories_count === 0,
                                                'Checkpoints' => $event->checkpoints_count === 0,
                                                'Announcements' => $event->published_announcements_count === 0,
                                                'Results' => $event->checked_in_registrations_count > 0 && $event->race_results_count === 0,
                                            ])->filter()->keys();
                                        @endphp

                                        <tr class="text-sm text-slate-700">
                                            <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                                <p class="font-bold text-slate-950">{{ $event->title }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ optional($event->event_date)->format('M d, Y') ?? 'TBD' }}</p>
                                            </td>
                                            <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                                <div class="space-y-1 text-xs font-medium text-slate-500">
                                                    <p>{{ number_format($event->categories_count) }} categories</p>
                                                    <p>{{ number_format($event->checkpoints_count) }} checkpoints</p>
                                                    <p>{{ number_format($event->published_announcements_count) }} announcements</p>
                                                </div>
                                            </td>
                                            <td class="border-y border-white/60 bg-white/40 px-4 py-4 font-bold text-slate-950 backdrop-blur-xl">{{ number_format($event->registrations_count) }}</td>
                                            <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                                <div class="space-y-1 text-xs font-medium text-slate-500">
                                                    <p>{{ number_format($event->checked_in_registrations_count) }} checked in</p>
                                                    <p>{{ number_format($event->race_results_count) }} results</p>
                                                </div>
                                            </td>
                                            <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                                @if ($missingItems->isEmpty())
                                                    <span class="inline-flex rounded-full border border-emerald-200/70 bg-emerald-100/70 px-3 py-1.5 text-xs font-bold text-emerald-700 backdrop-blur-xl">Healthy</span>
                                                @else
                                                    <span class="inline-flex rounded-full border border-amber-200/70 bg-amber-100/70 px-3 py-1.5 text-xs font-bold text-amber-700 backdrop-blur-xl">
                                                        Needs {{ $missingItems->take(2)->implode(', ') }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">
                                                No upcoming events available for health review.
                                            </td>
                                        </tr>
                                    @endforelse
                                @else
                                    @forelse ($eventPerformance as $event)
                                        @php
                                            $completionRate = $event->registrations_count > 0 ? round(($event->race_results_count / $event->registrations_count) * 100, 1) : 0;
                                        @endphp

                                        <tr class="text-sm text-slate-700">
                                            <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/40 px-4 py-4 font-bold text-slate-950 backdrop-blur-xl">{{ $event->title }}</td>
                                            <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ $event->registrations_count }}</td>
                                            <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ $event->race_results_count }}</td>
                                            <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/40 px-4 py-4 font-bold text-slate-950 backdrop-blur-xl">{{ $completionRate }}%</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-5 py-12 text-center text-sm text-slate-500">
                                                No event performance data available.
                                            </td>
                                        </tr>
                                    @endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                @elseif ($primaryType === 'feedback-feed')
                    <div class="space-y-3 px-5 py-5">
                        @forelse ($recentFeedback as $feedback)
                            <div class="rounded-[1.35rem] border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl transition hover:bg-white/55">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-bold text-slate-950">{{ $feedback->user?->name ?? 'Participant' }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $feedback->event?->title ?? 'General Feedback' }}</p>
                                    </div>

                                    <span class="rounded-full px-3 py-1.5 text-xs font-bold backdrop-blur-xl {{ $feedback->is_flagged ? 'bg-amber-100/80 text-amber-800 border border-amber-200/70' : 'bg-emerald-100/80 text-emerald-800 border border-emerald-200/70' }}">
                                        {{ $feedback->is_flagged ? 'Flagged' : 'Visible' }}
                                    </span>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ \Illuminate\Support\Str::limit($feedback->content, 150) }}</p>

                                <a href="{{ route('admin.content.community-posts.show', $feedback) }}" class="mt-3 inline-flex items-center text-sm font-bold text-sky-700 transition hover:text-sky-900">
                                    Open item
                                    <i class="fas fa-arrow-right ml-2 text-xs"></i>
                                </a>
                            </div>
                        @empty
                            <div class="rounded-[1.35rem] border border-white/60 bg-white/40 px-5 py-8 text-sm text-slate-500 backdrop-blur-xl">
                                No feedback queue available.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="overflow-x-auto p-3">
                        <table class="min-w-full border-separate border-spacing-y-3 text-left">
                            <thead>
                                <tr class="text-sm text-slate-600">
                                    <th class="px-4 py-3 font-bold">Event Name</th>
                                    <th class="px-4 py-3 font-bold">Date</th>
                                    <th class="px-4 py-3 font-bold">Location</th>
                                    <th class="px-4 py-3 font-bold">Status</th>
                                    <th class="px-4 py-3 font-bold">Registrations</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($recentEvents as $event)
                                    <tr class="text-sm text-slate-700">
                                        <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/40 px-4 py-4 font-bold text-slate-950 backdrop-blur-xl">{{ $event->title }}</td>
                                        <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ optional($event->event_date)->format('M d, Y') ?? 'TBD' }}</td>
                                        <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ $event->venue ?: 'Location TBD' }}</td>
                                        <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ str($event->effective_status ?? 'scheduled')->replace('_', ' ')->title() }}</td>
                                        <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/40 px-4 py-4 font-bold text-slate-950 backdrop-blur-xl">{{ $event->registrations_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">
                                            No recent events available.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>

            {{-- SECONDARY PANEL --}}
            <article class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="border-b border-white/50 px-5 py-5">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-700">Activity</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $secondaryTitle }}</h2>
                </div>

                @if ($secondaryType === 'feedback-summary')
                    <div class="space-y-4 px-5 py-5">
                        <div class="rounded-[1.35rem] border border-rose-200/60 bg-rose-100/45 px-4 py-4 backdrop-blur-xl">
                            <p class="text-sm font-bold text-slate-950">Complaint Signals</p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ number_format($feedbackInsights['complaints']) }} posts contain complaint-oriented language.</p>
                        </div>

                        <div class="rounded-[1.35rem] border border-sky-200/60 bg-sky-100/45 px-4 py-4 backdrop-blur-xl">
                            <p class="text-sm font-bold text-slate-950">Suggestions</p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ number_format($feedbackInsights['suggestions']) }} posts contain improvement suggestions.</p>
                        </div>

                        <div class="rounded-[1.35rem] border border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                            <p class="text-sm font-bold text-slate-950">Ratings</p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Structured ratings are not yet stored in the current schema.</p>
                        </div>
                    </div>
                @elseif ($secondaryType === 'moderation-activity')
                    <div class="space-y-3 px-5 py-5">
                        @forelse ($moderationActivities as $activity)
                            <div class="flex items-start gap-4 rounded-[1.35rem] border border-white/60 bg-white/40 px-4 py-4 shadow-sm backdrop-blur-xl transition hover:bg-white/55">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950/90 text-sm font-bold text-white shadow-md">
                                    {{ $activity->user?->initials() ?: 'AD' }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-slate-950">{{ $activity->user?->name ?? 'System' }}</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $activity->action }}</p>
                                </div>

                                <p class="shrink-0 text-xs font-semibold text-slate-500">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <div class="rounded-[1.35rem] border border-white/60 bg-white/40 px-5 py-8 text-sm text-slate-500 backdrop-blur-xl">
                                No moderation actions logged yet.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="space-y-3 px-5 py-5">
                        @forelse ($recentActivities as $activity)
                            <div class="flex items-start gap-4 rounded-[1.35rem] border border-white/60 bg-white/40 px-4 py-4 shadow-sm backdrop-blur-xl transition hover:bg-white/55">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950/90 text-sm font-bold text-white shadow-md">
                                    {{ $activity->user?->initials() ?: 'AD' }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-slate-950">{{ $activity->user?->name ?? 'System' }}</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $activity->action }}</p>
                                </div>

                                <p class="shrink-0 text-xs font-semibold text-slate-500">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <div class="rounded-[1.35rem] border border-white/60 bg-white/40 px-5 py-8 text-sm text-slate-500 backdrop-blur-xl">
                                No recent activity yet.
                            </div>
                        @endforelse
                    </div>
                @endif
            </article>
        </section>
    </div>
</div>
@endsection