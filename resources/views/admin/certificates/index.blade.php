@extends('admin.layouts.app')

@section('title', 'E-Certificates')

@section('content')
<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1400px] space-y-6">

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Official completion records</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-700">E-Certificates</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-400">Certificates are issued automatically once a participant has a completed registration with an official result. Feedback remains optional.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Total', $summary['total']], ['Valid', $summary['valid']], ['Revoked', $summary['revoked']], ['Awaiting Certificate', $summary['awaiting_certificate']]] as [$label, $value])
                <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                    <p class="text-sm text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" class="grid gap-3 rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:grid-cols-4">
            <input name="search" value="{{ request('search') }}" placeholder="Participant or certificate number" class="h-11 rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200 md:col-span-2">
            <select name="event_id" class="h-11 rounded-2xl border-0 bg-slate-50 px-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                <option value="">All events</option>
                @foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <select name="status" class="h-11 min-w-0 flex-1 rounded-2xl border-0 bg-slate-50 px-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">All statuses</option>
                    <option value="valid" @selected(request('status') === 'valid')>Valid</option>
                    <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                </select>
                <button class="h-11 rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">Filter</button>
            </div>
        </form>

        <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-6 py-4">Certificate</th>
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event / Category</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($certificates as $certificate)
                        <tr class="transition hover:bg-[#f7f8fc]">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-700">{{ $certificate->certificate_number }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ optional($certificate->issued_at)->format('M j, Y g:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-700">{{ $certificate->user?->name }}</td>
                            <td class="px-6 py-4">
                                <div class="text-slate-600">{{ $certificate->event?->title }}</div>
                                <div class="text-xs text-slate-400">{{ $certificate->category?->name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $certificate->isValid() ? 'bg-emerald-50 text-emerald-500' : 'bg-rose-50 text-rose-400' }}">{{ $certificate->isValid() ? 'Valid' : 'Revoked' }}</span>
                                @if ($certificate->revocation_reason)
                                    <div class="mt-2 max-w-xs text-xs text-slate-400">{{ $certificate->revocation_reason }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a target="_blank" href="{{ route('certificates.verify', $certificate->verification_token) }}" class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-200">Verify</a>
                                    @if ($certificate->isValid())
                                        <a href="{{ route('certificates.download', $certificate->verification_token) }}" class="inline-flex items-center rounded-full bg-indigo-400 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500">Download</a>
                                        <form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}" class="flex gap-2" onsubmit="return confirm('Revoke this E-Certificate? The record will remain visible as revoked.');">
                                            @csrf @method('PATCH')
                                            <input name="reason" required maxlength="500" placeholder="Revocation reason" class="w-40 rounded-2xl border-0 bg-rose-50 px-3 py-2 text-xs text-slate-700 placeholder:text-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-200">
                                            <button class="inline-flex items-center rounded-full bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-400 transition hover:bg-rose-100">Revoke</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.certificates.reinstate', $certificate) }}" onsubmit="return confirm('Reinstate this E-Certificate?');">
                                            @csrf @method('PATCH')
                                            <button class="inline-flex items-center rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-500 transition hover:bg-emerald-100">Reinstate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">No E-Certificates match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($certificates->hasPages())
                <div class="border-t border-slate-50 px-6 py-4">{{ $certificates->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection