@extends('admin.layouts.app')

@section('title', 'Activity Logs')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Security</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Activity Logs</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Review admin actions by user, IP address, action keyword, and date range.</p>
    </div>

    <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
        Back to security
    </a>
</div>

<div class="mb-6 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm sm:p-5">
    <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div>
            <label for="user_id" class="mb-2 block text-sm font-medium text-[#111827]">User ID</label>
            <input
                type="number"
                name="user_id"
                id="user_id"
                value="{{ request('user_id') }}"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div>
            <label for="action" class="mb-2 block text-sm font-medium text-[#111827]">Action</label>
            <input
                type="text"
                name="action"
                id="action"
                value="{{ request('action') }}"
                placeholder="POST admin/users"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div>
            <label for="ip_address" class="mb-2 block text-sm font-medium text-[#111827]">IP Address</label>
            <input
                type="text"
                name="ip_address"
                id="ip_address"
                value="{{ request('ip_address') }}"
                placeholder="127.0.0.1"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div>
            <label for="date_from" class="mb-2 block text-sm font-medium text-[#111827]">From</label>
            <input
                type="date"
                name="date_from"
                id="date_from"
                value="{{ request('date_from') }}"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div>
            <label for="date_to" class="mb-2 block text-sm font-medium text-[#111827]">To</label>
            <input
                type="date"
                name="date_to"
                id="date_to"
                value="{{ request('date_to') }}"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
        </div>

        <div class="flex flex-wrap items-end gap-3 xl:col-span-5">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Filter Logs
            </button>
            <a href="{{ route('admin.security.activity-logs') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Clear
            </a>
        </div>
    </form>
</div>

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#eef1f4]">
            <thead class="bg-[#fbfcfd]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">User</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Action</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">IP Address</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">User Agent</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Logged At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f4] bg-white">
                @forelse ($logs as $log)
                    <tr class="align-top">
                        <td class="px-6 py-4">
                            <div class="min-w-[180px]">
                                <p class="text-sm font-semibold text-[#111827]">{{ $log->user?->name ?? 'Deleted user' }}</p>
                                <p class="mt-1 text-xs text-[#7a8392]">{{ $log->user?->email ?? 'No email available' }}</p>
                                <p class="mt-1 text-xs text-[#7a8392]">User ID: {{ $log->user_id }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#202733]">{{ $log->action }}</td>
                        <td class="px-6 py-4 text-sm text-[#202733]">{{ $log->ip_address ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-[#6d7685]">
                            <div class="max-w-xs break-words">
                                {{ $log->user_agent ?? 'N/A' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#202733]">{{ $log->created_at?->format('F d, Y h:i A') ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-14 text-center">
                            <div class="mx-auto max-w-md rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] px-6 py-8">
                                <p class="text-sm font-semibold text-[#202733]">No activity logs found</p>
                                <p class="mt-2 text-sm leading-6 text-[#6d7685]">Try adjusting your filters or come back after more admin activity has been recorded.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="border-t border-[#eef1f4] px-6 py-4">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
