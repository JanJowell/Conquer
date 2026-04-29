@extends('admin.layouts.app')

@section('title', 'Security Dashboard')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Security Dashboard</h1>
</div>

<!-- Security Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Suspicious Activities</p>
                <p class="text-2xl font-semibold text-gray-800">{{ $suspiciousActivities->count() }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-ban text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Failed Logins</p>
                <p class="text-2xl font-semibold text-gray-800">{{ count($failedLogins) }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-gavel text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Banned IPs</p>
                <p class="text-2xl font-semibold text-gray-800">{{ $bannedIPs->count() }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-history text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Recent Activities</p>
                <p class="text-2xl font-semibold text-gray-800">{{ $recentActivities->count() }}</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Suspicious Activities -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Suspicious Activities (Last 24h)</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @forelse($suspiciousActivities as $activity)
                    <div class="flex items-center justify-between py-3 border-b last:border-b-0">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white text-sm">
                                {{ strtoupper(substr($activity->user->name, 0, 1)) }}
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">{{ $activity->user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $activity->count }} actions from {{ $activity->ips }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <a href="{{ route('admin.security.activity-logs', ['user_id' => $activity->user_id]) }}" 
                               class="text-blue-600 hover:text-blue-900 text-sm">
                                View Details
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No suspicious activities detected</p>
                @endforelse
            </div>
        </div>
    </div>
    
    <!-- Recent Security Activities -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Recent Admin Activities</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @forelse($recentActivities->take(10) as $activity)
                    <div class="flex items-center justify-between py-3 border-b last:border-b-0">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm">
                                {{ strtoupper(substr($activity->user->name, 0, 1)) }}
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">{{ $activity->user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $activity->action }}</p>
                                <p class="text-xs text-gray-400">{{ $activity->ip_address }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No recent activities</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Security Actions -->
<div class="bg-white rounded-lg shadow mt-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Security Actions</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('admin.security.banned-ips') }}" class="block text-center bg-red-600 text-white py-3 px-4 rounded hover:bg-red-700 transition-colors">
                <i class="fas fa-ban mr-2"></i>Manage Banned IPs
            </a>
            <a href="{{ route('admin.security.activity-logs') }}" class="block text-center bg-blue-600 text-white py-3 px-4 rounded hover:bg-blue-700 transition-colors">
                <i class="fas fa-list mr-2"></i>View Activity Logs
            </a>
            <form method="POST" action="{{ route('admin.security.enforce-2fa') }}" 
                  onsubmit="return confirm('Enforce 2FA for all admin users?')" class="inline">
                @csrf
                <button type="submit" class="w-full bg-purple-600 text-white py-3 px-4 rounded hover:bg-purple-700 transition-colors">
                    <i class="fas fa-shield-alt mr-2"></i>Enforce 2FA
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Failed Login Attempts -->
@if(!empty($failedLogins))
<div class="bg-white rounded-lg shadow mt-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Recent Failed Login Attempts</h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Attempt</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($failedLogins as $ip => $data)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $ip }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $data['count'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $data['last_attempt'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form method="POST" action="{{ route('admin.security.ban-ip') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="ip_address" value="{{ $ip }}">
                                    <input type="hidden" name="reason" value="Multiple failed login attempts">
                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Ban IP">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
