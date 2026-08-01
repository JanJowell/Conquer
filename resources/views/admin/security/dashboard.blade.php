@extends('admin.layouts.app')

@section('title', 'Settings')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">System Settings</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Security Dashboard</h1>
            <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Monitor admin activity, login risk, access logs, and security controls.</p>
        </div>

        <a href="{{ route('admin.security.activity-logs') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/60 bg-white/45 px-5 py-3 text-sm font-semibold text-[#151b26] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
            <i class="fas fa-list mr-2 text-xs"></i>
            Activity Logs
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-[#6d7685]">Suspicious Activities</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($suspiciousActivities->count()) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-rose-200/70 bg-rose-100/70 text-rose-700 shadow-sm backdrop-blur-xl">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-[#6d7685]">Failed Logins</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format(count($failedLogins)) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-200/70 bg-amber-100/70 text-amber-700 shadow-sm backdrop-blur-xl">
                    <i class="fas fa-ban"></i>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-[#6d7685]">Banned IPs</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($bannedIPs->count()) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-violet-200/70 bg-violet-100/70 text-violet-700 shadow-sm backdrop-blur-xl">
                    <i class="fas fa-gavel"></i>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-[#6d7685]">Recent Activities</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($recentActivities->count()) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-sky-200/70 bg-sky-100/70 text-sky-700 shadow-sm backdrop-blur-xl">
                    <i class="fas fa-history"></i>
                </div>
            </div>
        </div>
    </div>

    <section class="rounded-3xl border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Controls</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight text-[#151b26]">Security Actions</h2>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <a href="{{ route('admin.security.banned-ips') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-[#151b26] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                <i class="fas fa-ban mr-2 text-rose-600"></i>
                Manage Banned IPs
            </a>
            <a href="{{ route('admin.security.activity-logs') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-[#151b26] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                <i class="fas fa-list mr-2 text-sky-600"></i>
                View Activity Logs
            </a>
            <a href="{{ route('admin.security.login-monitoring') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-[#151b26] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                <i class="fas fa-sign-in-alt mr-2 text-amber-600"></i>
                Login Monitoring
            </a>
            <a href="{{ route('admin.security.data-access-logs') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-[#151b26] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                <i class="fas fa-database mr-2 text-emerald-600"></i>
                Data Access Logs
            </a>
            <form method="POST" action="{{ route('admin.security.enforce-2fa') }}" onsubmit="return confirm('Enforce 2FA for all admin users?')">
                @csrf
                <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-[#151b26] px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                    <i class="fas fa-shield-alt mr-2"></i>
                    Enforce 2FA
                </button>
            </form>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-3xl border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="border-b border-white/60 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Last 24 Hours</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight text-[#151b26]">Suspicious Activities</h2>
            </div>

            <div class="space-y-2 p-4">
                @forelse($suspiciousActivities as $activity)
                    <div class="flex flex-col gap-3 rounded-2xl border border-white/60 bg-white/45 p-4 shadow-sm backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-sm font-semibold text-white shadow-sm">
                                {{ strtoupper(substr($activity->user?->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[#151b26]">{{ $activity->user?->name ?? 'Deleted user' }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">{{ $activity->count }} actions from {{ $activity->ips }}</p>
                            </div>
                        </div>
                        <a href="{{ route('admin.security.activity-logs', ['user_id' => $activity->user_id]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-semibold text-[#315fa8] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                            View Details
                        </a>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/70 bg-white/35 px-5 py-8 text-center shadow-sm backdrop-blur-xl">
                        <p class="text-sm font-semibold text-[#202733]">No suspicious activities detected</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="border-b border-white/60 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Audit Trail</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight text-[#151b26]">Recent Admin Activities</h2>
            </div>

            <div class="space-y-2 p-4">
                @forelse($recentActivities->take(10) as $activity)
                    <div class="flex flex-col gap-3 rounded-2xl border border-white/60 bg-white/45 p-4 shadow-sm backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-sm font-semibold text-white shadow-sm">
                                {{ strtoupper(substr($activity->user?->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[#151b26]">{{ $activity->user?->name ?? 'Deleted user' }}</p>
                                <p class="mt-1 truncate text-xs text-[#6d7685]">{{ $activity->action }}</p>
                                <p class="mt-1 text-xs text-[#9aa3af]">{{ $activity->ip_address }}</p>
                            </div>
                        </div>
                        <p class="shrink-0 text-xs font-medium text-[#6d7685]">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/70 bg-white/35 px-5 py-8 text-center shadow-sm backdrop-blur-xl">
                        <p class="text-sm font-semibold text-[#202733]">No recent activities</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @if(!empty($failedLogins))
        <section class="overflow-hidden rounded-3xl border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
            <div class="border-b border-white/60 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Login Risk</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight text-[#151b26]">Recent Failed Login Attempts</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-y-2 px-3 py-2">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-4 py-3">IP Address</th>
                            <th class="px-4 py-3">Attempts</th>
                            <th class="px-4 py-3">Last Attempt</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-[#202733]">
                        @foreach($failedLogins as $ip => $data)
                            <tr>
                                <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/45 px-4 py-4 font-semibold backdrop-blur-xl">{{ $ip }}</td>
                                <td class="border-y border-white/60 bg-white/45 px-4 py-4 backdrop-blur-xl">{{ $data['count'] }}</td>
                                <td class="border-y border-white/60 bg-white/45 px-4 py-4 text-[#6d7685] backdrop-blur-xl">{{ $data['last_attempt'] }}</td>
                                <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/45 px-4 py-4 text-right backdrop-blur-xl">
                                    <form method="POST" action="{{ route('admin.security.ban-ip') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="ip_address" value="{{ $ip }}">
                                        <input type="hidden" name="reason" value="Multiple failed login attempts">
                                        <input type="hidden" name="permanent" value="1">
                                        <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 shadow-sm transition hover:bg-rose-100" title="Ban IP">
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
@endsection
