@extends('admin.layouts.app')

@section('title', 'Edit Category')

@section('content')
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
                This category already has registrations or results, so distance and type are locked. You can still update its scheduled gun start and cutoff/end time before the race begins, along with status, slots, fee, and description.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
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

                    <div>
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

                    <div id="custom-distance-wrapper" class="{{ old('distance_option', $distanceOption) === 'custom' ? '' : 'hidden' }}">
                        <label for="custom_distance_km" class="mb-2 block text-sm font-medium text-[#3d4757]">Custom Distance (km)</label>
                        <input id="custom_distance_km" name="custom_distance_km" type="number" step="0.01" min="0.01" value="{{ old('custom_distance_km', (float) $category->distance_km) }}"
                            class="h-12 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    </div>
                @endif

                <div>
                    <p class="mb-2 block text-sm font-medium text-[#3d4757]">Scheduled Gun Start</p>
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
                    <p class="mb-2 block text-sm font-medium text-[#3d4757]">Category Cutoff/End Time</p>
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

                <div class="md:col-span-2 rounded-2xl border border-[#d9dee7] bg-[#fafbfc] p-4">
                    <p class="mb-4 text-sm text-[#6d7685]">Required when the registration fee is greater than 0.00.</p>
                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label for="payment_provider" class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Method</label>
                            <select id="payment_provider" name="payment_provider" class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                                <option value="">Select method</option>
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_provider', \App\Models\Category::normalizePaymentProvider($category->payment_provider)) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment_account_name" class="mb-2 block text-sm font-medium text-[#3d4757]">Account Name</label>
                            <input id="payment_account_name" name="payment_account_name" type="text" value="{{ old('payment_account_name', $category->payment_account_name) }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>
                        <div>
                            <label for="payment_account_number" class="mb-2 block text-sm font-medium text-[#3d4757]">Account Number</label>
                            <input id="payment_account_number" name="payment_account_number" type="text" value="{{ old('payment_account_number', $category->payment_account_number) }}"
                                class="h-12 w-full rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm text-[#151b26] outline-none">
                        </div>
                        <div class="md:col-span-3">
                            <label for="payment_instructions" class="mb-2 block text-sm font-medium text-[#3d4757]">Payment Instructions</label>
                            <textarea id="payment_instructions" name="payment_instructions" rows="4" placeholder="Tell runners how to pay and what proof/reference they should upload."
                                class="w-full rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#151b26] outline-none">{{ old('payment_instructions', $category->payment_instructions) }}</textarea>
                        </div>
                    </div>
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

            categoryType?.addEventListener('change', () => {
                customCategoryWrapper?.classList.toggle('hidden', categoryType.value !== 'custom');
            });

            distanceOption?.addEventListener('change', () => {
                customDistanceWrapper?.classList.toggle('hidden', distanceOption.value !== 'custom');
            });
        </script>
    @endunless
@endsection
