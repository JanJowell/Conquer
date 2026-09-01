@extends('admin.layouts.app')

@section('title', 'Finish Scans')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event-Day Simulation</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Finish Scan History</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[#6d7685]">Scanner captures are provisional. Review them in Results before publishing rankings and E-Certificates.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([['label' => 'Total Scans', 'value' => $summary['total']], ['label' => 'Awaiting Publication', 'value' => $summary['provisional']], ['label' => 'Published Results', 'value' => $summary['published']]] as $card)
                <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-[#6d7685]">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($card['value']) }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_220px_220px_180px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Participant or BIB</label>
                <input id="search" name="search" value="{{ request('search') }}" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm outline-none" placeholder="Name or BIB">
            </div>
            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm outline-none">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Category</label>
                <select id="category_id" name="category_id" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm outline-none">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->event?->title }} · {{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                <select id="status" name="status" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm outline-none">
                    <option value="">All statuses</option>
                    <option value="provisional" @selected(request('status') === 'provisional')>Provisional</option>
                    <option value="published" @selected(request('status') === 'published')>Published</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="h-11 rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold">Filter</button>
                <a href="{{ route('admin.finish-scans.index') }}" class="inline-flex h-11 items-center rounded-xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685]">Clear</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4] text-sm">
                    <thead class="bg-[#fafbfc] text-left text-xs font-semibold uppercase tracking-[0.16em] text-[#7a8495]">
                        <tr>
                            <th class="px-5 py-4">Participant</th>
                            <th class="px-5 py-4">Event / Category</th>
                            <th class="px-5 py-4">Scan</th>
                            <th class="px-5 py-4">Elapsed</th>
                            <th class="px-5 py-4">Staff</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Review</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-[#202733]">
                        @forelse ($scans as $scan)
                            <tr class="align-top">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-[#151b26]">{{ $scan->registration?->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">BIB {{ $scan->bib_number }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ $scan->event?->title ?: 'Deleted event' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $scan->category?->name ?: 'Deleted category' }}</p>
                                </td>
                                <td class="px-5 py-4">{{ $scan->scanned_at?->format('M j, Y g:i:s A') }}</td>
                                <td class="px-5 py-4 font-semibold">{{ $scan->elapsed_time }}</td>
                                <td class="px-5 py-4">{{ $scan->scannedBy?->name ?: 'Former staff account' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $scan->status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ str($scan->status)->title() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('admin.results.index', ['search' => $scan->bib_number, 'event_id' => $scan->event_id]) }}" class="inline-flex h-9 items-center rounded-xl border border-[#d9dee7] px-3 text-xs font-semibold">Open Results</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-[#6d7685]">No finish scans match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[#eef1f4] px-5 py-4">{{ $scans->links() }}</div>
        </div>
    </div>
@endsection
