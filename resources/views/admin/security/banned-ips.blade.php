@extends('admin.layouts.app')

@section('title', 'Banned IPs')

@section('content')
<div class="mx-auto max-w-[1400px] space-y-6">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Security</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">Banned IPs</h1>
            <p class="mt-2 text-sm text-slate-500">Track blocked addresses and add new bans for abusive or suspicious traffic.</p>
        </div>

        <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
            Back to security
        </a>
    </div>

    <div class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
        <div class="mb-5">
            <h2 class="text-xl font-bold tracking-tight text-slate-700">Ban an IP Address</h2>
            <p class="mt-2 text-sm text-slate-500">Use this to block repeated abuse, spam, or suspicious access attempts.</p>
        </div>

        <form method="POST" action="{{ route('admin.security.ban-ip') }}" class="grid gap-4 md:grid-cols-2">
            @csrf

            <div>
                <label for="ip_address" class="mb-1.5 block text-sm font-medium text-slate-600">IP Address</label>
                <input
                    type="text"
                    name="ip_address"
                    id="ip_address"
                    value="{{ old('ip_address') }}"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
                @error('ip_address')
                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="expires_at" class="mb-1.5 block text-sm font-medium text-slate-600">Expires At</label>
                <input
                    type="datetime-local"
                    name="expires_at"
                    id="expires_at"
                    value="{{ old('expires_at') }}"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
                <p class="mt-2 text-xs text-slate-400">Required for temporary bans. Leave blank only when permanent ban is checked.</p>
                @error('expires_at')
                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="reason" class="mb-1.5 block text-sm font-medium text-slate-600">Reason</label>
                <input
                    type="text"
                    name="reason"
                    id="reason"
                    value="{{ old('reason') }}"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
                @error('reason')
                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-3 text-sm text-slate-600">
                    <input type="hidden" name="permanent" value="0">
                    <input type="checkbox" name="permanent" value="1" {{ old('permanent') ? 'checked' : '' }} class="h-4 w-4 rounded border-0 bg-slate-100 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                    <span>Permanent ban</span>
                </label>
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Ban IP Address
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">IP Address</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Reason</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Expires</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bannedIPs as $bannedIP)
                        <tr class="transition hover:bg-[#f7f8fc]">
                            <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ $bannedIP->ip_address }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $bannedIP->reason }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                @if ($bannedIP->permanent)
                                    <span class="inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-400">Permanent</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-500">Temporary</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $bannedIP->expires_at?->format('F d, Y h:i A') ?? 'No expiry' }}</td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('admin.security.unban-ip', $bannedIP) }}" onsubmit="return confirm('Remove this ban?')" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-500 transition hover:bg-emerald-100">
                                        Unban
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <div class="mx-auto max-w-md rounded-2xl bg-slate-50 px-6 py-8">
                                    <p class="text-sm font-semibold text-slate-700">No banned IPs</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-500">Add a ban above if you need to block repeated abuse or suspicious access.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bannedIPs->hasPages())
            <div class="border-t border-slate-50 px-6 py-4">
                {{ $bannedIPs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection