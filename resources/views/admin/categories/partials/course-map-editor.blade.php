@php
    $courseMapValue = $value ?? null;
    $courseMapJson = is_string($courseMapValue) ? $courseMapValue : ($courseMapValue ? json_encode($courseMapValue) : '');
    $courseMapDecoded = is_string($courseMapValue) ? json_decode($courseMapValue, true) : $courseMapValue;
    $courseMapPoints = is_array($courseMapDecoded['path'] ?? null) ? count($courseMapDecoded['path']) : 0;
    $courseMapCheckpoints = is_array($courseMapDecoded['checkpoints'] ?? null) ? count($courseMapDecoded['checkpoints']) : 0;
    $courseMapConfigured = filled(config('services.google_maps.browser_key'));
@endphp

<div class="md:col-span-2 min-w-0 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4"
    data-course-map-editor data-api-key="{{ config('services.google_maps.browser_key') }}">
    <input type="hidden" name="{{ $name ?? 'course_map' }}" value="{{ $courseMapJson }}" data-course-map-value>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-[#151b26]">Course Map Editor <span class="font-normal text-[#7a8495]">(optional)</span></h3>
            <p class="mt-1 text-xs leading-5 text-[#6d7685]" data-course-map-summary>
                {{ $courseMapPoints ? $courseMapPoints.' route points and '.$courseMapCheckpoints.' checkpoints. Saved with this category.' : 'No drawn course yet. Your uploaded map image remains available separately.' }}
            </p>
        </div>
        <button type="button" data-course-action="open" @disabled(! $courseMapConfigured)
            class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
            Open Map Editor
        </button>
    </div>

    @unless ($courseMapConfigured)
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">The map editor is not configured yet. Add the restricted GOOGLE_MAPS_BROWSER_KEY to the server environment. Image uploads still work.</p>
    @endunless

    @error($errorKey ?? 'course_map')
        <p class="mt-2 text-sm text-rose-700" role="alert">{{ $message }}</p>
    @enderror
    <p class="mt-2 text-sm text-[#3d4757]" data-course-map-status role="status" aria-live="polite"></p>

    <div data-course-map-panel hidden>
        <p class="mt-3 text-sm leading-6 text-[#6d7685]">Draw the actual course in order. The first point is Start and the last point is Finish. Drag the line handles to adjust it; right-click a route point to remove it. For a loop, finish at the starting location. This does not calculate a road route or change the category distance.</p>
        <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Course map tools">
            <button type="button" data-course-action="draw" aria-pressed="true" class="min-h-11 rounded-xl border border-indigo-200 bg-white px-3 py-2 text-sm font-semibold text-indigo-700">Draw Course</button>
            <button type="button" data-course-action="checkpoint" aria-pressed="false" class="min-h-11 rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm font-semibold text-[#3d4757]">Add Checkpoint</button>
            <button type="button" data-course-action="preview" aria-pressed="false" class="min-h-11 rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm font-semibold text-[#3d4757]">Preview</button>
            <button type="button" data-course-action="undo" class="min-h-11 rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm font-semibold text-[#3d4757]">Undo</button>
            <button type="button" data-course-action="close-loop" class="min-h-11 rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm font-semibold text-[#3d4757]">Finish at Start</button>
            <button type="button" data-course-action="fit" class="min-h-11 rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm font-semibold text-[#3d4757]">Fit Course</button>
            <button type="button" data-course-action="clear" class="min-h-11 rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700">Clear Draft</button>
        </div>
        <div class="mt-3 overflow-hidden rounded-xl border border-[#d9dee7] bg-white" data-course-map-canvas style="height: 420px; width: 100%;" role="region" aria-label="Interactive category course map"></div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-sm font-medium text-[#3d4757]">
                Map center latitude
                <input type="text" inputmode="decimal" data-course-center-lat value="14.46" class="mt-1 min-h-11 w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-[#3d4757]">
                Map center longitude
                <input type="text" inputmode="decimal" data-course-center-lng value="120.96" class="mt-1 min-h-11 w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2">
            </label>
        </div>
        <button type="button" data-course-action="center" class="mt-2 min-h-11 rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm font-semibold text-[#3d4757]">Go to Coordinates</button>
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">The initial view is Bacoor. Pan or enter coordinates to locate another venue. No location-search API is used.</p>

        <div class="mt-4">
            <h4 class="text-sm font-semibold text-[#151b26]">Checkpoints</h4>
            <p class="mt-1 text-xs leading-5 text-[#6d7685]">Choose Add Checkpoint, then click the map or course. Labels and numbers appear in the preview.</p>
            <div data-course-checkpoints class="mt-2 space-y-2"></div>
        </div>
        <label class="mt-4 flex items-start gap-2 text-sm leading-6 text-[#3d4757]">
            <input type="checkbox" data-course-reviewed class="mt-1 h-4 w-4 shrink-0 rounded border-[#c8cfda]">
            <span>I have reviewed this course and its Start, Finish, and checkpoints.</span>
        </label>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" data-course-action="apply" class="min-h-11 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Use This Route</button>
            <button type="button" data-course-action="discard" class="min-h-11 rounded-xl border border-[#d9dee7] bg-white px-4 py-2 text-sm font-semibold text-[#3d4757]">Discard Draft / Close</button>
        </div>
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Use This Route stages your changes. Save the event or category form to persist them. This prototype does not replace the map image shown in the participant app.</p>
    </div>

    <button type="button" data-course-action="remove" @if (! $courseMapPoints && ! $courseMapJson) hidden @endif
        class="mt-3 min-h-11 rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700">Remove Saved Route</button>
</div>

@once
    @push('scripts')
        <script type="module" src="{{ asset('js/category-course-map.js') }}?v={{ filemtime(public_path('js/category-course-map.js')) }}"></script>
    @endpush
@endonce
