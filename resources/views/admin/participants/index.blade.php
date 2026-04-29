@extends('admin.layouts.app')

@section('title', 'Participants')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Participants</h1>
            <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Approve registrations, assign bib numbers, and track participant progress by event.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-5">
            @foreach ($summary as $label => $value)
                <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-[#6d7685]">{{ str($label)->replace('_', ' ')->title() }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_180px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant, email, bib, event"
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
            <div>
                <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                <select id="status" name="status" class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="h-11 rounded-2xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Filter</button>
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
                            <th class="px-6 py-4">Registered</th>
                            <th class="px-6 py-4">Manage Registration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($participants as $participant)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $participant->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $participant->user?->email ?: 'No email available' }}</p>
                                </td>
                                <td class="px-6 py-5">{{ $participant->event?->title ?: 'Deleted event' }}</td>
                                <td class="px-6 py-5">{{ $participant->category?->name ?: 'No category' }}</td>
                                <td class="px-6 py-5">{{ $participant->registered_at?->format('M d, Y h:i A') ?: $participant->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-6 py-5">
                                    <form method="POST" action="{{ route('admin.participants.update', $participant) }}" class="grid gap-3 lg:grid-cols-[180px_160px_auto] lg:items-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="h-10 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none">
                                        @foreach ($statusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($participant->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input name="bib_number" type="text" value="{{ $participant->bib_number }}" placeholder="Bib number"
                                            class="h-10 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none">
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#151b26] px-4 text-xs font-semibold text-white transition hover:bg-[#232b39]">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-[#6d7685]">No participants match the selected filters.</td>
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
