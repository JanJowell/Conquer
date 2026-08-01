@php
    $fieldIdPrefix = $fieldIdPrefix ?? '';
    $useOldInput = $useOldInput ?? true;
    $fieldValue = fn (string $field, mixed $fallback = null) => $useOldInput ? old($field, $fallback) : $fallback;
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="{{ $fieldIdPrefix }}title" class="mb-2 block text-sm font-medium text-[#111827]">Title</label>
        <input
            type="text"
            name="title"
            id="{{ $fieldIdPrefix }}title"
            required
            value="{{ $fieldValue('title', $module?->title) }}"
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >
        @error('title')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}type" class="mb-2 block text-sm font-medium text-[#111827]">Type</label>
        <select
            name="type"
            id="{{ $fieldIdPrefix }}type"
            required
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >
            @foreach (['warmup' => 'Warmup', 'safety' => 'Safety', 'guideline' => 'Guideline', 'program' => 'Program'] as $value => $label)
                <option value="{{ $value }}" {{ $fieldValue('type', $module?->type) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}interest_type" class="mb-2 block text-sm font-medium text-[#111827]">Training Focus</label>
        <select
            name="interest_type"
            id="{{ $fieldIdPrefix }}interest_type"
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >
            <option value="">General training</option>
            @foreach (($interestTypes ?? []) as $interestType)
                <option value="{{ $interestType }}" {{ $fieldValue('interest_type', $module?->interest_type) === $interestType ? 'selected' : '' }}>
                    {{ $interestType }}
                </option>
            @endforeach
        </select>
        @error('interest_type')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}difficulty_level" class="mb-2 block text-sm font-medium text-[#111827]">Difficulty Level</label>
        <select
            name="difficulty_level"
            id="{{ $fieldIdPrefix }}difficulty_level"
            required
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >
            @foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label)
                <option value="{{ $value }}" {{ $fieldValue('difficulty_level', $module?->difficulty_level) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('difficulty_level')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}duration" class="mb-2 block text-sm font-medium text-[#111827]">Duration in Minutes</label>
        <input
            type="number"
            name="duration"
            id="{{ $fieldIdPrefix }}duration"
            min="1"
            value="{{ $fieldValue('duration', $module?->duration) }}"
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >
        @error('duration')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-3 pt-8">
        <input
            type="hidden"
            name="is_published"
            value="0"
        >
        <input
            type="checkbox"
            name="is_published"
            id="{{ $fieldIdPrefix }}is_published"
            value="1"
            {{ $fieldValue('is_published', $module?->is_published) ? 'checked' : '' }}
            class="h-4 w-4 rounded border-[#cfd5de] text-[#111827] focus:ring-[#d9dee7]"
        >
        <label for="{{ $fieldIdPrefix }}is_published" class="text-sm font-medium text-[#111827]">Publish this module</label>
    </div>

    <div class="md:col-span-2">
        <label for="{{ $fieldIdPrefix }}description" class="mb-2 block text-sm font-medium text-[#111827]">Description</label>
        <textarea
            name="description"
            id="{{ $fieldIdPrefix }}description"
            rows="4"
            required
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >{{ $fieldValue('description', $module?->description) }}</textarea>
        @error('description')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="{{ $fieldIdPrefix }}content" class="mb-2 block text-sm font-medium text-[#111827]">Content</label>
        <textarea
            name="content"
            id="{{ $fieldIdPrefix }}content"
            rows="10"
            required
            class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
        >{{ $fieldValue('content', $module?->content) }}</textarea>
        @error('content')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>
