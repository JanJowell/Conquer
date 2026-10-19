@extends('admin.layouts.app')

@section('title', $pageTitle ?? 'Analytics Dashboard')

@section('content')
@php
    $eventScopeLabel = auth()->user()->managesAssignedEventsOnly() ? 'Assigned events' : 'All events';
@endphp

<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1400px] space-y-6">

        {{-- PAGE HEADER --}}
        <div class="flex flex-col gap-4 rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.15)] lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Performance</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-700">{{ $pageTitle ?? 'Analytics Dashboard' }}</h1>
                <p class="mt-1 text-sm text-slate-400">Review event registrations, result completion, and operational reporting.</p>
            </div>

            @if(in_array(($pageTitle ?? ''), ['Reports', 'Analytics Dashboard', 'Analytics & Reports'], true))
                <div class="flex flex-wrap gap-2">
                    @if(auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EXECUTIVE]))
                        <a href="{{ route('admin.reports.export', 'users') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                            <i class="fas fa-download mr-2 text-xs"></i>Export Users
                        </a>
                    @endif
                    <a href="{{ route('admin.reports.export', 'events') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                        <i class="fas fa-download mr-2 text-xs"></i>Export Events
                    </a>
                    <a href="{{ route('admin.reports.export', 'summary') }}" class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        <i class="fas fa-file-arrow-down mr-2 text-xs"></i>Export Summary
                    </a>
                </div>
            @endif
        </div>

        {{-- SUMMARY CARDS --}}
        <section class="grid grid-cols-1 gap-4 {{ $canViewUserAnalytics ? 'md:grid-cols-3' : 'md:grid-cols-1' }}">
            @if($canViewUserAnalytics)
                <div class="flex items-start gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-400">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-400">New Users</p>
                        <p class="mt-1 text-2xl font-bold text-slate-700">{{ number_format($userGrowth->sum('count')) }}</p>
                        <p class="mt-1 text-xs text-slate-300">Last 90 days</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-400">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-400">Avg. Daily Active Users</p>
                        <p class="mt-1 text-2xl font-bold text-slate-700">{{ $dailyActiveUsers->isNotEmpty() ? round($dailyActiveUsers->avg('count')) : 0 }}</p>
                        <p class="mt-1 text-xs text-slate-300">Last 30 days</p>
                    </div>
                </div>
            @endif

            <div class="flex items-start gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-400">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-slate-400">Avg. Completion Rate</p>
                    <p class="mt-1 text-2xl font-bold text-slate-700">{{ $eventPerformance->avg('completion_rate') ? number_format($eventPerformance->avg('completion_rate'), 1) : 0 }}%</p>
                    <p class="mt-1 text-xs text-slate-300">{{ $eventScopeLabel }}</p>
                </div>
            </div>
        </section>

        {{-- CHARTS --}}
        @if($canViewUserAnalytics)
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <article class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <h2 class="text-base font-semibold text-slate-700">User Growth</h2>
                    <p class="mt-1 text-sm text-slate-300">Last 90 days</p>

                    <div class="mt-5 flex h-56 items-end justify-between gap-1 rounded-2xl bg-[#f7f8fc] p-3">
                        @if($userGrowth->sum('count') > 0)
                            @foreach($userGrowth as $growth)
                                <div class="group relative flex-1 rounded-full bg-indigo-300 transition hover:bg-indigo-400"
                                     style="height: {{ data_get($growth, 'count') > 0 ? max((data_get($growth, 'count') / $userGrowth->max('count')) * 100, 4) : 0 }}%">
                                    <div class="absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-full bg-slate-600 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100">
                                        {{ data_get($growth, 'date') }}: {{ data_get($growth, 'count') }} users
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="flex w-full items-center justify-center text-sm text-slate-300">No user growth data available</div>
                        @endif
                    </div>
                </article>

                <article class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <h2 class="text-base font-semibold text-slate-700">Daily Active Users</h2>
                    <p class="mt-1 text-sm text-slate-300">Last 30 days</p>

                    <div class="mt-5 flex h-56 items-end justify-between gap-1 rounded-2xl bg-[#f7f8fc] p-3">
                        @if($dailyActiveUsers->sum('count') > 0)
                            @foreach($dailyActiveUsers as $active)
                                <div class="group relative flex-1 rounded-full bg-emerald-300 transition hover:bg-emerald-400"
                                     style="height: {{ data_get($active, 'count') > 0 ? max((data_get($active, 'count') / $dailyActiveUsers->max('count')) * 100, 4) : 0 }}%">
                                    <div class="absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-full bg-slate-600 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100">
                                        {{ data_get($active, 'date') }}: {{ data_get($active, 'count') }} users
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="flex w-full items-center justify-center text-sm text-slate-300">No active user data available</div>
                        @endif
                    </div>
                </article>
            </section>
        @endif

        {{-- EVENT PERFORMANCE TABLE --}}
        <article class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="border-b border-slate-50 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-700">Event Performance</h2>
                <p class="mt-1 text-sm text-slate-300">{{ $eventScopeLabel }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-slate-400">
                            <th class="px-6 py-3 font-medium">Event</th>
                            <th class="px-6 py-3 font-medium">Registrations</th>
                            <th class="px-6 py-3 font-medium">Results</th>
                            <th class="px-6 py-3 font-medium">Completion Rate</th>
                            <th class="px-6 py-3 font-medium">Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eventPerformance as $event)
                            <tr class="transition hover:bg-[#f7f8fc]">
                                <td class="px-6 py-4 font-medium text-slate-700">{{ $event['name'] }}</td>
                                <td class="px-6 py-4 text-slate-500">{{ $event['registrations'] }}</td>
                                <td class="px-6 py-4 text-slate-500">{{ $event['results'] }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="w-12 text-slate-600">{{ number_format($event['completion_rate'], 1) }}%</span>
                                        <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-indigo-300" style="width: {{ min($event['completion_rate'], 100) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($event['completion_rate'] >= 80)
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-500">Excellent</span>
                                    @elseif($event['completion_rate'] >= 60)
                                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-500">Good</span>
                                    @else
                                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-400">Poor</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-400">No event performance data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</div>
@endsection
