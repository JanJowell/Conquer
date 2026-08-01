@extends('admin.layouts.app')

@section('title', $pageTitle ?? 'Analytics Dashboard')

@section('content')
@php
    $eventScopeLabel = auth()->user()->managesAssignedEventsOnly() ? 'Assigned events' : 'All events';
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Performance</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $pageTitle ?? 'Analytics Dashboard' }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Review event registrations, result completion, and operational reporting.</p>
        </div>

        @if(in_array(($pageTitle ?? ''), ['Reports', 'Analytics Dashboard'], true))
            <div class="flex flex-wrap gap-3">
                @if(auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EXECUTIVE]))
                    <a href="{{ route('admin.reports.export', 'users') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                        <i class="fas fa-download mr-2 text-xs"></i>Export User Data
                    </a>
                @endif
                <a href="{{ route('admin.reports.export', 'events') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    <i class="fas fa-download mr-2 text-xs"></i>Export Event Data
                </a>
                <a href="{{ route('admin.reports.export', 'summary') }}" class="inline-flex items-center justify-center rounded-xl bg-[#151b26] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    <i class="fas fa-file-arrow-down mr-2 text-xs"></i>Export Summary
                </a>
            </div>
        @endif
    </div>

    @if($canViewUserAnalytics)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
                <div class="border-b border-[#eef1f4] p-6">
                    <h3 class="text-lg font-semibold text-[#151b26]">User Growth</h3>
                    <p class="mt-1 text-sm text-[#6d7685]">Last 90 days</p>
                </div>
                <div class="p-6">
                    <div class="flex h-64 items-end justify-between gap-2">
                        @if($userGrowth->sum('count') > 0)
                            @foreach($userGrowth as $growth)
                                <div class="group relative flex-1 bg-[#315fa8] transition hover:bg-[#244c8a]"
                                     style="height: {{ data_get($growth, 'count') > 0 ? max((data_get($growth, 'count') / $userGrowth->max('count')) * 100, 2) : 0 }}%">
                                    <div class="absolute bottom-full left-1/2 mb-2 -translate-x-1/2 rounded bg-[#151b26] px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 whitespace-nowrap">
                                        {{ data_get($growth, 'date') }}: {{ data_get($growth, 'count') }} users
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="flex w-full items-center justify-center text-sm text-[#6d7685]">
                                No user growth data available
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
                <div class="border-b border-[#eef1f4] p-6">
                    <h3 class="text-lg font-semibold text-[#151b26]">Daily Active Users</h3>
                    <p class="mt-1 text-sm text-[#6d7685]">Last 30 days</p>
                </div>
                <div class="p-6">
                    <div class="flex h-64 items-end justify-between gap-2">
                        @if($dailyActiveUsers->sum('count') > 0)
                            @foreach($dailyActiveUsers as $active)
                                <div class="group relative flex-1 bg-emerald-500 transition hover:bg-emerald-600"
                                     style="height: {{ data_get($active, 'count') > 0 ? max((data_get($active, 'count') / $dailyActiveUsers->max('count')) * 100, 2) : 0 }}%">
                                    <div class="absolute bottom-full left-1/2 mb-2 -translate-x-1/2 rounded bg-[#151b26] px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 whitespace-nowrap">
                                        {{ data_get($active, 'date') }}: {{ data_get($active, 'count') }} users
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="flex w-full items-center justify-center text-sm text-[#6d7685]">
                                No active user data available
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] p-6">
            <h3 class="text-lg font-semibold text-[#151b26]">Event Performance</h3>
            <p class="mt-1 text-sm text-[#6d7685]">{{ $eventScopeLabel }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#eef1f4]">
                <thead class="bg-[#fafbfc]">
                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                        <th class="px-6 py-4">Event</th>
                        <th class="px-6 py-4">Registrations</th>
                        <th class="px-6 py-4">Results</th>
                        <th class="px-6 py-4">Completion Rate</th>
                        <th class="px-6 py-4">Performance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                    @forelse($eventPerformance as $event)
                        <tr>
                            <td class="px-6 py-5 font-semibold text-[#151b26]">{{ $event['name'] }}</td>
                            <td class="px-6 py-5">{{ $event['registrations'] }}</td>
                            <td class="px-6 py-5">{{ $event['results'] }}</td>
                            <td class="px-6 py-5">
                                <div class="flex items-center">
                                    <div class="mr-3 min-w-12 text-sm text-[#151b26]">{{ number_format($event['completion_rate'], 1) }}%</div>
                                    <div class="h-2 w-20 rounded-full bg-[#eef1f4]">
                                        <div class="h-2 rounded-full bg-[#315fa8]" style="width: {{ min($event['completion_rate'], 100) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                @if($event['completion_rate'] >= 80)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        Excellent
                                    </span>
                                @elseif($event['completion_rate'] >= 60)
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                        Good
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                                        Poor
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-[#6d7685]">No event performance data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 {{ $canViewUserAnalytics ? 'md:grid-cols-3' : 'md:grid-cols-1' }}">
        @if($canViewUserAnalytics)
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-[#6d7685]">New Users</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($userGrowth->sum('count')) }}</p>
                        <p class="mt-1 text-xs text-[#6d7685]">Last 90 days</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-50 text-[#315fa8]">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-[#6d7685]">Avg. Daily Active Users</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $dailyActiveUsers->isNotEmpty() ? round($dailyActiveUsers->avg('count')) : 0 }}</p>
                        <p class="mt-1 text-xs text-[#6d7685]">Last 30 days</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-[#6d7685]">Avg. Completion Rate</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $eventPerformance->avg('completion_rate') ? number_format($eventPerformance->avg('completion_rate'), 1) : 0 }}%</p>
                    <p class="mt-1 text-xs text-[#6d7685]">{{ $eventScopeLabel }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <i class="fas fa-trophy"></i>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
