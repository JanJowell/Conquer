<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="interest_type" class="mb-2 block text-sm font-medium text-[#3d4757]">Event Type</label>
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
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Must be on or before the event date.</p>
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
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">If set, it must be later than the start time.</p>
    </div>

    @php
        $selectedEventType = old('interest_type', $event?->interest_type);
        $eventTypeDetailSchemas = config('conquer.event_type_details', []);
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
                    @php
                        $inputName = "type_details[{$eventType}][{$detailKey}]";
                        $inputId = 'type-detail-'.str($eventType.'-'.$detailKey)->slug();
                        $detailValue = old("type_details.{$eventType}.{$detailKey}", $storedDetails[$detailKey] ?? null);
                        $fieldClasses = 'w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]';
                    @endphp
                    <div class="{{ ($definition['type'] ?? null) === 'textarea' ? 'md:col-span-2' : '' }}">
                        <label for="{{ $inputId }}" class="mb-2 block text-sm font-medium text-[#3d4757]">
                            {{ $definition['label'] }}
                            @if ($definition['required_for_publication'] ?? false)
                                <span class="text-rose-500" aria-label="required for publication">*</span>
                            @endif
                        </label>

                        @if (($definition['type'] ?? null) === 'select')
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

        <p class="mt-4 text-xs leading-5 text-[#6d7685]"><span class="text-rose-500">*</span> Required before the event can become visible for mobile registration.</p>
    </section>

    @php
        $categoryRows = old('categories');
        if ($categoryRows === null) {
            $categoryRows = $event ? [] : [[
                'category_type' => '',
                'custom_category_name' => '',
                'distance_option' => '',
                'custom_distance_km' => '',
                'scheduled_start_time' => '',
                'scheduled_end_time' => '',
                'slot_limit' => '',
                'price_amount' => '0.00',
                'price_currency' => 'PHP',
                'payment_provider' => '',
                'payment_account_name' => '',
                'payment_account_number' => '',
                'payment_instructions' => '',
                'status' => 'open',
                'description' => '',
            ]];
        }
    @endphp

    <div class="md:col-span-2 rounded-3xl border border-[#d9dee7] bg-[#fafbfc] p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-[#151b26]" data-category-heading>{{ $categoryLabels[$selectedEventType] ?? 'Registration Categories' }}</h2>
                <p class="mt-1 text-sm leading-6 text-[#6d7685]">Add the race distances and registration fees while setting up the event.</p>
            </div>
            <button type="button" data-add-category class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                Add Category
            </button>
        </div>

        @if ($event && $event->categories->isNotEmpty())
            <div class="mt-4 overflow-hidden rounded-2xl border border-[#d9dee7] bg-white">
                <div class="grid gap-3 border-b border-[#eef1f4] px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495] md:grid-cols-[minmax(0,1fr)_100px_100px_100px_100px_80px]">
                    <span>Existing Category</span>
                    <span>Gun Start</span>
                    <span>Cutoff/End</span>
                    <span>Fee</span>
                    <span>Usage</span>
                    <span>Status</span>
                </div>
                <div class="divide-y divide-[#eef1f4]">
                    @foreach ($event->categories as $existingCategory)
                        <div class="grid gap-3 px-4 py-3 text-sm text-[#202733] md:grid-cols-[minmax(0,1fr)_100px_100px_100px_100px_80px]">
                            <div>
                                <p class="font-semibold text-[#151b26]">{{ $existingCategory->name }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">{{ number_format((float) $existingCategory->distance_km, 2) }} km{{ $existingCategory->slot_limit ? ' - ' . number_format($existingCategory->slot_limit) . ' slots' : '' }}</p>
                            </div>
                            <p>{{ $existingCategory->scheduled_start_time?->format('g:i A') ?: ($event->start_time?->format('g:i A') ?? 'Not set') }}</p>
                            <p>{{ $existingCategory->scheduled_end_time?->format('g:i A') ?: ($event->end_time?->format('g:i A') ?? 'Not set') }}</p>
                            <p>{{ ($existingCategory->price_cents ?? 0) > 0 ? ($existingCategory->price_currency ?? 'PHP') . ' ' . number_format($existingCategory->price_cents / 100, 2) : 'Free' }}</p>
                            <p class="text-xs leading-5 text-[#6d7685]">{{ number_format($existingCategory->registrations_count ?? 0) }} registrations<br>{{ number_format($existingCategory->race_results_count ?? 0) }} results</p>
                            <p>{{ str($existingCategory->status)->title() }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-[#eef1f4] px-4 py-3">
                    <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="text-sm font-semibold text-[#151b26]">View, edit, or delete existing categories</a>
                </div>
            </div>
        @endif

        <div data-category-list class="mt-4 space-y-4">
            @foreach ($categoryRows as $index => $categoryRow)
                <div data-category-row class="rounded-2xl border border-[#d9dee7] bg-white p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-[#151b26]">New Category <span data-category-number>{{ $index + 1 }}</span></p>
                        <button type="button" data-remove-category class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                            Remove
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category Type</label>
                            <select name="categories[{{ $index }}][category_type]" data-category-type class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <option value="">Select type</option>
                                @foreach (($categoryTypes ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($categoryRow['category_type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div data-custom-category-wrapper class="{{ ($categoryRow['category_type'] ?? '') === 'custom' ? '' : 'hidden' }}">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Type</label>
                            <input name="categories[{{ $index }}][custom_category_name]" type="text" value="{{ $categoryRow['custom_category_name'] ?? '' }}" placeholder="Trail, Family, Corporate"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Distance</label>
                            <select name="categories[{{ $index }}][distance_option]" data-distance-option class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <option value="">Select distance</option>
                                @foreach (($distanceOptions ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($categoryRow['distance_option'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div data-custom-distance-wrapper class="{{ ($categoryRow['distance_option'] ?? '') === 'custom' ? '' : 'hidden' }}">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance (km)</label>
                            <input name="categories[{{ $index }}][custom_distance_km]" type="number" step="0.01" min="0.01" value="{{ $categoryRow['custom_distance_km'] ?? '' }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Scheduled Gun Start</label>
                            <input name="categories[{{ $index }}][scheduled_start_time]" type="time" value="{{ $categoryRow['scheduled_start_time'] ?? '' }}" required
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <p class="mt-2 text-xs text-[#6d7685]">Planned wave time; the Start button records the actual server time.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category Cutoff/End Time</label>
                            <input name="categories[{{ $index }}][scheduled_end_time]" type="time" value="{{ $categoryRow['scheduled_end_time'] ?? '' }}" required
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the event schedule.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit</label>
                            <input name="categories[{{ $index }}][slot_limit]" type="number" min="1" value="{{ $categoryRow['slot_limit'] ?? '' }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Registration Fee</label>
                            <div class="grid grid-cols-[1fr_88px] gap-2">
                                <input name="categories[{{ $index }}][price_amount]" type="number" step="0.01" min="0" value="{{ $categoryRow['price_amount'] ?? '0.00' }}"
                                    class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <input name="categories[{{ $index }}][price_currency]" type="text" maxlength="3" value="{{ $categoryRow['price_currency'] ?? 'PHP' }}"
                                    class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-3 text-sm font-semibold uppercase text-[#151b26] outline-none">
                            </div>
                            <p class="mt-2 text-xs text-[#6d7685]">Use 0.00 for free registration.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                            <select name="categories[{{ $index }}][status]" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                @foreach (['open', 'closed', 'draft'] as $status)
                                    <option value="{{ $status }}" @selected(($categoryRow['status'] ?? 'open') === $status)>{{ str($status)->title() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2 rounded-2xl border border-[#eef1f4] bg-[#fafbfc] p-4">
                            <p class="mb-4 text-sm text-[#6d7685]">Payment details are required when the registration fee is greater than 0.00.</p>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Method</label>
                                    <select name="categories[{{ $index }}][payment_provider]" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                        <option value="">Select method</option>
                                        @foreach (($paymentMethods ?? []) as $value => $label)
                                            <option value="{{ $value }}" @selected(($categoryRow['payment_provider'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Account Name</label>
                                    <input name="categories[{{ $index }}][payment_account_name]" type="text" value="{{ $categoryRow['payment_account_name'] ?? '' }}"
                                        class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Account Number</label>
                                    <input name="categories[{{ $index }}][payment_account_number]" type="text" value="{{ $categoryRow['payment_account_number'] ?? '' }}"
                                        class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Instructions</label>
                                    <textarea name="categories[{{ $index }}][payment_instructions]" rows="3" placeholder="Tell runners how to pay and what proof/reference they should upload."
                                        class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $categoryRow['payment_instructions'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
                            <textarea name="categories[{{ $index }}][description]" rows="3" class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ $categoryRow['description'] ?? '' }}</textarea>
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
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category Type</label>
                        <select name="categories[__INDEX__][category_type]" data-category-type class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <option value="">Select type</option>
                            @foreach (($categoryTypes ?? []) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-custom-category-wrapper class="hidden">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Type</label>
                        <input name="categories[__INDEX__][custom_category_name]" type="text" placeholder="Trail, Family, Corporate" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Distance</label>
                        <select name="categories[__INDEX__][distance_option]" data-distance-option class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <option value="">Select distance</option>
                            @foreach (($distanceOptions ?? []) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-custom-distance-wrapper class="hidden">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance (km)</label>
                        <input name="categories[__INDEX__][custom_distance_km]" type="number" step="0.01" min="0.01" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Scheduled Gun Start</label>
                        <input name="categories[__INDEX__][scheduled_start_time]" type="time" required class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">Planned wave time; the Start button records the actual server time.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Category Cutoff/End Time</label>
                        <input name="categories[__INDEX__][scheduled_end_time]" type="time" required class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the event schedule.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit</label>
                        <input name="categories[__INDEX__][slot_limit]" type="number" min="1" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Registration Fee</label>
                        <div class="grid grid-cols-[1fr_88px] gap-2">
                            <input name="categories[__INDEX__][price_amount]" type="number" step="0.01" min="0" value="0.00" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            <input name="categories[__INDEX__][price_currency]" type="text" maxlength="3" value="PHP" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-3 text-sm font-semibold uppercase text-[#151b26] outline-none">
                        </div>
                        <p class="mt-2 text-xs text-[#6d7685]">Use 0.00 for free registration.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                        <select name="categories[__INDEX__][status]" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            @foreach (['open', 'closed', 'draft'] as $status)
                                <option value="{{ $status }}" @selected($status === 'open')>{{ str($status)->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 rounded-2xl border border-[#eef1f4] bg-[#fafbfc] p-4">
                        <p class="mb-4 text-sm text-[#6d7685]">Payment details are required when the registration fee is greater than 0.00.</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Method</label>
                                <select name="categories[__INDEX__][payment_provider]" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                    <option value="">Select method</option>
                                    @foreach (($paymentMethods ?? []) as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-[#3d4757]">Account Name</label>
                                <input name="categories[__INDEX__][payment_account_name]" type="text" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-[#3d4757]">Account Number</label>
                                <input name="categories[__INDEX__][payment_account_number]" type="text" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                            </div>
                            <div class="md:col-span-3">
                                <label class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Instructions</label>
                                <textarea name="categories[__INDEX__][payment_instructions]" rows="3" placeholder="Tell runners how to pay and what proof/reference they should upload." class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
                        <textarea name="categories[__INDEX__][description]" rows="3" class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none"></textarea>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="md:col-span-2">
        @if ($event)
            @php($readinessErrors = $event->publicReadinessErrors())
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
                New events start as drafts. After saving, add categories and complete the setup; the system will update the status automatically.
            </div>
        @endif
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
        <label for="banner_image_upload" class="mb-2 block text-sm font-medium text-[#3d4757]">Banner Image</label>
        <input type="hidden" name="banner_image" value="{{ old('banner_image', $event?->banner_image) }}">
        <input id="banner_image_upload" name="banner_image_upload" type="file" accept="image/*"
            class="block w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] file:mr-4 file:rounded-xl file:border-0 file:bg-[#151b26] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
        <p class="mt-2 text-xs leading-5 text-[#6d7685]">Upload a JPG, PNG, or WebP banner image up to 4 MB. Recommended size: 1080 x 1080 px.</p>
        @if ($event?->banner_image)
            <div class="mt-3 overflow-hidden rounded-2xl border border-[#eef1f4] bg-[#f8f9fb]">
                <img src="{{ asset('storage/'.$event->banner_image) }}" alt="{{ $event->title }} banner" class="h-40 w-full object-cover">
            </div>
        @endif
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

<script>
    (() => {
        const eventTypeSelect = document.querySelector('#interest_type');
        const eventTypePanels = document.querySelectorAll('[data-event-type-panel]');
        const eventTypeEmpty = document.querySelector('[data-event-type-empty]');
        const categoryHeading = document.querySelector('[data-category-heading]');
        const categoryLabels = @json($categoryLabels);

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
            const customDistance = row.querySelector('[data-custom-distance-wrapper]');
            const removeButton = row.querySelector('[data-remove-category]');

            categoryType?.addEventListener('change', () => {
                customCategory?.classList.toggle('hidden', categoryType.value !== 'custom');
            });

            distanceOption?.addEventListener('change', () => {
                customDistance?.classList.toggle('hidden', distanceOption.value !== 'custom');
            });

            removeButton?.addEventListener('click', () => {
                row.remove();
                refreshNumbers();
            });
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

            list.appendChild(row);
            bindRow(row);
            nextIndex += 1;
            refreshNumbers();
        });

        refreshNumbers();
    })();
</script>
