@extends('admin.layouts.app')

@section('title', 'Data Access Logs')

@section('content')
<div class="mx-auto max-w-[1400px] space-y-6">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Security</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">Data Access Logs</h1>
            <p class="mt-2 text-sm text-slate-500">Audit admin actions related to data viewing, exports, and downloads.</p>
        </div>

        <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
            Back to security
        </a>
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
                    @forelse ($dataAccessLogs as $log)
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
                                    <p class="text-sm font-semibold text-slate-700">No data access logs found</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-500">Matching audit entries will appear here after data, export, or download actions are logged.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($dataAccessLogs->hasPages())
            <div class="border-t border-slate-50 px-6 py-4">
                {{ $dataAccessLogs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection