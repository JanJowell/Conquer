@extends('admin.layouts.app')

@section('title', 'Login Monitoring')

@section('content')
<div class="mx-auto max-w-[1400px] space-y-6">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Security</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">Login Monitoring</h1>
            <p class="mt-2 text-sm text-slate-500">Review recent sign-ins and IP addresses with unusually high admin activity.</p>
        </div>

        <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
            Back to security
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-[1.5rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
            <p class="text-sm font-medium text-slate-500">Tracked Login Attempts</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-700">{{ count($loginAttempts) }}</p>
        </div>

        <div class="rounded-[1.5rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
            <p class="text-sm font-medium text-slate-500">Suspicious IPs</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-700">{{ $suspiciousLogins->count() }}</p>
        </div>

        <div class="rounded-[1.5rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
            <p class="text-sm font-medium text-slate-500">Recent Sign-ins</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-700">{{ $recentLogins->count() }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="border-b border-slate-50 px-6 py-5">
                <h2 class="text-lg font-bold tracking-tight text-slate-700">Suspicious Login Sources</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">IP Address</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Actions</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Users</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suspiciousLogins as $login)
                            <tr class="transition hover:bg-[#f7f8fc]">
                                <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ $login->ip_address ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $login->count }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500">{{ $login->users ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500">No suspicious login patterns detected.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="border-b border-slate-50 px-6 py-5">
                <h2 class="text-lg font-bold tracking-tight text-slate-700">Recent Sign-ins</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">User</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">IP Address</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentLogins as $user)
                            <tr class="transition hover:bg-[#f7f8fc]">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-700">{{ $user->name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $user->email }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $user->last_login_ip ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $user->last_login_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500">No recent sign-ins found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection