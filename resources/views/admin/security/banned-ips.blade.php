@extends('admin.layouts.app')

@section('title', 'Banned IPs')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Security</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Banned IPs</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Track blocked addresses and add new bans for abusive or suspicious traffic.</p>
    </div>

    <a href="{{ route('admin.security.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
        Back to security
    </a>
</div>

<div class="mb-6 rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Ban an IP Address</h2>
        <p class="mt-2 text-sm text-[#6d7685]">Use this to block repeated abuse, spam, or suspicious access attempts.</p>
    </div>

    <form method="POST" action="{{ route('admin.security.ban-ip') }}" class="grid gap-4 md:grid-cols-2">
        @csrf

        <div>
            <label for="ip_address" class="mb-2 block text-sm font-medium text-[#111827]">IP Address</label>
            <input
                type="text"
                name="ip_address"
                id="ip_address"
                value="{{ old('ip_address') }}"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
            @error('ip_address')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="expires_at" class="mb-2 block text-sm font-medium text-[#111827]">Expires At</label>
            <input
                type="datetime-local"
                name="expires_at"
                id="expires_at"
                value="{{ old('expires_at') }}"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
            <p class="mt-2 text-xs text-[#7a8392]">Required for temporary bans. Leave blank only when permanent ban is checked.</p>
            @error('expires_at')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label for="reason" class="mb-2 block text-sm font-medium text-[#111827]">Reason</label>
            <input
                type="text"
                name="reason"
                id="reason"
                value="{{ old('reason') }}"
                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
            >
            @error('reason')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex items-center gap-3 text-sm text-[#202733]">
                <input type="hidden" name="permanent" value="0">
                <input type="checkbox" name="permanent" value="1" {{ old('permanent') ? 'checked' : '' }} class="h-4 w-4 rounded border-[#cfd5de] text-[#111827] focus:ring-[#d9dee7]">
                <span>Permanent ban</span>
            </label>
        </div>

        <div class="md:col-span-2">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Ban IP Address
            </button>
        </div>
    </form>
</div>

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#eef1f4]">
            <thead class="bg-[#fbfcfd]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">IP Address</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Reason</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Expires</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8392]">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f4] bg-white">
                @forelse ($bannedIPs as $bannedIP)
                    <tr>
                        <td class="px-6 py-4 text-sm font-semibold text-[#111827]">{{ $bannedIP->ip_address }}</td>
                        <td class="px-6 py-4 text-sm text-[#202733]">{{ $bannedIP->reason }}</td>
                        <td class="px-6 py-4 text-sm text-[#202733]">
                            @if ($bannedIP->permanent)
                                <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">Permanent</span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Temporary</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[#202733]">{{ $bannedIP->expires_at?->format('F d, Y h:i A') ?? 'No expiry' }}</td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" action="{{ route('admin.security.unban-ip', $bannedIP) }}" onsubmit="return confirm('Remove this ban?')" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">
                                    Unban
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-14 text-center">
                            <div class="mx-auto max-w-md rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] px-6 py-8">
                                <p class="text-sm font-semibold text-[#202733]">No banned IPs</p>
                                <p class="mt-2 text-sm leading-6 text-[#6d7685]">Add a ban above if you need to block repeated abuse or suspicious access.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($bannedIPs->hasPages())
        <div class="border-t border-[#eef1f4] px-6 py-4">
            {{ $bannedIPs->links() }}
        </div>
    @endif
</div>
@endsection
