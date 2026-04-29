@extends('admin.layouts.app')

@section('title', 'Results')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Race Results</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Results Management</h1>
            <p class="mt-2 max-w-3xl text-sm text-[#6d7685]">Encode finish times, store rankings, and mark registrations as completed once results are posted.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Published Results</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['published_results']) }}</p>
            </div>
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Awaiting Results</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['awaiting_results']) }}</p>
            </div>
            <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Completed Registrations</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['completed_registrations']) }}</p>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant, bib, event"
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
                            <th class="px-6 py-4">Event / Category</th>
                            <th class="px-6 py-4">Bib</th>
                            <th class="px-6 py-4">Finish Time</th>
                            <th class="px-6 py-4">Ranks</th>
                            <th class="px-6 py-4">Remarks</th>
                            <th class="px-6 py-4 text-right">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($registrations as $registration)
                            @php($result = $registration->raceResult)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $registration->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->user?->email ?: 'No email available' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $registration->event?->title ?: 'Deleted event' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->category?->name ?: 'No category' }}</p>
                                </td>
                                <td class="px-6 py-5">{{ $registration->bib_number ?: 'Not assigned' }}</td>
                                <td colspan="4" class="px-6 py-5">
                                    <form method="POST" action="{{ $result ? route('admin.results.update', $result) : route('admin.results.store') }}" class="grid gap-3 md:grid-cols-[120px_120px_120px_minmax(0,1fr)_auto]">
                                        @csrf
                                        @if ($result)
                                            @method('PATCH')
                                        @else
                                            <input type="hidden" name="registration_id" value="{{ $registration->id }}">
                                        @endif
                                        <input name="finish_time" type="text" value="{{ old('finish_time', $result?->finish_time) }}" placeholder="00:45:12"
                                            class="h-10 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none">
                                        <input name="rank_overall" type="number" min="1" value="{{ old('rank_overall', $result?->rank_overall) }}" placeholder="Overall"
                                            class="h-10 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none">
                                        <input name="rank_category" type="number" min="1" value="{{ old('rank_category', $result?->rank_category) }}" placeholder="Category"
                                            class="h-10 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none">
                                        <input name="remarks" type="text" value="{{ old('remarks', $result?->remarks) }}" placeholder="Optional notes"
                                            class="h-10 rounded-xl border border-[#d9dee7] px-3 text-sm text-[#151b26] outline-none">
                                        <div class="flex justify-end">
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#151b26] px-4 text-xs font-semibold text-white transition hover:bg-[#232b39]">
                                                {{ $result ? 'Update' : 'Save' }}
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-[#6d7685]">No registrations are ready for result encoding yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $registrations->links() }}
            </div>
        </div>
    </div>
@endsection
