@extends('admin.layouts.app')

@section('title', 'Edit Category')

@section('content')
    @php
        $categoryTypeDetailSchema = config("conquer.event_category_type_details.{$category->event?->interest_type}", []);
        $usesSegmentedDistances = in_array($category->event?->interest_type, ['Triathlon', 'Duathlon'], true);
        $resolvedCategoryDetails = $category->resolvedTypeDetails();
        $mutableCategoryTypeDetailSchema = collect($categoryTypeDetailSchema)
            ->reject(fn (array $definition) => $definition['locked_when_in_use'] ?? false)
            ->all();
    @endphp
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Setup</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Edit {{ $category->event?->categorySectionLabel() ?? 'Registration Category' }}</h1>
            <p class="mt-2 text-sm text-[#6d7685]">{{ $category->event?->title ?: 'Removed event' }} · {{ $category->name }}</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($categoryInUse)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                This category already has registrations or results, so distance and type are locked. You can still update category details such as difficulty and gear, its scheduled gun start and cutoff/end time before the race begins, along with status, slots, fee, and description.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                @if ($categoryInUse)
                    <div>
                        <p class="mb-2 block text-sm font-medium text-[#3d4757]">Category</p>
                        <p class="flex h-12 items-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 text-sm font-semibold text-[#151b26]">{{ $category->name }}</p>
                    </div>
                    <div>
                        <p class="mb-2 block text-sm font-medium text-[#3d4757]">Distance</p>
                        <p class="flex h-12 items-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 text-sm text-[#151b26]">{{ number_format((float) $category->distance_km, 2) }} km</p>
                    </div>
                    @if ($usesSegmentedDistances)
                        <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] p-4">
                            <p class="mb-3 text-sm font-medium text-[#3d4757]">Category Distances</p>
                            <div class="grid gap-3 md:grid-cols-3">
                                @foreach ($category->formattedTypeDetails() as $detail)
                                    <div class="rounded-xl bg-white px-3 py-2 text-sm text-[#151b26]">
                                        <span class="block text-xs text-[#7a8495]">{{ $detail['label'] }}</span>
                                        <span class="font-semibold">{{ $detail['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
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
                                <option value="{{ $value }}" @selected(old('category_type', $categoryType['key']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="custom-category-wrapper" class="{{ old('category_type', $categoryType['key']) === 'custom' ? '' : 'hidden' }}">
                        <label for="custom_category_name" class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Type</label>
                        <input id="custom_category_name" name="custom_category_name" type="text" value="{{ old('custom_category_name', $categoryType['custom']) }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    </div>

                    <div class="{{ $usesSegmentedDistances ? 'hidden' : '' }}">
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
                                <option value="{{ $value }}" @selected(old('distance_option', $distanceOption) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="custom-distance-wrapper" class="{{ $usesSegmentedDistances || old('distance_option', $distanceOption) !== 'custom' ? 'hidden' : '' }}">
                        <label for="custom_distance_km" class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance (km)</label>
                        <input id="custom_distance_km" name="custom_distance_km" type="number" step="0.01" min="0.01" value="{{ old('custom_distance_km', (float) $category->distance_km) }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    </div>

                    @if ($categoryTypeDetailSchema !== [])
                        <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                            <p class="mb-4 text-sm font-semibold text-[#151b26]">{{ $category->event->interest_type }} Category Details</p>
                            <div class="grid gap-4 md:grid-cols-3">
                                @foreach ($categoryTypeDetailSchema as $detailKey => $definition)
                                    @php($detailRequired = in_array('required', $definition['rules'] ?? [], true))
                                    <div class="{{ ($definition['type'] ?? 'number') === 'textarea' ? 'md:col-span-3' : '' }}">
                                        <label for="type_details_{{ $detailKey }}" class="mb-2 block text-sm font-medium text-[#3d4757]">{{ $definition['label'] }}</label>
                                        @if (($definition['type'] ?? 'number') === 'textarea')
                                            <textarea id="type_details_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" rows="3" placeholder="{{ $definition['placeholder'] ?? '' }}" @if ($detailRequired) required @endif
                                                class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ old("type_details.{$detailKey}", $resolvedCategoryDetails[$detailKey] ?? null) }}</textarea>
                                        @elseif (($definition['type'] ?? 'number') === 'select')
                                            <select id="type_details_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" @if ($detailRequired) required @endif
                                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                                <option value="">Select {{ strtolower($definition['label']) }}</option>
                                                @foreach (($definition['options'] ?? []) as $option)
                                                    <option value="{{ $option }}" @selected(old("type_details.{$detailKey}", $resolvedCategoryDetails[$detailKey] ?? null) === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="relative">
                                                <input id="type_details_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" type="number" min="0.01" step="0.01" value="{{ old("type_details.{$detailKey}", $resolvedCategoryDetails[$detailKey] ?? null) }}" @if ($detailRequired) required @endif
                                                    class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 {{ isset($definition['suffix']) ? 'pr-12' : '' }} text-sm text-[#151b26] outline-none">
                                                @if (isset($definition['suffix']))
                                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-semibold text-[#7a8495]">{{ $definition['suffix'] }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($usesSegmentedDistances)
                                <p class="mt-3 text-xs text-[#6d7685]">The total category distance is calculated automatically from these segments.</p>
                            @endif
                        </div>
                    @endif
                @endif

                @if ($categoryInUse && $mutableCategoryTypeDetailSchema !== [])
                    <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                        <p class="mb-4 text-sm font-semibold text-[#151b26]">{{ $category->event->interest_type }} Category Details</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($mutableCategoryTypeDetailSchema as $detailKey => $definition)
                                @php($detailRequired = in_array('required', $definition['rules'] ?? [], true))
                                <div class="{{ ($definition['type'] ?? 'textarea') === 'textarea' ? 'md:col-span-2' : '' }}">
                                    <label for="type_details_{{ $detailKey }}" class="mb-2 block text-sm font-medium text-[#3d4757]">{{ $definition['label'] }}</label>
                                    @if (($definition['type'] ?? 'textarea') === 'select')
                                        <select id="type_details_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" @if ($detailRequired) required @endif
                                            class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                            <option value="">Select {{ strtolower($definition['label']) }}</option>
                                            @foreach (($definition['options'] ?? []) as $option)
                                                <option value="{{ $option }}" @selected(old("type_details.{$detailKey}", $resolvedCategoryDetails[$detailKey] ?? null) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <textarea id="type_details_{{ $detailKey }}" name="type_details[{{ $detailKey }}]" rows="3" placeholder="{{ $definition['placeholder'] ?? '' }}" @if ($detailRequired) required @endif
                                            class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ old("type_details.{$detailKey}", $resolvedCategoryDetails[$detailKey] ?? null) }}</textarea>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <p class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Date</p>
                    @if ($category->started_at)
                        <p class="flex h-12 items-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 text-sm font-semibold text-[#151b26]">
                            {{ ($category->scheduled_start_date ?? $category->event?->event_date)?->format('F j, Y') ?? 'Not set' }}
                        </p>
                    @else
                        <input id="scheduled_start_date" name="scheduled_start_date" type="date" value="{{ old('scheduled_start_date', ($category->scheduled_start_date ?? $category->event?->event_date)?->format('Y-m-d')) }}" required
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    @endif
                </div>

                <div>
                    <p class="mb-2 block text-sm font-medium text-[#3d4757]">Gun Start Time</p>
                    @if ($category->started_at)
                        <p class="flex h-12 items-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 text-sm font-semibold text-[#151b26]">
                            {{ $category->scheduled_start_time?->format('g:i A') ?: ($category->event?->start_time?->format('g:i A') ?? 'Not set') }}
                        </p>
                        <p class="mt-2 text-xs text-[#6d7685]">Locked because this category has already started.</p>
                    @else
                        <input id="scheduled_start_time" name="scheduled_start_time" type="time" value="{{ old('scheduled_start_time', $category->scheduled_start_time?->format('H:i') ?? $category->event?->start_time?->format('H:i')) }}" required
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">The Start Category button becomes available at this planned time.</p>
                    @endif
                </div>

                <div>
                    <p class="mb-2 block text-sm font-medium text-[#3d4757]">End Date</p>
                    @if ($category->started_at)
                        <p class="flex h-12 items-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 text-sm font-semibold text-[#151b26]">
                            {{ ($category->scheduled_end_date ?? $category->scheduled_start_date ?? $category->event?->event_date)?->format('F j, Y') ?? 'Not set' }}
                        </p>
                    @else
                        <input id="scheduled_end_date" name="scheduled_end_date" type="date" value="{{ old('scheduled_end_date', ($category->scheduled_end_date ?? $category->scheduled_start_date ?? $category->event?->event_date)?->format('Y-m-d')) }}" required
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    @endif
                </div>

                <div>
                    <p class="mb-2 block text-sm font-medium text-[#3d4757]">End Time</p>
                    @if ($category->started_at)
                        <p class="flex h-12 items-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 text-sm font-semibold text-[#151b26]">
                            {{ $category->scheduled_end_time?->format('g:i A') ?: ($category->event?->end_time?->format('g:i A') ?? 'Not set') }}
                        </p>
                        <p class="mt-2 text-xs text-[#6d7685]">Locked because this category has already started.</p>
                    @else
                        <input id="scheduled_end_time" name="scheduled_end_time" type="time" value="{{ old('scheduled_end_time', $category->scheduled_end_time?->format('H:i') ?? $category->event?->end_time?->format('H:i')) }}" required
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        <p class="mt-2 text-xs text-[#6d7685]">Must be after the gun start and within the overall event schedule.</p>
                    @endif
                </div>

                <div>
                    <label for="slot_limit" class="mb-2 block text-sm font-medium text-[#3d4757]">Slot Limit</label>
                    <input id="slot_limit" name="slot_limit" type="number" min="1" value="{{ old('slot_limit', $category->slot_limit) }}" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                </div>

                <div class="rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                    <input type="hidden" name="requires_medical_certificate" value="{{ $categoryInUse ? (int) $category->requiresMedicalCertificate() : 0 }}">
                    <label for="requires_medical_certificate" class="flex {{ $categoryInUse ? 'cursor-not-allowed' : 'cursor-pointer' }} items-start gap-3">
                        <input id="requires_medical_certificate" name="requires_medical_certificate" type="checkbox" value="1"
                            @checked(old('requires_medical_certificate', $category->requiresMedicalCertificate())) @disabled($categoryInUse)
                            class="mt-1 h-4 w-4 rounded border-[#c8cfda] text-[#151b26] focus:ring-[#151b26] disabled:cursor-not-allowed disabled:opacity-60">
                        <span>
                            <span class="block text-sm font-semibold text-[#151b26]">Medical Certificate Required</span>
                            <span class="mt-1 block text-xs leading-5 text-[#6d7685]">
                                {{ $categoryInUse
                                    ? 'Locked because participants are already registered in this category.'
                                    : 'Participants must upload a medical certificate when registering for this category.' }}
                            </span>
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                    <label for="checkpoint_map_image_upload" class="block text-sm font-semibold text-[#151b26]">Course / Checkpoint Map <span class="font-normal text-[#7a8495]">(optional)</span></label>
                    @if ($category->checkpoint_map_image)
                        <a href="{{ asset('storage/'.$category->checkpoint_map_image) }}" target="_blank" rel="noopener" class="mt-3 block overflow-hidden rounded-2xl border border-[#d9dee7] bg-white">
                            <img src="{{ asset('storage/'.$category->checkpoint_map_image) }}" alt="{{ $category->name }} course and checkpoint map" class="max-h-80 w-full object-contain">
                        </a>
                        <p class="mt-2 text-xs text-[#6d7685]">Upload another image to replace the current map.</p>
                    @endif
                    <input id="checkpoint_map_image_upload" name="checkpoint_map_image_upload" type="file" accept="image/jpeg,image/png,image/webp"
                        class="mt-3 block w-full rounded-xl border border-[#d9dee7] bg-white px-3 py-2 text-sm text-[#3d4757] file:mr-3 file:rounded-lg file:border-0 file:bg-[#eef1f4] file:px-3 file:py-2 file:font-semibold file:text-[#151b26]">
                    <p class="mt-2 text-xs leading-5 text-[#6d7685]">JPG, PNG, or WebP up to 5 MB. It is shown only for this category.</p>
                    @if ($category->checkpoint_map_image)
                        <input type="hidden" name="remove_checkpoint_map_image" value="0">
                        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-rose-700">
                            <input name="remove_checkpoint_map_image" type="checkbox" value="1" @checked(old('remove_checkpoint_map_image')) class="h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                            Remove the current map image
                        </label>
                    @endif
                </div>

                <div>
                    <label for="price_amount" class="mb-2 block text-sm font-medium text-[#3d4757]">Registration Fee</label>
                    <div class="grid grid-cols-[1fr_88px] gap-2">
                        <input id="price_amount" name="price_amount" type="number" step="0.01" min="0" value="{{ old('price_amount', number_format(($category->price_cents ?? 0) / 100, 2, '.', '')) }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        <input id="price_currency" name="price_currency" type="text" maxlength="3" value="{{ old('price_currency', $category->price_currency ?? 'PHP') }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-3 text-sm font-semibold uppercase text-[#151b26] outline-none">
                    </div>
                    <p class="mt-2 text-xs text-[#6d7685]">Existing registrations keep their original payment amount.</p>
                </div>

                <div>
                    <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                    <select id="status" name="status" class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                        @foreach (['open', 'closed', 'draft'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $category->status) === $status)>{{ str($status)->title() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-[#3d4757]">Description</label>
                    <textarea id="description" name="description" rows="4" class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('description', $category->description) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="qualification_notes" class="mb-2 block text-sm font-medium text-[#3d4757]">Qualification / Eligibility Notes <span class="font-normal text-[#7a8495]">(optional)</span></label>
                    <textarea id="qualification_notes" name="qualification_notes" rows="4" maxlength="5000" placeholder="e.g. Must be at least 18 years old and have previous trail experience."
                        class="w-full rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('qualification_notes', $category->qualification_notes) }}</textarea>
                    <p class="mt-2 text-xs text-[#6d7685]">Shown to participants before they register for this category.</p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Save Changes
                </button>
                <a href="{{ route('admin.categories.index', ['event_id' => $category->event_id]) }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    @unless ($categoryInUse)
        <script>
            const categoryType = document.getElementById('category_type');
            const customCategoryWrapper = document.getElementById('custom-category-wrapper');
            const distanceOption = document.getElementById('distance_option');
            const customDistanceWrapper = document.getElementById('custom-distance-wrapper');
            const usesSegmentedDistances = @json($usesSegmentedDistances);

            categoryType?.addEventListener('change', () => {
                customCategoryWrapper?.classList.toggle('hidden', categoryType.value !== 'custom');
            });

            distanceOption?.addEventListener('change', () => {
                customDistanceWrapper?.classList.toggle('hidden', usesSegmentedDistances || distanceOption.value !== 'custom');
            });

            if (usesSegmentedDistances) {
                distanceOption.disabled = true;
                document.getElementById('custom_distance_km').disabled = true;
            }
        </script>
    @endunless
@endsection
