@extends('admin.layouts.app')

@section('title', 'Login Monitoring')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Security</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Login Monitoring</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Review recent sign-ins and IP addresses with unusually high admin activity.</p>
    </div>

    <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
        Back to security
    </a>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <div class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-[#6d7685]">Tracked Login Attempts</p>
        <p class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">{{ count($loginAttempts) }}</p>
    </div>

    <div class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-[#6d7685]">Suspicious IPs</p>
        <p class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">{{ $suspiciousLogins->count() }}</p>
    </div>

    <div class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-[#6d7685]">Recent Sign-ins</p>
        <p class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">{{ $recentLogins->count() }}</p>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-5">
            <h2 class="text-lg font-semibold tracking-tight text-[#111827]">Suspicious Login Sources</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#eef1f4]">
                <thead class="bg-[#fbfcfd]">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">IP Address</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Actions</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Users</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f4] bg-white">
                    @forelse ($suspiciousLogins as $login)
                        <tr>
                            <td class="px-6 py-4 text-sm font-semibold text-[#111827]">{{ $login->ip_address ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-[#202733]">{{ $login->count }}</td>
                            <td class="px-6 py-4 text-sm text-[#6d7685]">{{ $login->users ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-sm text-[#6d7685]">No suspicious login patterns detected.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="border-b border-[#eef1f4] px-6 py-5">
            <h2 class="text-lg font-semibold tracking-tight text-[#111827]">Recent Sign-ins</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#eef1f4]">
                <thead class="bg-[#fbfcfd]">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">User</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">IP Address</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Last Login</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f4] bg-white">
                    @forelse ($recentLogins as $user)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-[#111827]">{{ $user->name }}</p>
                                <p class="mt-1 text-xs text-[#7a8392]">{{ $user->email }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#202733]">{{ $user->last_login_ip ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-[#202733]">{{ $user->last_login_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-sm text-[#6d7685]">No recent sign-ins found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
