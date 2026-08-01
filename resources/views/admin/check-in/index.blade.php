@extends('admin.layouts.app')

@section('title', 'Check-in')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Race Day Operations</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Check-in</h1>
            <p class="mt-2 max-w-3xl text-sm text-[#6d7685]">
                Mark approved participants as arrived, keep event-day attendance current, and move finished participants toward results.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Ready for Check-in</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['ready']) }}</p>
            </div>
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Checked In</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['checked_in']) }}</p>
            </div>
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Completed</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['completed']) }}</p>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant or bib number"
                    class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
            </div>
            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-2xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Filter</button>
                <a href="{{ route('admin.check-in.index') }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685] transition hover:bg-[#f7f8fa]">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Bib</th>
                            <th class="px-6 py-4">Waiver & Kit</th>
                            <th class="px-6 py-4">Current Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
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
                            <tr>
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $participant->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $participant->user?->email ?: 'No email available' }}</p>
                                    @if ($safetyContact !== '' || $healthNotes !== '')
                                        <div class="mt-3 rounded-2xl border border-rose-100 bg-rose-50/70 px-3 py-2 text-xs leading-5 text-rose-950">
                                            <p class="font-semibold text-rose-800">Safety</p>
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
                                        <p class="inline-flex rounded-full border px-2.5 py-1 font-semibold {{ $hasWaiverForKit ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                                            {{ $participant->waiver_accepted ? 'Mobile waiver accepted' : ($participant->kit_waiver_signed_at ? 'Onsite waiver signed' : 'Waiver required') }}
                                        </p>
                                        <p class="text-[#6d7685]">
                                            Kit {{ $participant->kit_released_at ? 'released '.$participant->kit_released_at->format('M d, h:i A') : 'not released yet' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full bg-[#eef1f4] px-3 py-1 text-xs font-semibold text-[#4f5a6a]">
                                        {{ str($participant->status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    @if ($participant->status === 'completed')
                                        <div class="inline-flex flex-col items-end gap-2">
                                            <span class="inline-flex h-10 items-center rounded-xl border border-[#d9dee7] px-3 text-sm font-medium text-[#5e6878]">
                                                Completed
                                            </span>
                                            <span class="text-xs text-[#6d7685]">Managed from Results</span>
                                        </div>
                                    @elseif ($eventCompleted)
                                        <div class="inline-flex flex-col items-end gap-2">
                                            <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-600">
                                                Closed
                                            </span>
                                            <span class="text-xs text-[#6d7685]">Event completed</span>
                                        </div>
                                    @elseif ($participant->status === 'checked_in')
                                        <span class="inline-flex h-10 items-center rounded-xl border border-indigo-200 bg-indigo-50 px-3 text-sm font-semibold text-indigo-700">
                                            Checked in
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('admin.check-in.update', $participant) }}" class="inline-flex flex-col items-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="checked_in">
                                            @unless ($hasWaiverForKit)
                                                <label class="inline-flex max-w-[260px] items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-left text-xs font-semibold text-amber-900">
                                                    <input type="checkbox" name="kit_waiver_signed" value="1" class="mt-0.5 rounded border-amber-300">
                                                    <span>Waiver signed before race kit release</span>
                                                </label>
                                            @endunless
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#151b26] px-4 text-xs font-semibold text-white transition hover:bg-[#232b39]">
                                                Check in
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-[#6d7685]">No check-in records are ready yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $participants->links() }}
            </div>
        </div>
    </div>
@endsection
