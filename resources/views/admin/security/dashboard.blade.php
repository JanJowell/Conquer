@extends('admin.layouts.app')

@section('title', 'Settings')

@section('content')
<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1400px] space-y-6">

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">System Settings</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-700">Security Dashboard</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-400">Monitor admin activity, login risk, access logs, and security controls.</p>
            </div>

            <a href="{{ route('admin.security.activity-logs') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                <i class="fas fa-list mr-2 text-xs"></i>
                Activity Logs
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center justify-between gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <div>
                    <p class="text-sm text-slate-400">Suspicious Activities</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($suspiciousActivities->count()) }}</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-400">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <div>
                    <p class="text-sm text-slate-400">Failed Logins</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format(count($failedLogins)) }}</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-400">
                    <i class="fas fa-ban"></i>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <div>
                    <p class="text-sm text-slate-400">Banned IPs</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($bannedIPs->count()) }}</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-violet-50 text-violet-400">
                    <i class="fas fa-gavel"></i>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <div>
                    <p class="text-sm text-slate-400">Recent Activities</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($recentActivities->count()) }}</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-sky-400">
                    <i class="fas fa-history"></i>
                </div>
            </div>
        </div>

        <section class="rounded-[1.75rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="mb-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Controls</p>
                <h2 class="mt-1 text-lg font-bold text-slate-700">Security Actions</h2>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <a href="{{ route('admin.security.banned-ips') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                    <i class="fas fa-ban mr-2 text-rose-400"></i>
                    Manage Banned IPs
                </a>
                <a href="{{ route('admin.security.activity-logs') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                    <i class="fas fa-list mr-2 text-sky-400"></i>
                    View Activity Logs
                </a>
                <a href="{{ route('admin.security.login-monitoring') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                    <i class="fas fa-sign-in-alt mr-2 text-amber-400"></i>
                    Login Monitoring
                </a>
                <a href="{{ route('admin.security.data-access-logs') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                    <i class="fas fa-database mr-2 text-emerald-400"></i>
                    Data Access Logs
                </a>
                <form method="POST" action="{{ route('admin.security.enforce-2fa') }}" onsubmit="return confirm('Enforce 2FA for all admin users?')">
                    @csrf
                    <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-indigo-400 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Enforce 2FA
                    </button>
                </form>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="border-b border-slate-50 px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Last 24 Hours</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-700">Suspicious Activities</h2>
                </div>

                <div class="space-y-2 p-4">
                    @forelse($suspiciousActivities as $activity)
                        <div class="flex flex-col gap-3 rounded-2xl bg-[#f7f8fc] p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(148,163,184,0.18)] sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-rose-400 text-sm font-semibold text-white">
                                    {{ strtoupper(substr($activity->user?->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-700">{{ $activity->user?->name ?? 'Deleted user' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $activity->count }} actions from {{ $activity->ips }}</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.security.activity-logs', ['user_id' => $activity->user_id]) }}" class="inline-flex h-9 items-center justify-center rounded-full bg-indigo-50 px-4 text-xs font-semibold text-indigo-400 transition hover:bg-indigo-100">
                                View Details
                            </a>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-[#f7f8fc] px-5 py-8 text-center">
                            <p class="text-sm font-semibold text-slate-600">No suspicious activities detected</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="border-b border-slate-50 px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Audit Trail</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-700">Recent Admin Activities</h2>
                </div>

                <div class="space-y-2 p-4">
                    @forelse($recentActivities->take(10) as $activity)
                        <div class="flex flex-col gap-3 rounded-2xl bg-[#f7f8fc] p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(148,163,184,0.18)] sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-sky-400 text-sm font-semibold text-white">
                                    {{ strtoupper(substr($activity->user?->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-700">{{ $activity->user?->name ?? 'Deleted user' }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-400">{{ $activity->action }}</p>
                                    <p class="mt-1 text-xs text-slate-300">{{ $activity->ip_address }}</p>
                                </div>
                            </div>
                            <p class="shrink-0 text-xs text-slate-400">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-[#f7f8fc] px-5 py-8 text-center">
                            <p class="text-sm font-semibold text-slate-600">No recent activities</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        @if(!empty($failedLogins))
            <section class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                <div class="border-b border-slate-50 px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Login Risk</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-700">Recent Failed Login Attempts</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                <th class="px-6 py-3">IP Address</th>
                                <th class="px-6 py-3">Attempts</th>
                                <th class="px-6 py-3">Last Attempt</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failedLogins as $ip => $data)
                                <tr class="transition hover:bg-[#f7f8fc]">
                                    <td class="px-6 py-4 font-medium text-slate-700">{{ $ip }}</td>
                                    <td class="px-6 py-4 text-slate-600">{{ $data['count'] }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $data['last_attempt'] }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <form method="POST" action="{{ route('admin.security.ban-ip') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $ip }}">
                                            <input type="hidden" name="reason" value="Multiple failed login attempts">
                                            <input type="hidden" name="permanent" value="1">
                                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-rose-50 text-rose-400 transition hover:bg-rose-100" title="Ban IP">
                                                <i class="fas fa-ban text-xs"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>
@endsection