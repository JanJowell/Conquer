@props(['id', 'name', 'value' => null, 'hoursName', 'minutesName', 'hours' => null, 'minutes' => null])
@php
    $value = is_scalar($value) ? (string) $value : '';
    $presets = config('conquer.cutoff_duration_presets', []);
    $customValue = \App\Support\CutoffDuration::CUSTOM;
    $parsed = \App\Support\CutoffDuration::parse($value);
    $canonicalCustom = $parsed && ! in_array($value, $presets, true)
        && $value === \App\Support\CutoffDuration::format($parsed['hours'], $parsed['minutes']);
    $selection = $canonicalCustom ? $customValue : $value;
    $existingValue = $selection !== '' && $selection !== $customValue && ! in_array($selection, $presets, true);
    $hours = $hours ?? ($parsed['hours'] ?? 0);
    $minutes = $minutes ?? ($parsed['minutes'] ?? 0);
    $classes = 'h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none focus:ring-2 focus:ring-[#eef1f5]';
@endphp
<div data-cutoff-duration>
    <select id="{{ $id }}" name="{{ $name }}" data-duration-preset class="{{ $classes }}" aria-describedby="{{ $id }}-help">
        <option value="" @selected($selection === '')>Select cut-off duration</option>
        @foreach ($presets as $preset)
            <option value="{{ $preset }}" @selected($selection === $preset)>{{ $preset }}</option>
        @endforeach
        @if ($existingValue)
            <option value="{{ $value }}" selected>Existing: {{ $value }}</option>
        @endif
        <option value="{{ $customValue }}" @selected($selection === $customValue)>Custom duration</option>
    </select>
    <div data-duration-custom class="mt-3">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="{{ $id }}-hours" class="mb-2 block text-sm font-medium text-[#3d4757]">Hours</label>
                <input id="{{ $id }}-hours" name="{{ $hoursName }}" data-duration-hours type="number" min="0" max="1000000" step="1" inputmode="numeric" value="{{ $hours }}" class="{{ $classes }}" aria-describedby="{{ $id }}-help">
            </div>
            <div>
                <label for="{{ $id }}-minutes" class="mb-2 block text-sm font-medium text-[#3d4757]">Minutes</label>
                <input id="{{ $id }}-minutes" name="{{ $minutesName }}" data-duration-minutes type="number" min="0" max="59" step="1" inputmode="numeric" value="{{ $minutes }}" class="{{ $classes }}" aria-describedby="{{ $id }}-help">
            </div>
        </div>
    </div>
    <p id="{{ $id }}-help" class="mt-2 text-xs leading-5 text-[#6d7685]">For 9.5 hours, enter 9 hours and 30 minutes. The total must be greater than zero. Category start and end times are set separately.</p>
</div>
@once
    <script src="{{ asset('js/cutoff-duration.js') }}" defer></script>
@endonce
