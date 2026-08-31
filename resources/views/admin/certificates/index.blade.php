@extends('admin.layouts.app')

@section('title', 'E-Certificates')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Official completion records</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">E-Certificates</h1>
        <p class="mt-2 max-w-3xl text-sm text-[#6d7685]">Certificates are issued automatically once a participant has a completed registration with an official result. Feedback remains optional.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Total', $summary['total']], ['Valid', $summary['valid']], ['Revoked', $summary['revoked']], ['Awaiting Certificate', $summary['awaiting_certificate']]] as [$label, $value])
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">{{ $label }}</p>
                <p class="mt-3 text-3xl font-semibold text-[#151b26]">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm md:grid-cols-4">
        <input name="search" value="{{ request('search') }}" placeholder="Participant or certificate number" class="h-11 rounded-xl border border-[#d9dee7] px-4 md:col-span-2">
        <select name="event_id" class="h-11 rounded-xl border border-[#d9dee7] px-3">
            <option value="">All events</option>
            @foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <select name="status" class="min-w-0 flex-1 rounded-xl border border-[#d9dee7] px-3">
                <option value="">All statuses</option>
                <option value="valid" @selected(request('status') === 'valid')>Valid</option>
                <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
            </select>
            <button class="rounded-xl bg-[#151b26] px-4 text-sm font-semibold text-white">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#e4e8ee] text-sm">
                <thead class="bg-[#f7f9fb] text-left text-xs uppercase tracking-wider text-[#687386]"><tr>
                    <th class="px-5 py-4">Certificate</th><th class="px-5 py-4">Participant</th><th class="px-5 py-4">Event / Category</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-[#edf0f4]">
                @forelse ($certificates as $certificate)
                    <tr>
                        <td class="px-5 py-4"><div class="font-semibold text-[#151b26]">{{ $certificate->certificate_number }}</div><div class="mt-1 text-xs text-[#7a8495]">{{ optional($certificate->issued_at)->format('M j, Y g:i A') }}</div></td>
                        <td class="px-5 py-4 font-medium">{{ $certificate->user?->name }}</td>
                        <td class="px-5 py-4"><div>{{ $certificate->event?->title }}</div><div class="text-xs text-[#7a8495]">{{ $certificate->category?->name }}</div></td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $certificate->isValid() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $certificate->isValid() ? 'Valid' : 'Revoked' }}</span>
                            @if ($certificate->revocation_reason)<div class="mt-2 max-w-xs text-xs text-[#7a8495]">{{ $certificate->revocation_reason }}</div>@endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <a target="_blank" href="{{ route('certificates.verify', $certificate->verification_token) }}" class="rounded-lg border border-[#d9dee7] px-3 py-2 font-semibold">Verify</a>
                                @if ($certificate->isValid())
                                    <a href="{{ route('certificates.download', $certificate->verification_token) }}" class="rounded-lg bg-[#151b26] px-3 py-2 font-semibold text-white">Download</a>
                                    <form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}" class="flex gap-2" onsubmit="return confirm('Revoke this E-Certificate? The record will remain visible as revoked.');">@csrf @method('PATCH')
                                        <input name="reason" required maxlength="500" placeholder="Revocation reason" class="w-40 rounded-lg border border-[#efcaca] px-3">
                                        <button class="rounded-lg bg-red-50 px-3 py-2 font-semibold text-red-700">Revoke</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.certificates.reinstate', $certificate) }}" onsubmit="return confirm('Reinstate this E-Certificate?');">@csrf @method('PATCH')<button class="rounded-lg bg-green-50 px-3 py-2 font-semibold text-green-700">Reinstate</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-[#7a8495]">No E-Certificates match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($certificates->hasPages())<div class="border-t border-[#e4e8ee] p-4">{{ $certificates->links() }}</div>@endif
    </div>
</div>
@endsection
