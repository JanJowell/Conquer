@extends('admin.layouts.app')

@section('title', 'Checkpoints Management')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Course Operations</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Checkpoints</h1>
            <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Manage course markers, hydration stations, medical posts, and finish points.</p>
        </div>

        <a href="{{ route('admin.content.checkpoints.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
            <i class="fas fa-plus mr-2 text-xs"></i>
            Add Checkpoint
        </a>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-4 shadow-sm lg:grid-cols-[minmax(220px,1fr)_minmax(220px,1fr)_180px_auto_auto] lg:items-end">
        <div>
            <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
            <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="Name, location, or description"
                class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
        </div>

        <div>
            <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
            <select id="event_id" name="event_id" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">All events</option>
                @foreach ($events as $event)
                    <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="type" class="mb-2 block text-sm font-medium text-[#3d4757]">Type</label>
            <select id="type" name="type" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">All types</option>
                @foreach (['hydration' => 'Hydration', 'medical' => 'Medical', 'checkpoint' => 'Checkpoint', 'finish' => 'Finish'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">
            Filter
        </button>

        <a href="{{ route('admin.content.checkpoints') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa] focus:outline-none focus:ring-2 focus:ring-[#d9dee7]">
            Clear
        </a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#eef1f4]">
                <thead class="bg-[#fafbfc]">
                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                        <th class="px-6 py-4">Checkpoint</th>
                        <th class="px-6 py-4">Event</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">Location</th>
                        <th class="px-6 py-4">Order</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                    @forelse($checkpoints as $checkpoint)
                        <tr class="align-top">
                            <td class="px-6 py-5">
                                <p class="font-semibold text-[#151b26]">{{ $checkpoint->name }}</p>
                                @if($checkpoint->description)
                                    <p class="mt-1 text-xs leading-6 text-[#6d7685]">{{ Str::limit($checkpoint->description, 60) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-5">{{ $checkpoint->event ? $checkpoint->event->title : '-' }}</td>
                            <td class="px-6 py-5">
                                <span @class([
                                    'inline-flex rounded-full border px-3 py-1 text-xs font-semibold',
                                    'border-sky-200 bg-sky-50 text-sky-700' => $checkpoint->type === 'hydration',
                                    'border-rose-200 bg-rose-50 text-rose-700' => $checkpoint->type === 'medical',
                                    'border-amber-200 bg-amber-50 text-amber-700' => $checkpoint->type === 'checkpoint',
                                    'border-emerald-200 bg-emerald-50 text-emerald-700' => $checkpoint->type === 'finish',
                                ])>
                                    {{ str($checkpoint->type)->title() }}
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                <p>{{ $checkpoint->location }}</p>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $checkpoint->latitude }},{{ $checkpoint->longitude }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-2 inline-flex items-center text-xs font-semibold text-[#315fa8] hover:text-[#244c8a]">
                                    <i class="fas fa-map-location-dot mr-1"></i>
                                    Open map
                                </a>
                            </td>
                            <td class="px-6 py-5">{{ $checkpoint->order }}</td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.content.checkpoints.edit', $checkpoint) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.content.checkpoints.destroy', $checkpoint) }}" onsubmit="return confirm('Are you sure you want to delete this checkpoint?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-[#6d7685]">No checkpoints found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-[#eef1f4] px-6 py-4">
            {{ $checkpoints->links() }}
        </div>
    </div>
</div>
@endsection
