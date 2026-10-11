@extends('admin.layouts.app')

@section('title', 'Activity Logs')

@section('content')
<div class="mx-auto max-w-[1400px] space-y-6">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Security</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">Activity Logs</h1>
            <p class="mt-2 text-sm text-slate-500">Review admin actions by user, IP address, action keyword, and date range.</p>
        </div>

        <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
            Back to security
        </a>
    </div>

    <div class="rounded-[1.75rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] sm:p-5">
        <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label for="user_id" class="mb-1.5 block text-sm font-medium text-slate-600">User ID</label>
                <input
                    type="number"
                    name="user_id"
                    id="user_id"
                    value="{{ request('user_id') }}"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div>
                <label for="action" class="mb-1.5 block text-sm font-medium text-slate-600">Action</label>
                <input
                    type="text"
                    name="action"
                    id="action"
                    value="{{ request('action') }}"
                    placeholder="POST admin/users"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div>
                <label for="ip_address" class="mb-1.5 block text-sm font-medium text-slate-600">IP Address</label>
                <input
                    type="text"
                    name="ip_address"
                    id="ip_address"
                    value="{{ request('ip_address') }}"
                    placeholder="127.0.0.1"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div>
                <label for="date_from" class="mb-1.5 block text-sm font-medium text-slate-600">From</label>
                <input
                    type="date"
                    name="date_from"
                    id="date_from"
                    value="{{ request('date_from') }}"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div>
                <label for="date_to" class="mb-1.5 block text-sm font-medium text-slate-600">To</label>
                <input
                    type="date"
                    name="date_to"
                    id="date_to"
                    value="{{ request('date_to') }}"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div class="flex flex-wrap items-end gap-3 xl:col-span-5">
                <button type="submit" class="rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Filter Logs
                </button>
                <a href="{{ route('admin.security.activity-logs') }}" class="rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">User</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Action</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">IP Address</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">User Agent</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Logged At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="align-top transition hover:bg-[#f7f8fc]">
                            <td class="px-6 py-4">
                                <div class="min-w-[180px]">
                                    <p class="text-sm font-semibold text-slate-700">{{ $log->user?->name ?? 'Deleted user' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $log->user?->email ?? 'No email available' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">User ID: {{ $log->user_id }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $log->action }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $log->ip_address ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500">
                                <div class="max-w-xs break-words">
                                    {{ $log->user_agent ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $log->created_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <div class="mx-auto max-w-md rounded-2xl bg-slate-50 px-6 py-8">
                                    <p class="text-sm font-semibold text-slate-700">No activity logs found</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-500">Try adjusting your filters or come back after more admin activity has been recorded.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-slate-50 px-6 py-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection