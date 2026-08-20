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
        $selectedEventType = $selectedCategoryEvent?->interest_type;
        $usesSegmentedDistances = isset($categoryTypeDetailSchemas[$selectedEventType]);
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

        <form method="POST" action="{{ route('admin.categories.store') }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
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
                        <p class="mb-4 text-sm font-semibold text-[#151b26]">{{ $eventType }} Category Distances</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach ($detailSchema as $detailKey => $definition)
                                <div>
                                    <label for="type_details_{{ str($eventType)->slug('_') }}_{{ $detailKey }}" class="mb-2 block text-sm font-medium text-[#3d4757]">{{ $definition['label'] }}</label>
                                    <div class="relative">
                                        <input id="type_details_{{ str($eventType)->slug('_') }}_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" type="number" min="0.01" step="0.01" value="{{ old("type_details.{$detailKey}") }}"
                                            class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 pr-12 text-sm text-[#151b26] outline-none">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-semibold text-[#7a8495]">{{ $definition['suffix'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div>
                    <label for="scheduled_start_date" class="mb-2 block text-sm font-medium text-[#3d4757]">Scheduled Gun Start Date</label>
                    <input id="scheduled_start_date" name="scheduled_start_date" type="date" value="{{ old('scheduled_start_date', $selectedCategoryEvent?->event_date?->format('Y-m-d')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="scheduled_start_time" class="mb-2 block text-sm font-medium text-[#3d4757]">Scheduled Gun Start</label>
                    <input id="scheduled_start_time" name="scheduled_start_time" type="time" value="{{ old('scheduled_start_time', $selectedCategoryEvent?->start_time?->format('H:i')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <p class="mt-2 text-xs text-[#6d7685]">The Start Category button becomes available at this planned time.</p>
                </div>

                <div>
                    <label for="scheduled_end_date" class="mb-2 block text-sm font-medium text-[#3d4757]">Category Cutoff/End Date</label>
                    <input id="scheduled_end_date" name="scheduled_end_date" type="date" value="{{ old('scheduled_end_date', $selectedCategoryEvent?->event_date?->format('Y-m-d')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div>
                    <label for="scheduled_end_time" class="mb-2 block text-sm font-medium text-[#3d4757]">Category Cutoff/End Time</label>
                    <input id="scheduled_end_time" name="scheduled_end_time" type="time" value="{{ old('scheduled_end_time', $selectedCategoryEvent?->end_time?->format('H:i')) }}" required
                        class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the overall event schedule.</p>
                </div>

                <div>
                    <label for="slot_limit" class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit</label>
                    <input id="slot_limit" name="slot_limit" type="number" min="1" value="{{ old('slot_limit') }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
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

                <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                    <p class="mb-4 text-sm text-[#6d7685]">Required when the registration fee is greater than 0.00.</p>
                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="payment_provider" class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Method</label>
                            <select id="payment_provider" name="payment_provider" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <option value="">Select method</option>
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_provider') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment_account_name" class="mb-2 block text-sm font-medium text-[#3d4757]">Account Name</label>
                            <input id="payment_account_name" name="payment_account_name" type="text" value="{{ old('payment_account_name') }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>
                        <div>
                            <label for="payment_account_number" class="mb-2 block text-sm font-medium text-[#3d4757]">Account Number</label>
                            <input id="payment_account_number" name="payment_account_number" type="text" value="{{ old('payment_account_number') }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>
                        <div class="md:col-span-3">
                            <label for="payment_instructions" class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Instructions</label>
                            <textarea id="payment_instructions" name="payment_instructions" rows="4" placeholder="Tell runners how to pay and what proof/reference they should upload."
                                class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('payment_instructions') }}</textarea>
                        </div>
                    </div>
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
                wrapper.querySelectorAll('input, select').forEach((field) => field.disabled = ! visible);
            });

            categoryTypeDetailPanels.forEach((panel) => {
                const active = panel.dataset.categoryTypeDetails === eventType;
                panel.classList.toggle('hidden', ! active);
                panel.querySelectorAll('input').forEach((field) => field.disabled = ! active);
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
