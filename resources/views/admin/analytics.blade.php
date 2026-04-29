@extends('admin.layouts.app')

@section('title', $pageTitle ?? 'Analytics Dashboard')

@section('content')
<div class="mb-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <h1 class="text-2xl font-semibold text-gray-800">{{ $pageTitle ?? 'Analytics Dashboard' }}</h1>
        @if(in_array(($pageTitle ?? ''), ['Reports', 'Analytics Dashboard'], true))
            <div class="flex flex-wrap gap-3">
                @if(auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EXECUTIVE]))
                    <a href="{{ route('admin.reports.export', 'users') }}" class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 transition hover:bg-gray-50">
                        <i class="fas fa-download mr-2 text-xs"></i>Export User Data
                    </a>
                @endif
                <a href="{{ route('admin.reports.export', 'events') }}" class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-800 transition hover:bg-gray-50">
                    <i class="fas fa-download mr-2 text-xs"></i>Export Event Data
                </a>
                <a href="{{ route('admin.reports.export', 'summary') }}" class="inline-flex items-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800">
                    <i class="fas fa-file-arrow-down mr-2 text-xs"></i>Export Summary
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Analytics Overview -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- User Growth Chart -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">User Growth (Last 90 Days)</h3>
        </div>
        <div class="p-6">
            <div class="h-64 flex items-end justify-between space-x-2">
                @if($userGrowth->isNotEmpty())
                    @foreach($userGrowth as $growth)
                        <div class="flex-1 bg-blue-500 hover:bg-blue-600 transition-colors relative group" 
                             style="height: {{ ($growth->count / $userGrowth->max('count')) * 100 }}%">
                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                {{ $growth->date }}: {{ $growth->count }} users
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="w-full text-center text-gray-500">
                        No user growth data available
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Daily Active Users -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Daily Active Users (Last 30 Days)</h3>
        </div>
        <div class="p-6">
            <div class="h-64 flex items-end justify-between space-x-2">
                @if($dailyActiveUsers->isNotEmpty())
                    @foreach($dailyActiveUsers as $active)
                        <div class="flex-1 bg-green-500 hover:bg-green-600 transition-colors relative group" 
                             style="height: {{ ($active->count / $dailyActiveUsers->max('count')) * 100 }}%">
                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                {{ $active->date }}: {{ $active->count }} users
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="w-full text-center text-gray-500">
                        No active user data available
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Event Performance -->
<div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Event Performance</h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrations</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Results</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($eventPerformance as $event)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $event['name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $event['registrations'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $event['results'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="text-sm text-gray-900 mr-2">{{ number_format($event['completion_rate'], 1) }}%</div>
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($event['completion_rate'], 100) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($event['completion_rate'] >= 80)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Excellent
                                    </span>
                                @elseif($event['completion_rate'] >= 60)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Good
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Poor
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No event performance data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Users Growth</p>
                <p class="text-2xl font-semibold text-gray-800">
                    @if($userGrowth->count() > 1)
                        {{ $userGrowth->last()->count - $userGrowth->first()->count }}
                    @else
                        0
                    @endif
                </p>
                <p class="text-xs text-gray-500">Last 90 days</p>
            </div>
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Avg. Daily Active Users</p>
                <p class="text-2xl font-semibold text-gray-800">
                    {{ $dailyActiveUsers->avg('count') ? round($dailyActiveUsers->avg('count')) : 0 }}
                </p>
                <p class="text-xs text-gray-500">Last 30 days</p>
            </div>
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Avg. Completion Rate</p>
                <p class="text-2xl font-semibold text-gray-800">
                    {{ $eventPerformance->avg('completion_rate') ? number_format($eventPerformance->avg('completion_rate'), 1) : 0 }}%
                </p>
                <p class="text-xs text-gray-500">All events</p>
            </div>
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-trophy"></i>
            </div>
        </div>
    </div>
</div>

@endsection
