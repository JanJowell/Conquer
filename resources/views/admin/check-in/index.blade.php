@extends('admin.layouts.app')

@section('title', 'Check-in')

@section('content')
    <div class="mx-auto max-w-[1400px] space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Race Day Operations</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">Check-in</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">
                Mark approved participants as arrived, keep event-day attendance current, and move finished participants toward results.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <p class="text-sm font-medium text-slate-500">Ready for Check-in</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-700">{{ number_format($summary['ready']) }}</p>
            </div>
            <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <p class="text-sm font-medium text-slate-500">Checked In</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-700">{{ number_format($summary['checked_in']) }}</p>
            </div>
            <div class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_32px_rgba(148,163,184,0.22)]">
                <p class="text-sm font-medium text-slate-500">Completed</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-700">{{ number_format($summary['completed']) }}</p>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-[1.75rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:grid-cols-[minmax(0,1fr)_220px_auto]">
            <div>
                <label for="search" class="mb-1.5 block text-sm font-medium text-slate-600">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant or bib number"
                    class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-300 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label for="event_id" class="mb-1.5 block text-sm font-medium text-slate-600">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">Filter</button>
                <a href="{{ route('admin.check-in.index') }}" class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Bib</th>
                            <th class="px-6 py-4">Waiver & Kit</th>
                            <th class="px-6 py-4">Current Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-600">
                        @forelse ($participants as $participant)
                            @php
                                $eventCompleted = $participant->event?->effective_status === 'completed';
                                $emergencyContact = trim(collect([
                                    $participant->user?->emergency_contact_name,
                                    $participant->user?->emergency_contact_number,
                                ])->filter()->implode(' - '));
                                $legacyEmergencyContact = trim((string) ($participant->user?->emergency_contact ?? ''));
                                $safetyContact = $emergencyContact !== '' ? $emergencyContact : $legacyEmergencyContact;
                                $healthNotes = trim((string) ($participant->medical_conditions ?? ''));
                                $hasWaiverForKit = $participant->waiver_accepted || $participant->kit_waiver_signed_at;
                            @endphp
                            <tr class="transition hover:bg-[#f7f8fc]">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-slate-700">{{ $participant->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $participant->user?->email ?: 'No email available' }}</p>
                                    @if ($safetyContact !== '' || $healthNotes !== '')
                                        <div class="mt-3 rounded-2xl bg-rose-50 px-3 py-2 text-xs leading-5 text-rose-500">
                                            <p class="font-semibold text-rose-500">Safety</p>
                                            @if ($safetyContact !== '')
                                                <p class="mt-1"><span class="font-semibold">Emergency:</span> {{ $safetyContact }}</p>
                                            @endif
                                            @if ($healthNotes !== '')
                                                <p class="mt-1"><span class="font-semibold">Event health notes:</span> {{ $healthNotes }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $participant->event?->title ?: 'Deleted event' }}</td>
                                <td class="px-6 py-5">{{ $participant->category?->name ?: 'No category' }}</td>
                                <td class="px-6 py-5">{{ $participant->bib_number ?: 'Not assigned' }}</td>
                                <td class="px-6 py-5">
                                    <div class="space-y-2 text-xs">
                                        <p class="inline-flex rounded-full px-3 py-1 font-semibold {{ $hasWaiverForKit ? 'bg-emerald-50 text-emerald-500' : 'bg-rose-50 text-rose-400' }}">
                                            {{ $participant->waiver_accepted ? 'Mobile waiver accepted' : ($participant->kit_waiver_signed_at ? 'Onsite waiver signed' : 'Waiver required') }}
                                        </p>
                                        <p class="text-slate-400">
                                            Kit {{ $participant->kit_released_at ? 'released '.$participant->kit_released_at->format('M d, h:i A') : 'not released yet' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                                        {{ str($participant->status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    @if ($participant->status === 'completed')
                                        <div class="inline-flex flex-col items-end gap-2">
                                            <span class="inline-flex h-10 items-center rounded-full bg-slate-100 px-4 text-sm font-medium text-slate-500">
                                                Completed
                                            </span>
                                            <span class="text-xs text-slate-400">Managed from Results</span>
                                        </div>
                                    @elseif ($eventCompleted)
                                        <div class="inline-flex flex-col items-end gap-2">
                                            <span class="inline-flex h-10 items-center rounded-full bg-slate-100 px-4 text-sm font-semibold text-slate-500">
                                                Closed
                                            </span>
                                            <span class="text-xs text-slate-400">Event completed</span>
                                        </div>
                                    @elseif ($participant->status === 'checked_in')
                                        <div class="inline-flex flex-col items-end gap-2">
                                            <span class="inline-flex h-10 items-center rounded-full bg-indigo-50 px-4 text-sm font-semibold text-indigo-400">
                                                Checked in
                                            </span>
                                            @if ($participant->bib_number && $participant->category_id)
                                                <a href="{{ route('admin.check-in.finish-qr', $participant) }}" target="_blank"
                                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-sky-50 px-4 text-xs font-semibold text-sky-500 transition hover:bg-sky-100">
                                                    <i class="fas fa-qrcode"></i>
                                                    View / Print Finish QR
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('admin.check-in.update', $participant) }}" class="inline-flex flex-col items-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="checked_in">
                                            @unless ($hasWaiverForKit)
                                                <label class="inline-flex max-w-[260px] items-start gap-2 rounded-2xl bg-amber-50 px-3 py-2 text-left text-xs font-semibold text-amber-500">
                                                    <input type="checkbox" name="kit_waiver_signed" value="1" class="mt-0.5 h-4 w-4 rounded border-0 bg-slate-100 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                                                    <span>Waiver signed before race kit release</span>
                                                </label>
                                            @endunless
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-indigo-400 px-5 text-xs font-semibold text-white transition hover:bg-indigo-500">
                                                Check in
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">No check-in records are ready yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-50 px-6 py-4">
                {{ $participants->links() }}
            </div>
        </div>
    </div>
@endsection