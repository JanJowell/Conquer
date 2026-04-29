<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="title" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Name</label>
        <input id="title" name="title" type="text" value="{{ old('title', $event?->title) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
        <textarea id="description" name="description" rows="4"
            class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ old('description', $event?->description) }}</textarea>
    </div>

    <div>
        <label for="venue" class="mb-2 block text-sm font-medium text-[#3d4757]">Venue</label>
        <input id="venue" name="venue" type="text" value="{{ old('venue', $event?->venue) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="organized_by" class="mb-2 block text-sm font-medium text-[#3d4757]">Organized By</label>
        <input id="organized_by" name="organized_by" type="text" value="{{ old('organized_by', $event?->organized_by) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="event_date" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Date</label>
        <input id="event_date" name="event_date" type="date" value="{{ old('event_date', $event?->event_date?->format('Y-m-d')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="registration_deadline" class="mb-2 block text-sm font-medium text-[#3d4757]">Registration Deadline</label>
        <input id="registration_deadline" name="registration_deadline" type="date" value="{{ old('registration_deadline', $event?->registration_deadline?->format('Y-m-d')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="start_time" class="mb-2 block text-sm font-medium text-[#3d4757]">Start Time</label>
        <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $event?->start_time?->format('H:i')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="end_time" class="mb-2 block text-sm font-medium text-[#3d4757]">End Time</label>
        <input id="end_time" name="end_time" type="time" value="{{ old('end_time', $event?->end_time?->format('H:i')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
        <select id="status" name="status"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
            @foreach (['draft', 'published', 'ongoing', 'completed', 'upcoming'] as $status)
                <option value="{{ $status }}" @selected(old('status', $event?->status ?? 'upcoming') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
    </div>

    @if (auth()->user()->isSuperAdmin())
        <div>
            <label for="manager_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Assigned Event Manager</label>
            <select id="manager_id" name="manager_id"
                class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">Unassigned</option>
                @foreach (($managers ?? collect()) as $manager)
                    <option value="{{ $manager->id }}" @selected((string) old('manager_id', $event?->manager_id) === (string) $manager->id)>
                        {{ $manager->name }}{{ $manager->email ? ' (' . $manager->email . ')' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="md:col-span-2">
        <label for="banner_image" class="mb-2 block text-sm font-medium text-[#3d4757]">Banner Image Path</label>
        <input id="banner_image" name="banner_image" type="text" value="{{ old('banner_image', $event?->banner_image) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
        Save Event
    </button>
    <a href="{{ route('admin.events.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
        Cancel
    </a>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const eventDate = document.getElementById('event_date');
            const status = document.getElementById('status');

            if (!eventDate || !status) {
                return;
            }

            const applyDateStatusRule = () => {
                const today = new Date();
                const selectedDate = new Date(`${eventDate.value}T00:00:00`);
                today.setHours(0, 0, 0, 0);

                if (eventDate.value && selectedDate < today && status.value === 'upcoming') {
                    status.value = 'completed';
                }
            };

            eventDate.addEventListener('change', applyDateStatusRule);
            status.addEventListener('change', applyDateStatusRule);
            applyDateStatusRule();
        });
    </script>
@endonce
