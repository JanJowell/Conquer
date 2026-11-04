@once
    <link rel="stylesheet" href="{{ asset('css/event-review-submit.css') }}?v={{ filemtime(public_path('css/event-review-submit.css')) }}">
@endonce
@if ($event)
    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
@endif
@include('admin.events.partials.form-errors')
<p class="mb-5 hidden text-sm text-rose-700" role="alert" data-event-client-errors></p>
<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="interest_type" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Type <x-event-field-requirement kind="required" /></label>
        <select id="interest_type" name="interest_type"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
            <option value="">Select event type</option>
            @foreach (($interestTypes ?? []) as $interestType)
                <option value="{{ $interestType }}" @selected(old('interest_type', $event?->interest_type) === $interestType)>
                    {{ $interestType }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label for="title" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Name <x-event-field-requirement kind="required" /></label>
        <input id="title" name="title" type="text" value="{{ old('title', $event?->title) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-2 block text-sm font-medium text-[#3d4757]">Description <x-event-field-requirement kind="submission" /></label>
        <textarea id="description" name="description" rows="4"
            class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ old('description', $event?->description) }}</textarea>
    </div>

    @include('admin.events.partials.venue')

    <div>
        <label for="event_date" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Start Date <x-event-field-requirement kind="required" /></label>
        <input id="event_date" name="event_date" type="date" value="{{ old('event_date', $event?->event_date?->format('Y-m-d')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
    </div>

    <div>
        <label for="event_end_date" class="mb-2 block text-sm font-medium text-[#3d4757]">Event End Date <x-event-field-requirement kind="required" /></label>
        <input id="event_end_date" name="event_end_date" type="date" value="{{ old('event_end_date', $event?->event_end_date?->format('Y-m-d') ?? $event?->event_date?->format('Y-m-d')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Use the same date as the start for a one-day event.</p>
    </div>

    <div>
        <label for="registration_deadline" class="mb-2 block text-sm font-medium text-[#3d4757]">Registration Deadline <x-event-field-requirement kind="submission" /></label>
        <input id="registration_deadline" name="registration_deadline" type="date" value="{{ old('registration_deadline', $event?->registration_deadline?->format('Y-m-d')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Must be on or before the event date.</p>
    </div>

    <div>
        <label for="start_time" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Start Time <x-event-field-requirement kind="submission" /></label>
        <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $event?->start_time?->format('H:i')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Also needed when an event end time is entered, even when saving a draft.</p>
    </div>

    <div>
        <label for="end_time" class="mb-2 block text-sm font-medium text-[#3d4757]">Event End Time <x-event-field-requirement kind="submission" /></label>
        <input id="end_time" name="end_time" type="time" value="{{ old('end_time', $event?->end_time?->format('H:i')) }}"
            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Required for submission. The end date and time must be after the start date and time; overnight events can end the next day.</p>
    </div>

    @php
        $selectedEventType = old('interest_type', $event?->interest_type);
        $eventTypeDetailSchemas = config('conquer.event_type_details', []);
        $categoryTypeDetailSchemas = config('conquer.event_category_type_details', []);
        $categoryLabels = config('conquer.event_category_labels', []);
    @endphp

    <section class="md:col-span-2 rounded-3xl border border-[#d9dee7] bg-[#fafbfc] p-5">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-[#151b26]">Event Type Details</h2>
            <p class="mt-1 text-sm leading-6 text-[#6d7685]">These details change with the selected event type and are shown to participants in the mobile app.</p>
        </div>

        <div class="mt-4 rounded-2xl border border-dashed border-[#d9dee7] bg-white p-4 text-sm text-[#6d7685] {{ $selectedEventType ? 'hidden' : '' }}" data-event-type-empty>
            Select an event type to see its required details.
        </div>

        @foreach ($eventTypeDetailSchemas as $eventType => $detailSchema)
            @php
                $storedDetails = $event?->interest_type === $eventType && is_array($event?->type_details)
                    ? $event->type_details
                    : [];
            @endphp
            <div data-event-type-panel="{{ $eventType }}" class="mt-4 grid gap-4 md:grid-cols-2 {{ $selectedEventType === $eventType ? '' : 'hidden' }}">
                @foreach ($detailSchema as $detailKey => $definition)
                    @continue($definition['category_owned'] ?? false)
                    @php
                        $inputName = "type_details[{$eventType}][{$detailKey}]";
                        $inputId = 'type-detail-'.str($eventType.'-'.$detailKey)->slug();
                        $detailValue = old("type_details.{$eventType}.{$detailKey}", $storedDetails[$detailKey] ?? null);
                        $fieldClasses = 'w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]';
                    @endphp
                    <div class="{{ ($definition['type'] ?? null) === 'textarea' ? 'md:col-span-2' : '' }}">
                        <label for="{{ $inputId }}" class="mb-2 block text-sm font-medium text-[#3d4757]">
                            {{ $definition['label'] }}
                            <x-event-field-requirement :kind="($definition['type'] ?? '') === 'boolean' ? 'setting' : (in_array('required', $definition['rules'] ?? [], true) ? 'required' : (($definition['required_for_publication'] ?? false) ? 'submission' : 'optional'))" />
                        </label>

                        @if (($definition['type'] ?? null) === 'duration')
                            <x-cutoff-duration :id="$inputId" :name="$inputName" :value="$detailValue"
                                :hours-name="'type_details['.$eventType.']['.$detailKey.'_hours]'"
                                :minutes-name="'type_details['.$eventType.']['.$detailKey.'_minutes]'"
                                :hours="old('type_details.'.$eventType.'.'.$detailKey.'_hours')"
                                :minutes="old('type_details.'.$eventType.'.'.$detailKey.'_minutes')" :show-requirements="true" />
                            @error("type_details.{$eventType}.{$detailKey}_hours")
                                <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                            @error("type_details.{$eventType}.{$detailKey}_minutes")
                                <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        @elseif (($definition['type'] ?? null) === 'select')
                            <select id="{{ $inputId }}" name="{{ $inputName }}" class="h-12 {{ $fieldClasses }}">
                                <option value="">Select {{ strtolower($definition['label']) }}</option>
                                @foreach (($definition['options'] ?? []) as $option)
                                    <option value="{{ $option }}" @selected($detailValue === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif (($definition['type'] ?? null) === 'textarea')
                            <textarea id="{{ $inputId }}" name="{{ $inputName }}" rows="3" class="py-3 {{ $fieldClasses }}" placeholder="{{ $definition['placeholder'] ?? '' }}">{{ $detailValue }}</textarea>
                        @elseif (($definition['type'] ?? null) === 'boolean')
                            <input type="hidden" name="{{ $inputName }}" value="0">
                            <label class="flex min-h-12 items-center gap-3 rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26]">
                                <input id="{{ $inputId }}" name="{{ $inputName }}" type="checkbox" value="1" @checked((bool) $detailValue) class="h-4 w-4 rounded border-[#aeb7c3] text-[#151b26] focus:ring-[#aeb7c3]">
                                Helmets are mandatory for participants
                            </label>
                        @else
                            <div class="relative">
                                <input id="{{ $inputId }}" name="{{ $inputName }}" type="{{ ($definition['type'] ?? null) === 'number' ? 'number' : 'text' }}"
                                    @if (($definition['type'] ?? null) === 'number') min="0" step="0.01" @endif
                                    value="{{ $detailValue }}" placeholder="{{ $definition['placeholder'] ?? '' }}"
                                    class="h-12 {{ $fieldClasses }} {{ isset($definition['suffix']) ? 'pr-14' : '' }}">
                                @if (isset($definition['suffix']))
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-semibold text-[#7a8495]">{{ $definition['suffix'] }}</span>
                                @endif
                            </div>
                        @endif

                        @error("type_details.{$eventType}.{$detailKey}")
                            <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        @endforeach

        <p class="mt-4 text-xs leading-5 text-[#6d7685]">Requirements apply only to the selected event type. Checkbox settings do not have to be enabled.</p>
    </section>

    @include('admin.events.partials.payment-selection')

    @php
        $categoryRows = old('categories', $categoryRows ?? null);
        if ($categoryRows === null) {
            $categoryRows = [[
                'category_type' => '',
                'custom_category_name' => '',
                'distance_option' => '',
                'custom_distance_km' => '',
                'distance_unit' => 'km',
                'scheduled_start_date' => old('event_date', $event?->event_date?->format('Y-m-d')),
                'scheduled_start_time' => '',
                'scheduled_end_date' => old('event_date', $event?->event_date?->format('Y-m-d')),
                'scheduled_end_time' => '',
                'slot_limit' => '',
                'group_min_members' => '',
                'group_max_members' => '',
                'price_amount' => '0.00',
                'price_currency' => 'PHP',
                'status' => 'open',
                'description' => '',
                'qualification_notes' => '',
                'requires_medical_certificate' => false,
            ]];
        }
    @endphp

    <div class="md:col-span-2 rounded-3xl border border-[#d9dee7] bg-[#fafbfc] p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]" data-category-heading>{{ $categoryLabels[$selectedEventType] ?? 'Registration Categories' }}</h2>
                <p class="mt-1 text-sm leading-6 text-[#6d7685]">Complete the required fields for each category you add. At least one open category is needed before submission.</p>
            </div>
            <button type="button" data-add-category class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                Add Category
            </button>
        </div>

        <div data-category-list class="mt-4 space-y-4">
            @foreach ($categoryRows as $index => $categoryRow)
                @php
                    $existingCategory = filled($categoryRow['id'] ?? null)
                        ? $event?->categories->firstWhere('id', (int) $categoryRow['id'])
                        : null;
                    $existingCategoryInUse = $existingCategory
                        && (($existingCategory->registrations_count ?? 0) > 0 || ($existingCategory->race_results_count ?? 0) > 0);
                @endphp
                <div data-category-row class="rounded-2xl border border-[#d9dee7] bg-white p-4">
                    @if ($existingCategory)
                        <input type="hidden" name="categories[{{ $index }}][id]" value="{{ $existingCategory->id }}">
                    @endif
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-[#151b26]">
                            {{ $existingCategory ? 'Existing' : 'New' }} Category <span data-category-number>{{ $index + 1 }}</span>
                            @if ($existingCategory)
                                <span class="ml-1 font-normal text-[#6d7685]">— {{ $existingCategory->name }}</span>
                            @endif
                        </p>
                        @if ($existingCategory)
                            <a href="{{ route('admin.categories.edit', $existingCategory) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-[#d9dee7] px-3 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Open separately</a>
                        @else
                            <button type="button" data-remove-category class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                Remove
                            </button>
                        @endif
                    </div>

                    @if ($existingCategoryInUse || $existingCategory?->started_at)
                        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                            @if ($existingCategoryInUse)
                                Category type, distance, group limits, and medical-certificate requirement are preserved because this category already has registrations or results.
                            @endif
                            @if ($existingCategory?->started_at)
                                The gun-start and end schedule is preserved because this category has already started.
                            @endif
                        </div>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category Type <x-event-field-requirement kind="required" /></label>
                            <select name="categories[{{ $index }}][category_type]" data-category-type class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <option value="">Select type</option>
                                @foreach (($categoryTypes ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($categoryRow['category_type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div data-custom-category-wrapper class="{{ ($categoryRow['category_type'] ?? '') === 'custom' ? '' : 'hidden' }}">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Type <x-event-field-requirement kind="conditional">Required for Custom</x-event-field-requirement></label>
                            <input name="categories[{{ $index }}][custom_category_name]" type="text" value="{{ $categoryRow['custom_category_name'] ?? '' }}" placeholder="Trail, Family, Corporate"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div data-standard-distance>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Distance <x-event-field-requirement kind="required" /></label>
                            <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-2">
                                <select name="categories[{{ $index }}][distance_option]" data-distance-option class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                    <option value="">Select distance</option>
                                    @foreach (($distanceOptions ?? []) as $value => $label)
                                        <option value="{{ $value }}" @selected((string) ($categoryRow['distance_option'] ?? '') === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select name="categories[{{ $index }}][distance_unit]" data-distance-unit aria-label="Distance unit" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-3 text-sm font-semibold text-[#151b26] outline-none">
                                    <option value="km" @selected(($categoryRow['distance_unit'] ?? 'km') === 'km')>km</option>
                                    <option value="mi" @selected(($categoryRow['distance_unit'] ?? 'km') === 'mi')>miles</option>
                                </select>
                            </div>
                        </div>

                        <div data-standard-distance data-custom-distance-wrapper class="{{ ($categoryRow['distance_option'] ?? '') === 'custom' ? '' : 'hidden' }}">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance <x-event-field-requirement kind="conditional">Required for Custom</x-event-field-requirement></label>
                            <input name="categories[{{ $index }}][custom_distance_km]" type="number" step="0.01" min="0.01" value="{{ $categoryRow['custom_distance_km'] ?? '' }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        @foreach ($categoryTypeDetailSchemas as $eventType => $detailSchema)
                            <div data-category-type-details="{{ $eventType }}" class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4 {{ $selectedEventType === $eventType ? '' : 'hidden' }}">
                                <p class="mb-4 text-sm font-semibold text-[#151b26]">{{ $eventType }} Category Details</p>
                                <div class="grid gap-4 md:grid-cols-3">
                                    @foreach ($detailSchema as $detailKey => $definition)
                                        @php
                                            $legacyDetailValue = $event?->interest_type === $eventType
                                                ? data_get($event?->type_details, $detailKey)
                                                : null;
                                            $detailValue = data_get($categoryRow, "type_details.{$detailKey}", $legacyDetailValue);
                                            $detailRequired = in_array('required', $definition['rules'] ?? [], true);
                                        @endphp
                                        <div class="{{ ($definition['type'] ?? 'number') === 'textarea' ? 'md:col-span-3' : '' }}">
                                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">{{ $definition['label'] }} <x-event-field-requirement :kind="$detailRequired ? 'required' : (($definition['required_for_publication'] ?? false) ? 'submission' : 'optional')" /></label>
                                            @if (($definition['type'] ?? 'number') === 'textarea')
                                                <textarea name="categories[{{ $index }}][type_details][{{ $detailKey }}]" rows="3" placeholder="{{ $definition['placeholder'] ?? '' }}" @if ($detailRequired) required @endif
                                                    class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $detailValue }}</textarea>
                                            @elseif (($definition['type'] ?? 'number') === 'select')
                                                <select name="categories[{{ $index }}][type_details][{{ $detailKey }}]" @if ($detailRequired) required @endif
                                                    class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                                    <option value="">Select {{ strtolower($definition['label']) }}</option>
                                                    @foreach (($definition['options'] ?? []) as $option)
                                                        <option value="{{ $option }}" @selected($detailValue === $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <div class="relative">
                                                    <input name="categories[{{ $index }}][type_details][{{ $detailKey }}]" type="number" min="0.01" step="0.01" value="{{ $detailValue }}" @if ($detailRequired) required @endif
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
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Date <x-event-field-requirement kind="required" /></label>
                            <input name="categories[{{ $index }}][scheduled_start_date]" type="date" value="{{ $categoryRow['scheduled_start_date'] ?? '' }}" required
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Time <x-event-field-requirement kind="required" /></label>
                            <input name="categories[{{ $index }}][scheduled_start_time]" type="time" value="{{ $categoryRow['scheduled_start_time'] ?? '' }}" required
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <p class="mt-2 text-xs text-[#6d7685]">Planned wave time; the Start button records the actual server time.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">End Date <x-event-field-requirement kind="required" /></label>
                            <input name="categories[{{ $index }}][scheduled_end_date]" type="date" value="{{ $categoryRow['scheduled_end_date'] ?? '' }}" required
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">End Time <x-event-field-requirement kind="required" /></label>
                            <input name="categories[{{ $index }}][scheduled_end_time]" type="time" value="{{ $categoryRow['scheduled_end_time'] ?? '' }}" required
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the event schedule.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit <x-event-field-requirement kind="submission" /></label>
                            <input name="categories[{{ $index }}][slot_limit]" type="number" min="1" value="{{ $categoryRow['slot_limit'] ?? '' }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div data-group-setting @if($existingCategoryInUse) data-group-setting-locked @endif class="{{ ($categoryRow['category_type'] ?? '') === 'group' ? '' : 'hidden' }}">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Minimum Group Members <x-event-field-requirement kind="conditional">Required for groups</x-event-field-requirement></label>
                            @if ($existingCategoryInUse)
                                <input type="hidden" name="categories[{{ $index }}][group_min_members]" value="{{ $categoryRow['group_min_members'] ?? '' }}">
                            @endif
                            <input name="categories[{{ $index }}][group_min_members]" type="number" min="2" max="100" value="{{ $categoryRow['group_min_members'] ?? '' }}" placeholder="At least 2" @disabled($existingCategoryInUse)
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none disabled:cursor-not-allowed disabled:bg-[#f8f9fb] disabled:text-[#7a8495]">
                        </div>

                        <div data-group-setting @if($existingCategoryInUse) data-group-setting-locked @endif class="{{ ($categoryRow['category_type'] ?? '') === 'group' ? '' : 'hidden' }}">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Maximum Group Members <x-event-field-requirement kind="conditional">Required for groups</x-event-field-requirement></label>
                            @if ($existingCategoryInUse)
                                <input type="hidden" name="categories[{{ $index }}][group_max_members]" value="{{ $categoryRow['group_max_members'] ?? '' }}">
                            @endif
                            <input name="categories[{{ $index }}][group_max_members]" type="number" min="2" max="100" value="{{ $categoryRow['group_max_members'] ?? '' }}" placeholder="2 to 100" @disabled($existingCategoryInUse)
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none disabled:cursor-not-allowed disabled:bg-[#f8f9fb] disabled:text-[#7a8495]">
                            <p class="mt-2 text-xs text-[#6d7685]">Each member receives a separate registration, BIB, result, and certificate.</p>
                        </div>

                        <div class="rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                            <input type="hidden" name="categories[{{ $index }}][requires_medical_certificate]" value="0">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input name="categories[{{ $index }}][requires_medical_certificate]" type="checkbox" value="1" @checked(filter_var($categoryRow['requires_medical_certificate'] ?? false, FILTER_VALIDATE_BOOLEAN))
                                    class="mt-1 h-4 w-4 rounded border-[#c8cfda] text-[#151b26] focus:ring-[#151b26]">
                                <span>
                                    <span class="block text-sm font-semibold text-[#151b26]">Medical Certificate Required <x-event-field-requirement kind="setting" /></span>
                                    <span class="mt-1 block text-xs leading-5 text-[#6d7685]">Require participants to upload one when registering.</span>
                                </span>
                            </label>
                        </div>

                        <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4" data-category-map-requirement data-saved-map-image="{{ filled($existingCategory?->checkpoint_map_image) ? 'true' : 'false' }}" tabindex="-1" role="group" aria-label="Required course or checkpoint map">
                            <label for="category-{{ $index }}-map-upload" class="block text-sm font-semibold text-[#151b26]">Course / Checkpoint Map <x-event-field-requirement kind="submission" /></label>
                            <p class="mt-2 text-xs leading-5 text-[#6d7685]">For submission, upload a map image or review and apply a drawn course below. Either option is enough; a saved map also counts.</p>
                            @if ($existingCategory)
                                @if ($existingCategory->checkpoint_map_image)
                                    <a href="{{ asset('storage/'.$existingCategory->checkpoint_map_image) }}" target="_blank" rel="noopener" class="mt-3 block overflow-hidden rounded-2xl border border-[#d9dee7] bg-white">
                                        <img src="{{ asset('storage/'.$existingCategory->checkpoint_map_image) }}" alt="{{ $existingCategory->name }} course and checkpoint map" class="max-h-64 w-full object-contain">
                                    </a>
                                @else
                                    <p class="mt-2 text-xs text-[#6d7685]">No map is currently uploaded.</p>
                                @endif
                                <p class="mt-2 text-xs leading-5 text-[#6d7685]">Upload below to replace the saved image, or use “Open separately” to remove it.</p>
                            @endif
                            <input id="category-{{ $index }}-map-upload" name="categories[{{ $index }}][checkpoint_map_image_upload]" type="file" accept="image/jpeg,image/png,image/webp"
                                class="mt-3 block w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#3d4757] file:mr-3 file:rounded-lg file:border-0 file:bg-[#eef1f4] file:px-3 file:py-2 file:font-semibold file:text-[#151b26]">
                            <p class="mt-2 text-xs leading-5 text-[#6d7685]">JPG, PNG, or WebP up to 5 MB. This map belongs only to this category. Leave empty to keep any saved image.</p>
                        </div>

                        @include('admin.categories.partials.course-map-editor', [
                            'name' => 'categories['.$index.'][course_map]',
                            'errorKey' => 'categories.'.$index.'.course_map',
                            'value' => $categoryRow['course_map'] ?? null,
                            'mapRequirement' => true,
                        ])

                        <div>
                            <div class="grid grid-cols-[1fr_88px] gap-2">
                                <label for="category-{{ $index }}-price" class="block text-sm font-medium text-[#3d4757]">Registration Fee <x-event-field-requirement /></label>
                                <label for="category-{{ $index }}-currency" class="block text-sm font-medium text-[#3d4757]">Currency <x-event-field-requirement kind="required" /></label>
                                <input id="category-{{ $index }}-price" name="categories[{{ $index }}][price_amount]" type="number" step="0.01" min="0" value="{{ $categoryRow['price_amount'] ?? '0.00' }}"
                                    class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <input id="category-{{ $index }}-currency" name="categories[{{ $index }}][price_currency]" type="text" maxlength="3" value="{{ $categoryRow['price_currency'] ?? 'PHP' }}"
                                    class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-3 text-sm font-semibold uppercase text-[#151b26] outline-none">
                            </div>
                            <p class="mt-2 text-xs text-[#6d7685]">Use 0.00 for free registration.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Status <x-event-field-requirement kind="required" /></label>
                            <select name="categories[{{ $index }}][status]" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                @foreach (['open', 'closed', 'draft'] as $status)
                                    <option value="{{ $status }}" @selected(($categoryRow['status'] ?? 'open') === $status)>{{ str($status)->title() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Description <x-event-field-requirement /></label>
                            <textarea name="categories[{{ $index }}][description]" rows="3" class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $categoryRow['description'] ?? '' }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Qualification / Eligibility Notes <x-event-field-requirement /></label>
                            <textarea name="categories[{{ $index }}][qualification_notes]" rows="3" maxlength="5000" placeholder="e.g. Must be at least 18 years old and have previous race experience."
                                class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $categoryRow['qualification_notes'] ?? '' }}</textarea>
                            <p class="mt-2 text-xs text-[#6d7685]">Shown to participants before they register for this category.</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <template data-category-template>
            <div data-category-row class="rounded-2xl border border-[#d9dee7] bg-white p-4">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-[#151b26]">New Category <span data-category-number></span></p>
                    <button type="button" data-remove-category class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                        Remove
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category Type <x-event-field-requirement kind="required" /></label>
                        <select name="categories[__INDEX__][category_type]" data-category-type class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <option value="">Select type</option>
                            @foreach (($categoryTypes ?? []) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-custom-category-wrapper class="hidden">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Type <x-event-field-requirement kind="conditional">Required for Custom</x-event-field-requirement></label>
                        <input name="categories[__INDEX__][custom_category_name]" type="text" placeholder="Trail, Family, Corporate" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div data-standard-distance>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Distance <x-event-field-requirement kind="required" /></label>
                        <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-2">
                            <select name="categories[__INDEX__][distance_option]" data-distance-option class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <option value="">Select distance</option>
                                @foreach (($distanceOptions ?? []) as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="categories[__INDEX__][distance_unit]" data-distance-unit aria-label="Distance unit" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-3 text-sm font-semibold text-[#151b26] outline-none">
                                <option value="km">km</option>
                                <option value="mi">miles</option>
                            </select>
                        </div>
                    </div>
                    <div data-standard-distance data-custom-distance-wrapper class="hidden">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance <x-event-field-requirement kind="conditional">Required for Custom</x-event-field-requirement></label>
                        <input name="categories[__INDEX__][custom_distance_km]" type="number" step="0.01" min="0.01" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    @foreach ($categoryTypeDetailSchemas as $eventType => $detailSchema)
                        <div data-category-type-details="{{ $eventType }}" class="hidden md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                            <p class="mb-4 text-sm font-semibold text-[#151b26]">{{ $eventType }} Category Details</p>
                            <div class="grid gap-4 md:grid-cols-3">
                                @foreach ($detailSchema as $detailKey => $definition)
                                    @php
                                        $templateDetailValue = $event?->interest_type === $eventType
                                            ? data_get($event?->type_details, $detailKey)
                                            : null;
                                        $detailRequired = in_array('required', $definition['rules'] ?? [], true);
                                    @endphp
                                    <div class="{{ ($definition['type'] ?? 'number') === 'textarea' ? 'md:col-span-3' : '' }}">
                                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">{{ $definition['label'] }} <x-event-field-requirement :kind="$detailRequired ? 'required' : (($definition['required_for_publication'] ?? false) ? 'submission' : 'optional')" /></label>
                                        @if (($definition['type'] ?? 'number') === 'textarea')
                                            <textarea name="categories[__INDEX__][type_details][{{ $detailKey }}]" rows="3" placeholder="{{ $definition['placeholder'] ?? '' }}" @if ($detailRequired) required @endif
                                                class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $templateDetailValue }}</textarea>
                                        @elseif (($definition['type'] ?? 'number') === 'select')
                                            <select name="categories[__INDEX__][type_details][{{ $detailKey }}]" @if ($detailRequired) required @endif
                                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                                <option value="">Select {{ strtolower($definition['label']) }}</option>
                                                @foreach (($definition['options'] ?? []) as $option)
                                                    <option value="{{ $option }}" @selected($templateDetailValue === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="relative">
                                                <input name="categories[__INDEX__][type_details][{{ $detailKey }}]" type="number" min="0.01" step="0.01" value="{{ $templateDetailValue }}" @if ($detailRequired) required @endif
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
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Date <x-event-field-requirement kind="required" /></label>
                        <input name="categories[__INDEX__][scheduled_start_date]" type="date" data-category-start-date required class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Time <x-event-field-requirement kind="required" /></label>
                        <input name="categories[__INDEX__][scheduled_start_time]" type="time" required class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">Planned wave time; the Start button records the actual server time.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">End Date <x-event-field-requirement kind="required" /></label>
                        <input name="categories[__INDEX__][scheduled_end_date]" type="date" data-category-end-date required class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">End Time <x-event-field-requirement kind="required" /></label>
                        <input name="categories[__INDEX__][scheduled_end_time]" type="time" required class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the event schedule.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit <x-event-field-requirement kind="submission" /></label>
                        <input name="categories[__INDEX__][slot_limit]" type="number" min="1" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div data-group-setting class="hidden">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Minimum Group Members <x-event-field-requirement kind="conditional">Required for groups</x-event-field-requirement></label>
                        <input name="categories[__INDEX__][group_min_members]" type="number" min="2" max="100" placeholder="At least 2" disabled class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div data-group-setting class="hidden">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Maximum Group Members <x-event-field-requirement kind="conditional">Required for groups</x-event-field-requirement></label>
                        <input name="categories[__INDEX__][group_max_members]" type="number" min="2" max="100" placeholder="2 to 100" disabled class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">Each member receives a separate registration, BIB, result, and certificate.</p>
                    </div>
                    <div class="rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                        <input type="hidden" name="categories[__INDEX__][requires_medical_certificate]" value="0">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input name="categories[__INDEX__][requires_medical_certificate]" type="checkbox" value="1"
                                class="mt-1 h-4 w-4 rounded border-[#c8cfda] text-[#151b26] focus:ring-[#151b26]">
                            <span>
                                <span class="block text-sm font-semibold text-[#151b26]">Medical Certificate Required <x-event-field-requirement kind="setting" /></span>
                                <span class="mt-1 block text-xs leading-5 text-[#6d7685]">Require participants to upload one when registering.</span>
                            </span>
                        </label>
                    </div>
                    <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4" data-category-map-requirement data-saved-map-image="false" tabindex="-1" role="group" aria-label="Required course or checkpoint map">
                        <label for="category-__INDEX__-map-upload" class="block text-sm font-semibold text-[#151b26]">Course / Checkpoint Map <x-event-field-requirement kind="submission" /></label>
                        <p class="mt-2 text-xs leading-5 text-[#6d7685]">For submission, upload a map image or review and apply a drawn course below. Either option is enough; a saved map also counts.</p>
                        <input id="category-__INDEX__-map-upload" name="categories[__INDEX__][checkpoint_map_image_upload]" type="file" accept="image/jpeg,image/png,image/webp"
                            class="mt-3 block w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#3d4757] file:mr-3 file:rounded-lg file:border-0 file:bg-[#eef1f4] file:px-3 file:py-2 file:font-semibold file:text-[#151b26]">
                        <p class="mt-2 text-xs leading-5 text-[#6d7685]">JPG, PNG, or WebP up to 5 MB. This map belongs only to this category.</p>
                    </div>
                    @include('admin.categories.partials.course-map-editor', [
                        'name' => 'categories[__INDEX__][course_map]',
                        'errorKey' => 'categories.__INDEX__.course_map',
                        'value' => null,
                        'mapRequirement' => true,
                    ])
                    <div>
                        <div class="grid grid-cols-[1fr_88px] gap-2">
                            <label for="category-__INDEX__-price" class="block text-sm font-medium text-[#3d4757]">Registration Fee <x-event-field-requirement /></label>
                            <label for="category-__INDEX__-currency" class="block text-sm font-medium text-[#3d4757]">Currency <x-event-field-requirement kind="required" /></label>
                            <input id="category-__INDEX__-price" name="categories[__INDEX__][price_amount]" type="number" step="0.01" min="0" value="0.00" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <input id="category-__INDEX__-currency" name="categories[__INDEX__][price_currency]" type="text" maxlength="3" value="PHP" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-3 text-sm font-semibold uppercase text-[#151b26] outline-none">
                        </div>
                        <p class="mt-2 text-xs text-[#6d7685]">Use 0.00 for free registration.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Status <x-event-field-requirement kind="required" /></label>
                        <select name="categories[__INDEX__][status]" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            @foreach (['open', 'closed', 'draft'] as $status)
                                <option value="{{ $status }}" @selected($status === 'open')>{{ str($status)->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Description <x-event-field-requirement /></label>
                        <textarea name="categories[__INDEX__][description]" rows="3" class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Qualification / Eligibility Notes <x-event-field-requirement /></label>
                        <textarea name="categories[__INDEX__][qualification_notes]" rows="3" maxlength="5000" placeholder="e.g. Must be at least 18 years old and have previous race experience."
                            class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none"></textarea>
                        <p class="mt-2 text-xs text-[#6d7685]">Shown to participants before they register for this category.</p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="md:col-span-2">
        @if ($event)
            @php($readinessErrors = array_merge($event->publicReadinessErrors(), $event->isReviewPublished() ? [] : app(\App\Services\EventSubmissionRequirements::class)->errors($event)))
            <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4 text-sm leading-6 text-[#5f6b7a]">
                <span class="font-semibold text-[#151b26]">Status: {{ str($event->effective_status)->replace('_', ' ')->title() }}.</span>
                The system detects this from the event details, schedule, open categories, and payment setup.
            </div>
            <div class="mt-3 rounded-2xl border {{ $readinessErrors === [] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} p-4 text-sm leading-6">
                <p class="font-semibold">{{ $readinessErrors === [] ? 'Mobile registration setup is complete.' : 'To make this event visible for mobile registration:' }}</p>
                @if ($readinessErrors === [])
                    <p class="mt-1">The event has all required details, at least one open category, and complete payment setup for paid categories.</p>
                @else
                    <ul class="mt-2 grid gap-1 md:grid-cols-2">
                        @foreach ($readinessErrors as $readinessError)
                            <li class="flex gap-2">
                                <span aria-hidden="true">-</span>
                                <span>{{ ucfirst($readinessError) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="mt-3 grid gap-2 rounded-2xl border border-[#eef1f4] bg-white p-4 text-xs leading-5 text-[#5f6b7a] md:grid-cols-2">
                <p><span class="font-semibold text-[#151b26]">Draft:</span> internal setup only.</p>
                <p><span class="font-semibold text-[#151b26]">Upcoming:</span> visible in the mobile app and open for registration when categories are open.</p>
                <p><span class="font-semibold text-[#151b26]">Ongoing:</span> detected automatically on event day after start time.</p>
                <p><span class="font-semibold text-[#151b26]">Completed:</span> detected automatically after the event date or end time.</p>
            </div>
        @else
            <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4 text-sm leading-6 text-[#5f6b7a]">
                <span class="font-semibold text-[#151b26]">Status: Draft.</span>
                New events start as private drafts. Complete the setup, then submit for Admin review. Approval publishes the event.
            </div>
        @endif
    </div>

    @if (auth()->user()->isSuperAdmin())
        <div>
            <label for="manager_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Assigned Event Manager <x-event-field-requirement kind="submission" /></label>
            <select id="manager_id" name="manager_id"
                class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                <option value="">Unassigned</option>
                @foreach (($managers ?? collect()) as $manager)
                    <option value="{{ $manager->id }}" @selected((string) old('manager_id', $event?->manager_id) === (string) $manager->id)>
                        {{ $manager->name }}{{ $manager->email ? ' (' . $manager->email . ')' : '' }}
                    </option>
                @endforeach
            </select>
            <p id="manager-availability-message" class="mt-2 text-xs leading-5 text-[#6d7685]" aria-live="polite">
                Select the event dates and times to check the manager's availability.
            </p>
        </div>
    @endif

    <div class="md:col-span-2">
        <label for="banner_image_upload" class="mb-2 block text-sm font-medium text-[#3d4757]">Banner Image <x-event-field-requirement kind="submission" /></label>
        <input type="hidden" name="banner_image" value="{{ old('banner_image', $event?->banner_image) }}">
        <input id="banner_image_upload" name="banner_image_upload" type="file" accept="image/*" data-review-satisfied="{{ filled($event?->banner_image) ? 'true' : 'false' }}"
            class="block w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] file:mr-4 file:rounded-xl file:border-0 file:bg-[#151b26] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Upload a JPG, PNG, or WebP banner image up to 4 MB. Recommended size: 1080 x 1080 px.</p>
        @if ($event?->banner_image)
            <p class="mt-2 text-xs leading-5 text-[#6d7685]">Your saved banner already counts. Choose a new file only if you want to replace it.</p>
            <div class="mt-3 overflow-hidden rounded-2xl border border-[#eef1f4] bg-[#f8f9fb]">
                <img src="{{ asset('storage/'.$event->banner_image) }}" alt="{{ $event->title }} banner" class="h-40 w-full object-cover">
            </div>
        @endif
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" name="intent" value="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
        Submit for Review
    </button>
    <button type="submit" name="intent" value="draft" formnovalidate class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Save Draft</button>
    <a href="{{ route('admin.events.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
        Cancel
    </a>
</div>

@include('admin.events.partials.submission-confirmation', ['event' => $event])

<script>
    (() => {
        const eventTypeSelect = document.querySelector('#interest_type');
        const eventTypePanels = document.querySelectorAll('[data-event-type-panel]');
        const eventTypeEmpty = document.querySelector('[data-event-type-empty]');
        const categoryHeading = document.querySelector('[data-category-heading]');
        const categoryLabels = @json($categoryLabels);
        const eventStartDate = document.querySelector('#event_date');
        const eventEndDate = document.querySelector('#event_end_date');
        const eventStartTime = document.querySelector('#start_time');
        const eventEndTime = document.querySelector('#end_time');
        const managerSelect = document.querySelector('#manager_id');
        const managerAvailabilityMessage = document.querySelector('#manager-availability-message');
        const managerSchedules = @json($managerSchedules ?? []);

        const schedulesOverlap = (candidate, existing) => {
            if (! candidate.event_date || ! existing.event_date) return false;

            const candidateEndDate = candidate.event_end_date || candidate.event_date;
            const existingEndDate = existing.event_end_date || existing.event_date;
            const datesOverlap = candidate.event_date <= existingEndDate
                && candidateEndDate >= existing.event_date;

            if (! datesOverlap) return false;

            const hasCompleteTimes = candidate.start_time && candidate.end_time
                && existing.start_time && existing.end_time;

            if (! hasCompleteTimes) return true;

            const candidateStart = new Date(`${candidate.event_date}T${candidate.start_time}`);
            const candidateEnd = new Date(`${candidateEndDate}T${candidate.end_time}`);
            const existingStart = new Date(`${existing.event_date}T${existing.start_time}`);
            const existingEnd = new Date(`${existingEndDate}T${existing.end_time}`);

            return candidateStart < existingEnd && candidateEnd > existingStart;
        };

        const formatManagerConflict = (event) => {
            const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
            const startDate = new Date(`${event.event_date}T00:00:00`).toLocaleDateString(undefined, dateOptions);
            const endDateValue = event.event_end_date || event.event_date;
            const endDate = new Date(`${endDateValue}T00:00:00`).toLocaleDateString(undefined, dateOptions);
            const dateRange = event.event_date === endDateValue ? startDate : `${startDate} to ${endDate}`;

            return `${event.title} (${dateRange})`;
        };

        const refreshManagerAvailability = () => {
            if (! managerSelect || ! managerAvailabilityMessage) return;

            const candidate = {
                event_date: eventStartDate?.value || '',
                event_end_date: eventEndDate?.value || eventStartDate?.value || '',
                start_time: eventStartTime?.value || '',
                end_time: eventEndTime?.value || '',
            };

            Array.from(managerSelect.options).forEach((option) => {
                if (! option.value) return;
                option.dataset.baseLabel ||= option.textContent.trim();
                option.textContent = option.dataset.baseLabel;
            });

            if (! candidate.event_date) {
                managerAvailabilityMessage.className = 'mt-2 text-xs leading-5 text-[#6d7685]';
                managerAvailabilityMessage.textContent = 'Select the event dates and times to check the manager\'s availability.';
                return;
            }

            Array.from(managerSelect.options).forEach((option) => {
                if (! option.value) return;
                const conflict = (managerSchedules[option.value] || [])
                    .find((event) => schedulesOverlap(candidate, event));
                option.dataset.conflict = conflict ? 'true' : 'false';
                option.textContent = conflict
                    ? `${option.dataset.baseLabel} — Unavailable`
                    : `${option.dataset.baseLabel} — Available`;
            });

            const selectedOption = managerSelect.selectedOptions[0];
            if (! selectedOption?.value) {
                managerAvailabilityMessage.className = 'mt-2 text-xs leading-5 text-[#6d7685]';
                managerAvailabilityMessage.textContent = 'Choose a manager to see their availability for this schedule.';
                return;
            }

            const conflict = (managerSchedules[selectedOption.value] || [])
                .find((event) => schedulesOverlap(candidate, event));

            if (conflict) {
                managerAvailabilityMessage.className = 'mt-2 text-xs font-medium leading-5 text-rose-600';
                managerAvailabilityMessage.textContent = `Unavailable: already assigned to ${formatManagerConflict(conflict)}. Change the manager or schedule.`;
                return;
            }

            managerAvailabilityMessage.className = 'mt-2 text-xs font-medium leading-5 text-emerald-700';
            managerAvailabilityMessage.textContent = 'Available for this event schedule.';
        };

        [eventStartDate, eventEndDate, eventStartTime, eventEndTime, managerSelect].forEach((field) => {
            field?.addEventListener('change', refreshManagerAvailability);
        });
        refreshManagerAvailability();

        const refreshCategoryDistanceFields = (row) => {
            const selectedType = eventTypeSelect?.value || '';
            const usesSegmentedDistances = ['Triathlon', 'Duathlon'].includes(selectedType);
            const distanceOption = row.querySelector('[data-distance-option]');

            row.querySelectorAll('[data-standard-distance]').forEach((wrapper) => {
                const isCustomWrapper = wrapper.hasAttribute('data-custom-distance-wrapper');
                const visible = ! usesSegmentedDistances && (! isCustomWrapper || distanceOption?.value === 'custom');
                wrapper.classList.toggle('hidden', ! visible);
                wrapper.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.disabled = ! visible;
                });
            });

            row.querySelectorAll('[data-category-type-details]').forEach((panel) => {
                const active = panel.dataset.categoryTypeDetails === selectedType;
                panel.classList.toggle('hidden', ! active);
                panel.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.dataset.requiredWhenActive ??= field.required ? 'true' : 'false';
                    field.disabled = ! active;
                    field.required = active && field.dataset.requiredWhenActive === 'true';
                });
            });
        };

        const refreshEventTypeDetails = () => {
            const selectedType = eventTypeSelect?.value || '';
            eventTypeEmpty?.classList.toggle('hidden', Boolean(selectedType));

            eventTypePanels.forEach((panel) => {
                const active = panel.dataset.eventTypePanel === selectedType;
                panel.classList.toggle('hidden', ! active);
                panel.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.disabled = ! active;
                });
            });

            if (categoryHeading) {
                categoryHeading.textContent = categoryLabels[selectedType] || 'Registration Categories';
            }

            document.querySelectorAll('[data-category-row]').forEach(refreshCategoryDistanceFields);
        };

        eventTypeSelect?.addEventListener('change', refreshEventTypeDetails);
        refreshEventTypeDetails();


        const list = document.querySelector('[data-category-list]');
        const template = document.querySelector('[data-category-template]');
        const addButton = document.querySelector('[data-add-category]');
        let nextIndex = list ? list.querySelectorAll('[data-category-row]').length : 0;

        const refreshNumbers = () => {
            list?.querySelectorAll('[data-category-row]').forEach((row, index) => {
                const number = row.querySelector('[data-category-number]');
                if (number) {
                    number.textContent = index + 1;
                }
            });
        };

        const bindRow = (row) => {
            const categoryType = row.querySelector('[data-category-type]');
            const customCategory = row.querySelector('[data-custom-category-wrapper]');
            const distanceOption = row.querySelector('[data-distance-option]');
            const removeButton = row.querySelector('[data-remove-category]');
            const refreshGroupSettings = () => {
                const isGroup = categoryType?.value === 'group';

                row.querySelectorAll('[data-group-setting]').forEach((wrapper) => {
                    const locked = wrapper.hasAttribute('data-group-setting-locked');
                    wrapper.classList.toggle('hidden', ! isGroup);
                    wrapper.querySelectorAll('input:not([type="hidden"])').forEach((field) => {
                        field.disabled = ! isGroup || locked;
                        field.required = isGroup && ! locked;
                    });
                });
            };

            categoryType?.addEventListener('change', () => {
                customCategory?.classList.toggle('hidden', categoryType.value !== 'custom');
                refreshGroupSettings();
            });

            distanceOption?.addEventListener('change', () => {
                refreshCategoryDistanceFields(row);
            });

            removeButton?.addEventListener('click', () => {
                row.remove();
                refreshNumbers();
            });

            refreshCategoryDistanceFields(row);
            refreshGroupSettings();
        };

        list?.querySelectorAll('[data-category-row]').forEach(bindRow);

        addButton?.addEventListener('click', () => {
            if (! list || ! template) {
                return;
            }

            const html = template.innerHTML.replaceAll('__INDEX__', nextIndex);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const row = wrapper.firstElementChild;

            if (! row) {
                return;
            }

            const categoryStartDate = row.querySelector('[data-category-start-date]');
            const categoryEndDate = row.querySelector('[data-category-end-date]');
            if (categoryStartDate) categoryStartDate.value = eventStartDate?.value || '';
            if (categoryEndDate) categoryEndDate.value = eventStartDate?.value || '';

            list.appendChild(row);
            bindRow(row);
            nextIndex += 1;
            refreshNumbers();
        });

        refreshNumbers();
    })();
</script>
@once
    <script type="module" src="{{ asset('js/event-review-submit.js') }}?v={{ filemtime(public_path('js/event-review-submit.js')) }}"></script>
@endonce
