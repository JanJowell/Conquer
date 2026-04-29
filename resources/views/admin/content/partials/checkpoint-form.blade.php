<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
        <select id="event_id" name="event_id" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
            @foreach ($events as $event)
                <option value="{{ $event->id }}" @selected((string) old('event_id', $checkpoint?->event_id) === (string) $event->id)>{{ $event->title }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="name" class="mb-2 block text-sm font-medium text-[#3d4757]">Checkpoint Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $checkpoint?->name) }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
    </div>

    <div>
        <label for="type" class="mb-2 block text-sm font-medium text-[#3d4757]">Type</label>
        <select id="type" name="type" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
            @foreach (['hydration', 'medical', 'checkpoint', 'finish'] as $type)
                <option value="{{ $type }}" @selected(old('type', $checkpoint?->type ?? 'checkpoint') === $type)>{{ str($type)->title() }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label for="location" class="mb-2 block text-sm font-medium text-[#3d4757]">Location</label>
        <input id="location" name="location" type="text" value="{{ old('location', $checkpoint?->location) }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
    </div>

    <div>
        <label for="latitude" class="mb-2 block text-sm font-medium text-[#3d4757]">Latitude</label>
        <input id="latitude" name="latitude" type="number" step="0.00000001" value="{{ old('latitude', $checkpoint?->latitude) }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
    </div>

    <div>
        <label for="longitude" class="mb-2 block text-sm font-medium text-[#3d4757]">Longitude</label>
        <input id="longitude" name="longitude" type="number" step="0.00000001" value="{{ old('longitude', $checkpoint?->longitude) }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
    </div>

    <div>
        <label for="order" class="mb-2 block text-sm font-medium text-[#3d4757]">Sequence Order</label>
        <input id="order" name="order" type="number" min="1" value="{{ old('order', $checkpoint?->order ?? 1) }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
        <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('description', $checkpoint?->description) }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
        Save Checkpoint
    </button>
    <a href="{{ route('admin.content.checkpoints') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
        Cancel
    </a>
</div>
