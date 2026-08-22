@extends('admin.layouts.app')

@php
    $categoryPageLabel = $selectedEvent?->categorySectionLabel() ?? 'Registration Categories';
@endphp

@section('title', $categoryPageLabel)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Setup</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $categoryPageLabel }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Organize registration options, distances, fees, and slot allocations for each event.</p>
            </div>

            @if (request('event_id'))
                <a href="{{ route('admin.events.edit', request('event_id')) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Back to Event Setup
                </a>
            @endif
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[260px_auto]">
            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-2xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Filter</button>
                <a href="{{ route('admin.categories.index') }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685] transition hover:bg-[#f7f8fa]">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Distance</th>
                            <th class="px-6 py-4">Fee</th>
                            <th class="px-6 py-4">Slots</th>
                            <th class="px-6 py-4">Usage</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Race Start</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($categories as $category)
                            @php
                                $paidCategory = ($category->price_cents ?? 0) > 0;
                                $paymentReady = ! $paidCategory || $category->event?->hasUsablePaymentOptions(collect([$category]));
                                $eventPaymentProviders = ($category->event?->paymentMethods ?? collect())
                                    ->where('is_enabled', true)
                                    ->pluck('provider')
                                    ->join(', ');
                                $legacyPaymentProvider = ($category->event?->paymentMethods ?? collect())->isEmpty()
                                    ? $category->payment_provider_label
                                    : null;
                                $scheduledStartAt = $category->scheduledStartAt();
                            @endphp
                            <tr>
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $category->name }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $category->description ?: 'No description provided' }}</p>
                                    @if ($category->checkpoint_map_image)
                                        <a href="{{ asset('storage/'.$category->checkpoint_map_image) }}" target="_blank" rel="noopener" class="mt-2 inline-flex text-xs font-semibold text-emerald-700">View course map</a>
                                    @else
                                        <p class="mt-2 text-xs text-[#6d7685]">No course map uploaded</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $category->event?->title ?: 'Removed event' }}</td>
                                <td class="px-6 py-5">
                                    <p>{{ number_format((float) $category->distance_km, 2) }} km{{ $category->formattedTypeDetails() ? ' total' : '' }}</p>
                                    @foreach ($category->formattedTypeDetails() as $detail)
                                        <p class="mt-1 text-xs text-[#6d7685]">{{ $detail['label'] }}: {{ $detail['value'] }}</p>
                                    @endforeach
                                </td>
                                <td class="px-6 py-5">
                                    @if (($category->price_cents ?? 0) > 0)
                                        <p class="font-semibold text-[#151b26]">{{ $category->price_currency ?? 'PHP' }} {{ number_format($category->price_cents / 100, 2) }}</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">{{ $eventPaymentProviders ?: ($legacyPaymentProvider ?: 'No enabled event payment option') }}</p>
                                        @unless ($paymentReady)
                                            <p class="mt-2 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">
                                                Missing event payment option
                                            </p>
                                        @endunless
                                    @else
                                        Free
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $category->slot_limit ?: 'Open' }}</td>
                                <td class="px-6 py-5">
                                    <div class="space-y-1 text-xs text-[#6d7685]">
                                        <p>{{ number_format($category->registrations_count) }} registrations</p>
                                        <p>{{ number_format($category->race_results_count) }} results</p>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full bg-[#eef1f4] px-3 py-1 text-xs font-semibold text-[#4f5a6a]">
                                        {{ str($category->status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    @if ($category->started_at)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">In Progress</span>
                                        <p class="mt-2 text-xs text-[#6d7685]">Scheduled gun start {{ $scheduledStartAt?->format('M j, Y g:i A') ?: 'time unavailable' }}</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time unavailable' }}</p>
                                        <p class="mt-2 text-xs font-medium text-[#202733]">{{ $category->started_at->format('M j, Y g:i:s A') }}</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Started by {{ $category->startedBy?->name ?: 'administrator' }}</p>
                                    @elseif ($category->status === 'draft')
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Draft</span>
                                        <p class="mt-2 text-xs text-[#6d7685]">Scheduled gun start {{ $scheduledStartAt?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                    @elseif (! $scheduledStartAt)
                                        <span class="text-xs font-medium text-amber-700">Set the event schedule first</span>
                                    @elseif (now()->lt($scheduledStartAt))
                                        <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Scheduled</span>
                                        <p class="mt-2 text-xs text-[#6d7685]">Gun start {{ $scheduledStartAt->format('M j, Y g:i A') }}</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                    @else
                                        <form method="POST" action="{{ route('admin.categories.start', $category) }}" onsubmit="return confirm('Start this category now? The server time will become the official start for every participant in this category and cannot be restarted.');">
                                            @csrf
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                Start Category
                                            </button>
                                        </form>
                                        <p class="mt-2 text-xs text-[#6d7685]">Scheduled gun start {{ $scheduledStartAt->format('M j, Y g:i A') }}. Uses secure server time.</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                            @if ($category->registrations_count || $category->race_results_count)
                                                onsubmit="return confirm('Warning: this category has {{ number_format($category->registrations_count) }} registration(s) and {{ number_format($category->race_results_count) }} result(s). Deleting it will also delete those related records. Continue?');"
                                            @else
                                                onsubmit="return confirm('Delete this category?');"
                                            @endif
                                        >
                                            @csrf
                                            @method('DELETE')
                                            @if ($category->registrations_count || $category->race_results_count)
                                                <input type="hidden" name="delete_with_records" value="1">
                                            @endif
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-[#6d7685]">No categories found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $categories->links() }}
            </div>
        </div>
    </div>
@endsection
