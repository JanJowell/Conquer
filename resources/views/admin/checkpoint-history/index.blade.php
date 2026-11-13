@extends('admin.layouts.app')

@section('title', 'Checkpoint History')

@section('content')
<div data-checkpoint-history class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-700">Checkpoint History</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Recorded checkpoint passages, not finishes or official results. A visit does not indicate a participant's current location.</p>
        </div>
        <a href="{{ route('admin.checkpoint-history.index', array_filter(['event_id' => $eventId, 'category_id' => $categoryId, 'checkpoint_id' => $checkpointId, 'search' => $search], fn ($value) => $value !== null && $value !== '')) }}" class="inline-flex h-11 items-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-indigo-700">Refresh</a>
    </div>

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if(!$historyAvailable)
        <div role="alert" class="space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <h2 class="font-semibold">Checkpoint history is not available in this environment yet.</h2>
            <p>The checkpoint history database setup must be completed before visits can be displayed. No records have been changed.</p>
        </div>
    @else
    <form data-checkpoint-history-filters method="GET" action="{{ route('admin.checkpoint-history.index') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label for="history-search" class="mb-2 block text-sm font-medium text-slate-700">Participant or BIB</label>
            <input id="history-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Name or BIB" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-2 focus:outline-indigo-600">
        </div>
        <div>
            <label for="history-event" class="mb-2 block text-sm font-medium text-slate-700">Event</label>
            <select id="history-event" name="event_id" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-2 focus:outline-indigo-600">
                <option value="">All events</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected($eventId === $event->id)>{{ $event->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="history-category" class="mb-2 block text-sm font-medium text-slate-700">Category</label>
            <select id="history-category" name="category_id" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-2 focus:outline-indigo-600">
                <option value="">All categories</option>
                @foreach($events as $event)
                    <optgroup label="{{ $event->title }}" data-history-event="{{ $event->id }}">
                        @foreach($categories->where('event_id', $event->id) as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div>
            @php($categoryCheckpoints = $checkpoints->where('category_id', $categoryId))
            <label for="history-checkpoint" class="mb-2 block text-sm font-medium text-slate-700">Checkpoint</label>
            <select id="history-checkpoint" name="checkpoint_id" @disabled($categoryId === null || $categoryCheckpoints->isEmpty()) class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:outline-2 focus:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60">
                <option value="">{{ $categoryId === null ? 'Select a category first' : ($categoryCheckpoints->isEmpty() ? 'No recorded checkpoints' : 'All checkpoints') }}</option>
                @foreach($categories as $category)
                    @php($recordedCheckpoints = $checkpoints->where('category_id', $category->id))
                    @if($recordedCheckpoints->isNotEmpty())
                        <optgroup label="{{ $category->name }}" data-history-category="{{ $category->id }}" @disabled($categoryId !== $category->id) @if($categoryId !== $category->id) hidden @endif>
                            @foreach($recordedCheckpoints as $checkpoint)
                                <option value="{{ $checkpoint->checkpoint_id }}" @selected($checkpointId === (int) $checkpoint->checkpoint_id)>{{ $checkpoint->checkpoint_position }}. {{ $checkpoint->checkpoint_label }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-3 md:col-span-2 xl:col-span-4">
            <button type="submit" class="h-11 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Filter</button>
            <a href="{{ route('admin.checkpoint-history.index') }}" class="inline-flex h-11 items-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-600">Clear</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 text-sm text-slate-600">
            <p>{{ number_format($scans->total()) }} {{ $scans->total() === 1 ? 'recorded visit' : 'recorded visits' }}</p>
            <p>Newest first &middot; Server time: {{ config('app.timezone') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <caption class="sr-only">Server-confirmed checkpoint visits matching the selected filters.</caption>
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-5 py-4">Participant / BIB</th>
                        <th scope="col" class="px-5 py-4">Event / Category</th>
                        <th scope="col" class="px-5 py-4">Checkpoint</th>
                        <th scope="col" class="px-5 py-4">Server time</th>
                        <th scope="col" class="px-5 py-4">Elapsed (hh:mm:ss)</th>
                        <th scope="col" class="px-5 py-4">Recorded by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($scans as $scan)
                        <tr data-checkpoint-visit="{{ $scan->id }}" class="align-top">
                            <td class="px-5 py-4">
                                <p class="break-words font-semibold">{{ $scan->participant_name }}</p>
                                <p class="mt-1 text-xs tabular-nums text-slate-600">BIB {{ $scan->bib_number }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="break-words">{{ $scan->event_title }}</p>
                                <p class="mt-1 break-words text-xs text-slate-600">{{ $scan->category_name }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="break-words">{{ $scan->checkpoint_position }}. {{ $scan->checkpoint_label }}</p>
                                @if($scan->after_cutoff)
                                    <span class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">After scheduled end</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 tabular-nums">
                                <time datetime="{{ $scan->scanned_at->toIso8601String() }}">{{ $scan->scanned_at->format('M j, Y H:i:s') }}</time>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold tabular-nums">{{ sprintf('%02d:%02d:%02d', intdiv($scan->elapsed_seconds, 3600), intdiv($scan->elapsed_seconds % 3600, 60), $scan->elapsed_seconds % 60) }}</td>
                            <td class="break-words px-5 py-4">{{ $scan->scanned_by_name ?: 'Former staff account' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-600">No checkpoint visits match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($scans->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $scans->links() }}</div>
        @endif
    </div>
    <p class="text-xs leading-5 text-slate-500">Elapsed time is measured from the category's actual start. Names and checkpoint labels are shown as recorded at the visit. Missing records alone do not establish a missed checkpoint.</p>
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/checkpoint-history.js') }}" defer></script>
@endpush
