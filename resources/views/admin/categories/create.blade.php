@extends('admin.layouts.app')

@section('title', 'Create Category')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Setup</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Add Race Category</h1>
            <p class="mt-2 text-sm text-[#6d7685]">Create distance groups like 5K, 10K, or half marathon for a selected event.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.categories.store') }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                    <select id="event_id" name="event_id" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-[#3d4757]">Category Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="distance_km" class="mb-2 block text-sm font-medium text-[#3d4757]">Distance (km)</label>
                    <input id="distance_km" name="distance_km" type="number" step="0.01" min="0" value="{{ old('distance_km') }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="slot_limit" class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit</label>
                    <input id="slot_limit" name="slot_limit" type="number" min="1" value="{{ old('slot_limit') }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                    <select id="status" name="status" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        @foreach (['open', 'closed', 'draft'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'open') === $status)>{{ str($status)->title() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
                    <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Save Category
                </button>
                <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
