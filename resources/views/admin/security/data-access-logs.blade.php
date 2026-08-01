@extends('admin.layouts.app')

@section('title', 'Data Access Logs')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Security</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Data Access Logs</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Audit admin actions related to data viewing, exports, and downloads.</p>
    </div>

    <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
        Back to security
    </a>
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
                @forelse ($dataAccessLogs as $log)
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
                                <p class="text-sm font-semibold text-[#202733]">No data access logs found</p>
                                <p class="mt-2 text-sm leading-6 text-[#6d7685]">Matching audit entries will appear here after data, export, or download actions are logged.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($dataAccessLogs->hasPages())
        <div class="border-t border-[#eef1f4] px-6 py-4">
            {{ $dataAccessLogs->links() }}
        </div>
    @endif
</div>
@endsection
