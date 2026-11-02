@php
    $newVenue = ! $event || old('venue_mode', 'keep') !== 'keep';
    $currentVenue = $event?->venue_id ? $event->savedVenue : null;
    $currentDetail = $currentVenue ? implode(', ', array_filter([$currentVenue->name, $currentVenue->address], fn ($part) => $part !== '')) : '';
    // Do not truncate or guess details from a legacy/free-text venue.
    $currentDetail = \Illuminate\Support\Str::length($currentDetail) <= 100 ? $currentDetail : '';
    $venueFieldClasses = 'w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5] disabled:bg-slate-100 disabled:text-slate-500';
@endphp
<section class="md:col-span-2" data-venue-picker
    data-locations-url="{{ route('admin.venue-locations') }}"
    data-area="{{ old('venue_area_code', $currentVenue?->area_code ?? '') }}" data-city="{{ old('venue_city_code', $currentVenue?->city_code ?? '') }}" data-barangay="{{ old('venue_barangay_code', $currentVenue?->barangay_code ?? '') }}">
    <input type="hidden" name="venue_mode" data-venue-mode value="{{ $newVenue ? 'new' : 'keep' }}">
    @if ($event)
        <div class="mb-3">
            <p class="text-sm font-medium text-[#3d4757]">Current venue</p>
            <p class="mt-1 break-words text-sm text-[#6d7685]">{{ $event->venue }}</p>
            <button type="button" data-venue-change @if ($newVenue) hidden @endif @disabled(! ($venueLocationsAvailable ?? false)) class="mt-2 text-sm font-medium underline disabled:text-slate-400">Change venue</button>
            <button type="button" data-venue-cancel @if (! $newVenue) hidden @endif class="mt-2 text-sm font-medium underline">Keep current venue</button>
        </div>
    @endif
    @error('venue_mode') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
    @error('venue_id') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
    @error('venue') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

    @if (! ($venueLocationsAvailable ?? false))
        <p class="mt-3 text-sm text-amber-800">The local Philippine location list needs to be installed before entering a new venue. Existing event venues can still be kept.</p>
    @endif
    <noscript><p class="mt-3 text-sm text-amber-800">Enable JavaScript to use the province, city/municipality, and barangay dropdowns.</p></noscript>

    <fieldset data-venue-new class="grid gap-4 rounded-2xl bg-[#f7f8fa] p-4 md:grid-cols-3" @disabled(! $newVenue || ! ($venueLocationsAvailable ?? false)) @if (! $newVenue) hidden @endif>
        <legend class="sr-only">Venue</legend>
        <div class="md:col-span-3">
            <label for="venue_name" class="mb-2 block text-sm font-medium text-[#3d4757]">Venue / Landmark <span class="text-rose-500">*</span></label>
            <input id="venue_name" name="venue_name" data-venue-name type="text" maxlength="100" required value="{{ old('venue_name', $currentDetail) }}" placeholder="e.g. Municipal Hall entrance, Mabini Street" class="h-12 {{ $venueFieldClasses }}">
            @error('venue_name') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="venue_area_code" class="mb-2 block text-sm font-medium text-[#3d4757]">Province / Area <span class="text-rose-500">*</span></label>
            <select id="venue_area_code" name="venue_area_code" data-venue-area required disabled class="h-12 {{ $venueFieldClasses }}"><option value="">Select province or area</option></select>
            @error('venue_area_code') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="venue_city_code" class="mb-2 block text-sm font-medium text-[#3d4757]">City / Municipality <span class="text-rose-500">*</span></label>
            <select id="venue_city_code" name="venue_city_code" data-venue-city required disabled class="h-12 {{ $venueFieldClasses }}"><option value="">Select province or area first</option></select>
            @error('venue_city_code') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="venue_barangay_code" class="mb-2 block text-sm font-medium text-[#3d4757]">Barangay <span class="text-rose-500">*</span></label>
            <select id="venue_barangay_code" name="venue_barangay_code" data-venue-barangay required disabled class="h-12 {{ $venueFieldClasses }}"><option value="">Select city or municipality first</option></select>
            @error('venue_barangay_code') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <p class="text-xs text-[#6d7685] md:col-span-3">Location data: <a href="https://psa.gov.ph/classification/psgc" target="_blank" rel="noopener noreferrer" class="underline">PSA, PSGC</a></p>
    </fieldset>
    <p class="mt-3 text-sm text-[#6d7685] empty:hidden" role="status" aria-live="polite" data-venue-status></p>
    <button type="button" data-venue-retry hidden class="mt-2 rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-semibold">Retry loading locations</button>
    <p data-venue-preview class="mt-3 break-words text-sm font-medium text-[#3d4757] empty:hidden"></p>
</section>
@once
    <script src="{{ asset('js/venue-picker.js') }}" defer></script>
@endonce
