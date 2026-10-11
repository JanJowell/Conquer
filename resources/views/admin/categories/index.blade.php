@extends('admin.layouts.app')

@php
    $categoryPageLabel = $selectedEvent?->categorySectionLabel() ?? 'Registration Categories';
@endphp

@section('title', $categoryPageLabel)

@section('content')
    <div class="mx-auto max-w-[1400px] space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Event Setup</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">{{ $categoryPageLabel }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Organize registration options, distances, fees, and slot allocations for each event.</p>
            </div>

            @if (request('event_id'))
                <a href="{{ route('admin.events.edit', request('event_id')) }}" class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Back to Event Setup
                </a>
            @endif
        </div>

        <form method="GET" class="grid gap-3 rounded-[1.75rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:grid-cols-[260px_auto]">
            <div>
                <label for="event_id" class="mb-1.5 block text-sm font-medium text-slate-600">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">Filter</button>
                <a href="{{ route('admin.categories.index') }}" class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
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
                    <tbody class="text-sm text-slate-600">
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
                            <tr class="transition hover:bg-[#f7f8fc]">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-slate-700">{{ $category->name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $category->description ?: 'No description provided' }}</p>
                                    @if ($category->checkpoint_map_image)
                                        <a href="{{ asset('storage/'.$category->checkpoint_map_image) }}" target="_blank" rel="noopener" class="mt-2 inline-flex text-xs font-semibold text-emerald-500">View course map</a>
                                    @else
                                        <p class="mt-2 text-xs text-slate-400">No course map uploaded</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $category->event?->title ?: 'Removed event' }}</td>
                                <td class="px-6 py-5">
                                    <p>{{ number_format((float) $category->distance_km, 2) }} km{{ $category->formattedTypeDetails() ? ' total' : '' }}</p>
                                    @foreach ($category->formattedTypeDetails() as $detail)
                                        <p class="mt-1 text-xs text-slate-400">{{ $detail['label'] }}: {{ $detail['value'] }}</p>
                                    @endforeach
                                </td>
                                <td class="px-6 py-5">
                                    @if (($category->price_cents ?? 0) > 0)
                                        <p class="font-semibold text-slate-700">{{ $category->price_currency ?? 'PHP' }} {{ number_format($category->price_cents / 100, 2) }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $eventPaymentProviders ?: ($legacyPaymentProvider ?: 'No enabled event payment option') }}</p>
                                        @unless ($paymentReady)
                                            <p class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-500">
                                                Missing event payment option
                                            </p>
                                        @endunless
                                    @else
                                        Free
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $category->slot_limit ?: 'Open' }}</td>
                                <td class="px-6 py-5">
                                    <div class="space-y-1 text-xs text-slate-400">
                                        <p>{{ number_format($category->registrations_count) }} registrations</p>
                                        <p>{{ number_format($category->race_results_count) }} results</p>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                                        {{ str($category->status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    @if ($category->started_at)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-500">In Progress</span>
                                        <p class="mt-2 text-xs text-slate-400">Scheduled gun start {{ $scheduledStartAt?->format('M j, Y g:i A') ?: 'time unavailable' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time unavailable' }}</p>
                                        <p class="mt-2 text-xs font-medium text-slate-600">{{ $category->started_at->format('M j, Y g:i:s A') }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Started by {{ $category->startedBy?->name ?: 'administrator' }}</p>
                                    @elseif ($category->status === 'draft')
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Draft</span>
                                        <p class="mt-2 text-xs text-slate-400">Scheduled gun start {{ $scheduledStartAt?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                    @elseif (! $scheduledStartAt)
                                        <span class="text-xs font-medium text-amber-500">Set the event schedule first</span>
                                    @elseif (now()->lt($scheduledStartAt))
                                        <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-500">Scheduled</span>
                                        <p class="mt-2 text-xs text-slate-400">Gun start {{ $scheduledStartAt->format('M j, Y g:i A') }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                    @else
                                        <form method="POST" action="{{ route('admin.categories.start', $category) }}" onsubmit="return confirm('Start this category now? The server time will become the official start for every participant in this category and cannot be restarted.');">
                                            @csrf
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-emerald-50 px-4 text-xs font-semibold text-emerald-500 transition hover:bg-emerald-100">
                                                Start Category
                                            </button>
                                        </form>
                                        <p class="mt-2 text-xs text-slate-400">Scheduled gun start {{ $scheduledStartAt->format('M j, Y g:i A') }}. Uses secure server time.</p>
                                        <p class="mt-1 text-xs text-slate-400">Cutoff/end {{ $category->scheduledEndAt()?->format('M j, Y g:i A') ?: 'time not set' }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="inline-flex h-10 items-center justify-center rounded-full bg-slate-50 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-100">
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
                                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-rose-50 px-4 text-xs font-semibold text-rose-400 transition hover:bg-rose-100">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-500">No categories found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-50 px-6 py-4">
                {{ $categories->links() }}
            </div>
        </div>
    </div>
@endsection