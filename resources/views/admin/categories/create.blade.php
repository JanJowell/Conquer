@extends('admin.layouts.app')

@section('title', 'Create Category')

@section('content')
    @php
        $selectedEventId = (string) old('event_id', request('event_id'));
        $selectedCategoryEvent = $events->first(fn ($item) => (string) $item->id === $selectedEventId) ?? $events->first();
        $initialCategoryLabel = $selectedCategoryEvent?->categorySectionLabel() ?? 'Registration Categories';
        $categoryLabelsByEvent = $events->mapWithKeys(fn ($item) => [(string) $item->id => $item->categorySectionLabel()]);
        $eventStartDates = $events->mapWithKeys(fn ($item) => [(string) $item->id => $item->event_date?->format('Y-m-d')]);
        $eventStartTimes = $events->mapWithKeys(fn ($item) => [(string) $item->id => $item->start_time?->format('H:i')]);
        $eventEndTimes = $events->mapWithKeys(fn ($item) => [(string) $item->id => $item->end_time?->format('H:i')]);
        $eventTypes = $events->mapWithKeys(fn ($item) => [(string) $item->id => $item->interest_type]);
        $categoryTypeDetailSchemas = config('conquer.event_category_type_details', []);
        $eventCategoryTypeDetails = $events->mapWithKeys(function ($item) use ($categoryTypeDetailSchemas) {
            $detailKeys = array_keys($categoryTypeDetailSchemas[$item->interest_type] ?? []);

            return [(string) $item->id => collect($item->type_details ?? [])->only($detailKeys)->all()];
        });
        $selectedEventType = $selectedCategoryEvent?->interest_type;
        $usesSegmentedDistances = in_array($selectedEventType, ['Triathlon', 'Duathlon'], true);
    @endphp
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Setup</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Add to <span data-category-page-heading>{{ $initialCategoryLabel }}</span></h1>
            <p class="mt-2 text-sm text-[#6d7685]">Create a distance-based registration option for the selected event.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                    <select id="event_id" name="event_id" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected((string) old('event_id', request('event_id')) === (string) $event->id)>{{ $event->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category_type" class="mb-2 block text-sm font-medium text-[#3d4757]">Category Type</label>
                    <select id="category_type" name="category_type" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        @foreach ([
                            'open' => 'Open',
                            'male' => 'Male',
                            'female' => 'Female',
                            'elite' => 'Elite',
                            'beginner' => 'Beginner',
                            'kids' => 'Kids',
                            'senior' => 'Senior',
                            'custom' => 'Custom',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('category_type', 'open') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-[#6d7685]">The saved name combines distance and type, like 5K Open.</p>
                </div>

                <div id="custom-category-wrapper" class="{{ old('category_type', 'open') === 'custom' ? '' : 'hidden' }}">
                    <label for="custom_category_name" class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Type</label>
                    <input id="custom_category_name" name="custom_category_name" type="text" value="{{ old('custom_category_name') }}" placeholder="Trail, Family, Corporate"
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div data-standard-distance class="{{ $usesSegmentedDistances ? 'hidden' : '' }}">
                    <label for="distance_option" class="mb-2 block text-sm font-medium text-[#3d4757]">Distance</label>
                    <select id="distance_option" name="distance_option" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        @foreach ([
                            '1' => '1K',
                            '3' => '3K',
                            '5' => '5K',
                            '10' => '10K',
                            '21' => '21K',
                            '42' => '42K',
                            'custom' => 'Custom',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('distance_option', '5') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="custom-distance-wrapper" data-standard-distance class="{{ $usesSegmentedDistances || old('distance_option', '5') !== 'custom' ? 'hidden' : '' }}">
                    <label for="custom_distance_km" class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance (km)</label>
                    <input id="custom_distance_km" name="custom_distance_km" type="number" step="0.01" min="0.01" value="{{ old('custom_distance_km') }}"
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                @foreach ($categoryTypeDetailSchemas as $eventType => $detailSchema)
                    <div data-category-type-details="{{ $eventType }}" class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4 {{ $selectedEventType === $eventType ? '' : 'hidden' }}">
                        <p class="mb-4 text-sm font-semibold text-[#151b26]">{{ $eventType }} Category Details</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach ($detailSchema as $detailKey => $definition)
                                @php
                                    $detailValue = old("type_details.{$detailKey}",
                                        $selectedCategoryEvent?->interest_type === $eventType
                                            ? data_get($selectedCategoryEvent?->type_details, $detailKey)
                                            : null
                                    );
                                    $detailRequired = in_array('required', $definition['rules'] ?? [], true);
                                @endphp
                                <div class="{{ ($definition['type'] ?? 'number') === 'textarea' ? 'md:col-span-3' : '' }}">
                                    <label for="type_details_{{ str($eventType)->slug('_') }}_{{ $detailKey }}" class="mb-2 block text-sm font-medium text-[#3d4757]">{{ $definition['label'] }}</label>
                                    @if (($definition['type'] ?? 'number') === 'textarea')
                                        <textarea id="type_details_{{ str($eventType)->slug('_') }}_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" rows="3" data-category-detail-key="{{ $detailKey }}"
                                            placeholder="{{ $definition['placeholder'] ?? '' }}" @if ($detailRequired) required @endif
                                            class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $detailValue }}</textarea>
                                    @elseif (($definition['type'] ?? 'number') === 'select')
                                        <select id="type_details_{{ str($eventType)->slug('_') }}_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" data-category-detail-key="{{ $detailKey }}" @if ($detailRequired) required @endif
                                            class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                            <option value="">Select {{ strtolower($definition['label']) }}</option>
                                            @foreach (($definition['options'] ?? []) as $option)
                                                <option value="{{ $option }}" @selected($detailValue === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="relative">
                                            <input id="type_details_{{ str($eventType)->slug('_') }}_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" type="number" min="0.01" step="0.01" value="{{ $detailValue }}" data-category-detail-key="{{ $detailKey }}" @if ($detailRequired) required @endif
                                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 {{ isset($definition['suffix']) ? 'pr-12' : '' }} text-sm text-[#151b26] outline-none">
                                            @if (isset($definition['suffix']))
                                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-semibold text-[#7a8495]">{{ $definition['suffix'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if (in_array($eventType, ['Triathlon', 'Duathlon'], true))
                            <p class="mt-3 text-xs text-[#6d7685]">The total category distance is calculated automatically from these segments.</p>
                        @endif
                    </div>
                @endforeach

                <div>
                    <label for="scheduled_start_date" class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Date</label>
                    <input id="scheduled_start_date" name="scheduled_start_date" type="date" value="{{ old('scheduled_start_date', $selectedCategoryEvent?->event_date?->format('Y-m-d')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="scheduled_start_time" class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Time</label>
                    <input id="scheduled_start_time" name="scheduled_start_time" type="time" value="{{ old('scheduled_start_time', $selectedCategoryEvent?->start_time?->format('H:i')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <p class="mt-2 text-xs text-[#6d7685]">The Start Category button becomes available at this planned time.</p>
                </div>

                <div>
                    <label for="scheduled_end_date" class="mb-2 block text-sm font-medium text-[#3d4757]">End Date</label>
                    <input id="scheduled_end_date" name="scheduled_end_date" type="date" value="{{ old('scheduled_end_date', $selectedCategoryEvent?->event_date?->format('Y-m-d')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="scheduled_end_time" class="mb-2 block text-sm font-medium text-[#3d4757]">End Time</label>
                    <input id="scheduled_end_time" name="scheduled_end_time" type="time" value="{{ old('scheduled_end_time', $selectedCategoryEvent?->end_time?->format('H:i')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the overall event schedule.</p>
                </div>

                <div>
                    <label for="slot_limit" class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit</label>
                    <input id="slot_limit" name="slot_limit" type="number" min="1" value="{{ old('slot_limit') }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div class="rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                    <input type="hidden" name="requires_medical_certificate" value="0">
                    <label for="requires_medical_certificate" class="flex cursor-pointer items-start gap-3">
                        <input id="requires_medical_certificate" name="requires_medical_certificate" type="checkbox" value="1" @checked(old('requires_medical_certificate', false))
                            class="mt-1 h-4 w-4 rounded border-[#c8cfda] text-[#151b26] focus:ring-[#151b26]">
                        <span>
                            <span class="block text-sm font-semibold text-[#151b26]">Medical Certificate Required</span>
                            <span class="mt-1 block text-xs leading-5 text-[#6d7685]">Participants must upload a medical certificate when registering for this category.</span>
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                    <label for="checkpoint_map_image_upload" class="block text-sm font-semibold text-[#151b26]">Course / Checkpoint Map <span class="font-normal text-[#7a8495]">(optional)</span></label>
                    <input id="checkpoint_map_image_upload" name="checkpoint_map_image_upload" type="file" accept="image/jpeg,image/png,image/webp"
                        class="mt-3 block w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#3d4757] file:mr-3 file:rounded-lg file:border-0 file:bg-[#eef1f4] file:px-3 file:py-2 file:font-semibold file:text-[#151b26]">
                    <p class="mt-2 text-xs leading-5 text-[#6d7685]">Upload a JPG, PNG, or WebP route image up to 5 MB. It will be shown only for this category.</p>
                </div>

                <div>
                    <label for="price_amount" class="mb-2 block text-sm font-medium text-[#3d4757]">Registration Fee</label>
                    <div class="grid grid-cols-[1fr_88px] gap-2">
                        <input id="price_amount" name="price_amount" type="number" step="0.01" min="0" value="{{ old('price_amount', '0.00') }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        <input id="price_currency" name="price_currency" type="text" maxlength="3" value="{{ old('price_currency', 'PHP') }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-3 text-sm font-semibold uppercase text-[#151b26] outline-none">
                    </div>
                    <p class="mt-2 text-xs text-[#6d7685]">Use 0.00 for free registration.</p>
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

                <div class="md:col-span-2">
                    <label for="qualification_notes" class="mb-2 block text-sm font-medium text-[#3d4757]">Qualification / Eligibility Notes <span class="font-normal text-[#7a8495]">(optional)</span></label>
                    <textarea id="qualification_notes" name="qualification_notes" rows="4" maxlength="5000" placeholder="e.g. Must be at least 18 years old and have previous trail experience."
                        class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('qualification_notes') }}</textarea>
                    <p class="mt-2 text-xs text-[#6d7685]">Shown to participants before they register for this category.</p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Save Category
                </button>
                <a href="{{ route('admin.categories.index', array_filter(['event_id' => request('event_id')])) }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        const eventSelect = document.getElementById('event_id');
        const categoryPageHeading = document.querySelector('[data-category-page-heading]');
        const categoryLabelsByEvent = @json($categoryLabelsByEvent);
        const eventStartDates = @json($eventStartDates);
        const eventStartTimes = @json($eventStartTimes);
        const eventEndTimes = @json($eventEndTimes);
        const eventTypes = @json($eventTypes);
        const eventCategoryTypeDetails = @json($eventCategoryTypeDetails);
        const scheduledStartDate = document.getElementById('scheduled_start_date');
        const scheduledStartTime = document.getElementById('scheduled_start_time');
        const scheduledEndDate = document.getElementById('scheduled_end_date');
        const scheduledEndTime = document.getElementById('scheduled_end_time');
        const categoryType = document.getElementById('category_type');
        const customCategoryWrapper = document.getElementById('custom-category-wrapper');
        const distanceOption = document.getElementById('distance_option');
        const customDistanceWrapper = document.getElementById('custom-distance-wrapper');
        const standardDistanceFields = document.querySelectorAll('[data-standard-distance]');
        const categoryTypeDetailPanels = document.querySelectorAll('[data-category-type-details]');

        const refreshCategoryDistanceFields = () => {
            const eventType = eventTypes[eventSelect?.value] || '';
            const usesSegmentedDistances = ['Triathlon', 'Duathlon'].includes(eventType);

            standardDistanceFields.forEach((wrapper) => {
                const customWrapper = wrapper.id === 'custom-distance-wrapper';
                const visible = ! usesSegmentedDistances && (! customWrapper || distanceOption?.value === 'custom');
                wrapper.classList.toggle('hidden', ! visible);
                wrapper.querySelectorAll('input, select, textarea').forEach((field) => field.disabled = ! visible);
            });

            categoryTypeDetailPanels.forEach((panel) => {
                const active = panel.dataset.categoryTypeDetails === eventType;
                panel.classList.toggle('hidden', ! active);
                panel.querySelectorAll('input, select, textarea').forEach((field) => field.disabled = ! active);
            });
        };

        eventSelect?.addEventListener('change', () => {
            if (categoryPageHeading) {
                categoryPageHeading.textContent = categoryLabelsByEvent[eventSelect.value] || 'Registration Categories';
            }

            if (scheduledStartDate) {
                scheduledStartDate.value = eventStartDates[eventSelect.value] || '';
            }

            if (scheduledStartTime) {
                scheduledStartTime.value = eventStartTimes[eventSelect.value] || '';
            }

            if (scheduledEndDate) {
                scheduledEndDate.value = eventStartDates[eventSelect.value] || '';
            }

            if (scheduledEndTime) {
                scheduledEndTime.value = eventEndTimes[eventSelect.value] || '';
            }

            const legacyDetails = eventCategoryTypeDetails[eventSelect.value] || {};
            categoryTypeDetailPanels.forEach((panel) => {
                panel.querySelectorAll('[data-category-detail-key]').forEach((field) => {
                    field.value = legacyDetails[field.dataset.categoryDetailKey] || '';
                });
            });

            refreshCategoryDistanceFields();
        });

        categoryType?.addEventListener('change', () => {
            customCategoryWrapper?.classList.toggle('hidden', categoryType.value !== 'custom');
        });

        distanceOption?.addEventListener('change', () => {
            customDistanceWrapper?.classList.toggle('hidden', distanceOption.value !== 'custom');
            refreshCategoryDistanceFields();
        });

        refreshCategoryDistanceFields();
    </script>
@endsection
