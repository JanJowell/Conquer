@extends('admin.layouts.app')

@section('title', auth()->user()->normalizedRole() === \App\Models\User::ROLE_EXECUTIVE ? 'Events Overview' : 'My Events')

@section('content')
@php
    $canManageEvents = auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]);
@endphp

<div class="relative min-h-screen overflow-hidden bg-[#eaf2f9] px-4 py-6 sm:px-6 lg:px-8">
    <div class="pointer-events-none absolute -top-24 left-8 h-72 w-72 rounded-full bg-sky-300/35 blur-3xl"></div>
    <div class="pointer-events-none absolute top-40 right-0 h-96 w-96 rounded-full bg-cyan-300/25 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-indigo-300/20 blur-3xl"></div>

    <div class="relative mx-auto max-w-[1800px] space-y-6">
        <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-white/35 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.10)] backdrop-blur-2xl">
            <div class="rounded-[1.6rem] border border-white/60 bg-white/30 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-4 py-2 text-xs font-bold uppercase tracking-[0.24em] text-sky-700 shadow-sm backdrop-blur-xl">
                            <span class="h-2.5 w-2.5 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                            Event Operations
                        </div>

                        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">
                            {{ auth()->user()->normalizedRole() === \App\Models\User::ROLE_EXECUTIVE ? 'Events Overview' : 'My Events' }}
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            Manage event details, monitor registrations, and keep each race day setup current.
                        </p>
                    </div>

                    @if ($canManageEvents)
                        <a
                            href="{{ route('admin.events.create') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-950/90 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800"
                        >
                            <i class="fas fa-plus mr-2 text-xs"></i>
                            Create Event
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-[1.6rem] border border-white/60 bg-white/35 p-4 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40 md:grid-cols-[minmax(0,1fr)_180px_180px_auto] md:items-end">
            <div>
                <label for="search" class="mb-2 block text-sm font-semibold text-slate-700">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Event title, type, venue, organizer"
                    class="h-11 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
            </div>
            <div>
                <label for="interest_type" class="mb-2 block text-sm font-semibold text-slate-700">Event Type</label>
                <select id="interest_type" name="interest_type"
                    class="h-11 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                    <option value="">All types</option>
                    @foreach (($interestTypes ?? []) as $interestType)
                        <option value="{{ $interestType }}" @selected(request('interest_type') === $interestType)>{{ $interestType }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                <select id="status" name="status"
                    class="h-11 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'upcoming', 'ongoing', 'completed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-xl bg-slate-950/90 px-5 text-sm font-semibold text-white shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200">
                    Filter
                </button>
                <a href="{{ route('admin.events.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65 focus:outline-none focus:ring-4 focus:ring-sky-100/70">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
            <div class="overflow-x-auto p-3">
                <table class="min-w-full border-separate border-spacing-y-3 text-left">
                    <thead>
                        <tr class="text-xs font-bold uppercase tracking-[0.18em] text-slate-600">
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Venue</th>
                            <th class="px-4 py-3">Manager</th>
                            <th class="px-4 py-3">Categories</th>
                            <th class="px-4 py-3">Participants</th>
                            <th class="px-4 py-3">Results</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-700">
                        @forelse ($events as $event)
                            <tr class="align-top">
                                <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    <p class="font-bold text-slate-950">{{ $event->title }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full border border-sky-200/70 bg-sky-100/70 px-2.5 py-1 text-xs font-bold text-sky-700 backdrop-blur-xl">
                                            {{ $event->interest_type ?: 'Type not set' }}
                                        </span>
                                        <span class="text-xs text-slate-500">{{ $event->organized_by ?: 'Organizer not set' }}</span>
                                    </div>
                                </td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    <p>{{ optional($event->event_date)->format('M d, Y') ?: 'TBD' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $event->start_time?->format('H:i') ?: '--:--' }} to {{ $event->end_time?->format('H:i') ?: '--:--' }}
                                    </p>
                                </td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ $event->venue ?: '-' }}</td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ $event->manager?->name ?: 'Unassigned' }}</td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ number_format($event->categories_count) }}</td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ number_format($event->registrations_count) }}</td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">{{ number_format($event->race_results_count) }}</td>
                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    <span @class([
                                        'inline-flex rounded-full border px-3 py-1.5 text-xs font-bold backdrop-blur-xl',
                                        'border-slate-200/70 bg-slate-100/70 text-slate-600' => $event->effective_status === 'draft',
                                        'border-sky-200/70 bg-sky-100/70 text-sky-700' => $event->effective_status === 'upcoming',
                                        'border-emerald-200/70 bg-emerald-100/70 text-emerald-700' => $event->effective_status === 'ongoing',
                                        'border-indigo-200/70 bg-indigo-100/70 text-indigo-700' => $event->effective_status === 'completed',
                                        'border-amber-200/70 bg-amber-100/70 text-amber-700' => ! in_array($event->effective_status, ['draft', 'upcoming', 'ongoing', 'completed'], true),
                                    ])>
                                        {{ str($event->effective_status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" data-open-event-modal="view-event-{{ $event->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                                            View
                                        </button>
                                        @if ($canManageEvents)
                                            <button type="button" data-open-event-modal="edit-event-{{ $event->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                                                Edit
                                            </button>
                                            <button type="button" data-open-event-modal="delete-event-{{ $event->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200/70 bg-rose-100/60 px-4 text-xs font-bold text-rose-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-rose-100">
                                                Delete
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="rounded-2xl border border-white/60 bg-white/40 px-6 py-12 text-center text-sm text-slate-500 backdrop-blur-xl">
                                    No events found for the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-white/50 bg-white/25 px-6 py-4 backdrop-blur-xl">
                {{ $events->links() }}
            </div>
        </div>
    </div>
</div>

@foreach($events as $event)
    @php
        $eventStatusClasses = match ($event->effective_status) {
            'draft' => 'border-slate-200/70 bg-slate-100/70 text-slate-600',
            'upcoming' => 'border-sky-200/70 bg-sky-100/70 text-sky-700',
            'ongoing' => 'border-emerald-200/70 bg-emerald-100/70 text-emerald-700',
            'completed' => 'border-indigo-200/70 bg-indigo-100/70 text-indigo-700',
            default => 'border-amber-200/70 bg-amber-100/70 text-amber-700',
        };
    @endphp

    <div id="view-event-{{ $event->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="view-event-title-{{ $event->id }}">
        <button type="button" data-close-event-modal class="absolute inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

        <div class="relative max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
            <div class="pointer-events-none absolute -top-24 left-10 h-56 w-56 rounded-full bg-sky-300/35 blur-3xl"></div>
            <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-cyan-300/25 blur-3xl"></div>

            <div class="relative flex items-start justify-between gap-4 rounded-[1.6rem] border border-white/60 bg-white/35 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-700 shadow-sm backdrop-blur-xl">
                        <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                        Event Details
                    </div>
                    <h2 id="view-event-title-{{ $event->id }}" class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-950">{{ $event->title }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full border px-3 py-1.5 text-xs font-bold backdrop-blur-xl {{ $eventStatusClasses }}">
                            {{ str($event->effective_status)->replace('_', ' ')->title() }}
                        </span>
                        <span class="inline-flex rounded-full border border-sky-200/70 bg-sky-100/70 px-3 py-1.5 text-xs font-bold text-sky-700 backdrop-blur-xl">
                            {{ $event->interest_type ?: 'Type not set' }}
                        </span>
                    </div>
                </div>

                <button type="button" data-close-event-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="relative mt-4 flex flex-wrap gap-2 rounded-[1.3rem] border border-white/60 bg-white/30 p-2 shadow-sm backdrop-blur-xl">
                <button type="button" data-event-dialog-page="overview" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-xl bg-slate-950/90 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800">
                    Overview
                </button>
                <button type="button" data-event-dialog-page="operations" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                    Operations
                </button>
            </div>

            <div class="relative max-h-[calc(90vh-214px)] overflow-y-auto px-2 pb-2 pt-4 sm:px-0">
                <div data-event-dialog-panel="overview" class="event-dialog-panel space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl md:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Description</p>
                            <p class="mt-2 text-sm leading-6 text-slate-800">{{ $event->description ?: 'No description provided.' }}</p>
                        </div>
                        @foreach ($event->formattedTypeDetails() as $detail)
                            <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{{ $detail['label'] }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm font-medium text-slate-800">{{ $detail['value'] }}</p>
                            </div>
                        @endforeach
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Date</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $event->event_date?->format('F d, Y') ?: 'TBD' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Time</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $event->start_time?->format('H:i') ?: '--:--' }} to {{ $event->end_time?->format('H:i') ?: '--:--' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Venue</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $event->venue ?: 'Not set' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Registration Deadline</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $event->registration_deadline?->format('F d, Y') ?: 'No deadline set' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Organizer</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $event->organized_by ?: 'Not set' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Manager</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $event->manager?->name ?: 'Unassigned' }}</p>
                        </div>
                    </div>
                </div>

                <div data-event-dialog-panel="operations" class="event-dialog-panel hidden space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Categories</p>
                            <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($event->categories_count) }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Participants</p>
                            <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($event->registrations_count) }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Results</p>
                            <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($event->race_results_count) }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        @if($canManageEvents)
                            <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="rounded-2xl border border-white/60 bg-white/40 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65">Categories</a>
                            <a href="{{ route('admin.participants.index', ['event_id' => $event->id]) }}" class="rounded-2xl border border-white/60 bg-white/40 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65">Participants</a>
                            <a href="{{ route('admin.results.index', ['event_id' => $event->id]) }}" class="rounded-2xl border border-white/60 bg-white/40 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65">Results</a>
                        @endif
                        <a href="{{ route('admin.events.show', $event) }}" class="rounded-2xl border border-white/60 bg-white/40 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65">Full details page</a>
                    </div>
                </div>
            </div>

            <div class="relative mt-4 flex flex-wrap justify-end gap-3 rounded-[1.4rem] border border-white/60 bg-white/35 px-6 py-4 shadow-sm backdrop-blur-xl">
                <button type="button" data-close-event-modal class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                    Close
                </button>
                @if($canManageEvents)
                    <a href="{{ route('admin.events.edit', $event) }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950/90 px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                        Edit Event
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($canManageEvents)
        @php
            $editingThisEvent = (string) old('_editing_event') === (string) $event->id;
        @endphp

        <div id="edit-event-{{ $event->id }}" class="fixed inset-0 z-50 {{ $editingThisEvent && $errors->any() ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="edit-event-title-{{ $event->id }}">
            <button type="button" data-close-event-modal class="absolute inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

            <div class="relative max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="pointer-events-none absolute -top-24 left-10 h-56 w-56 rounded-full bg-sky-300/35 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-cyan-300/25 blur-3xl"></div>

                <form method="POST" action="{{ route('admin.events.update', $event) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_event" value="{{ $event->id }}">
                    <input type="hidden" name="manager_id" value="{{ $event->manager_id }}">
                    <input type="hidden" name="banner_image" value="{{ $event->banner_image }}">

                    <div class="relative flex items-start justify-between gap-4 rounded-[1.6rem] border border-white/60 bg-white/35 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                        <div class="min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-700 shadow-sm backdrop-blur-xl">
                                <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                                Edit Event
                            </div>
                            <h2 id="edit-event-title-{{ $event->id }}" class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-950">{{ $event->title }}</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Update event details here, then manage {{ strtolower($event->categorySectionLabel()) }} on the second page.</p>
                        </div>

                        <button type="button" data-close-event-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="relative mt-4 flex flex-col gap-3 rounded-[1.3rem] border border-white/60 bg-white/30 p-2 shadow-sm backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-event-dialog-page="details" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-xl bg-slate-950/90 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800">
                                Details
                            </button>
                            <button type="button" data-event-dialog-page="categories" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                                Categories
                            </button>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950/90 px-4 text-xs font-bold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                                Save Event
                            </button>
                        </div>
                    </div>

                    <div class="relative max-h-[calc(90vh-274px)] overflow-y-auto px-2 pb-2 pt-4 sm:px-0">
                        @if($editingThisEvent && $errors->any())
                            <div class="mb-5 rounded-2xl border border-rose-200/70 bg-rose-100/70 px-4 py-3 text-sm font-bold text-rose-700 shadow-sm backdrop-blur-xl">
                                Please review the event details and try again.
                            </div>
                        @endif

                        <div data-event-dialog-panel="details" class="event-dialog-panel">
                            <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40">
                                <div class="mb-5">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-slate-600 shadow-sm backdrop-blur-xl">
                                        Event Details
                                    </div>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="edit-title-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Event Name</label>
                                        <input id="edit-title-{{ $event->id }}" name="title" type="text" value="{{ $editingThisEvent ? old('title', $event->title) : $event->title }}" required class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('title') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="edit-description-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Description</label>
                                        <textarea id="edit-description-{{ $event->id }}" name="description" rows="4" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ $editingThisEvent ? old('description', $event->description) : $event->description }}</textarea>
                                        @if($editingThisEvent) @error('description') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-interest-type-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Event Type</label>
                                        <select id="edit-interest-type-{{ $event->id }}" name="interest_type" required class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                            <option value="">Select event type</option>
                                            @foreach (($interestTypes ?? []) as $interestType)
                                                <option value="{{ $interestType }}" @selected(($editingThisEvent ? old('interest_type', $event->interest_type) : $event->interest_type) === $interestType)>{{ $interestType }}</option>
                                            @endforeach
                                        </select>
                                        @if($editingThisEvent) @error('interest_type') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-venue-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Venue</label>
                                        <input id="edit-venue-{{ $event->id }}" name="venue" type="text" value="{{ $editingThisEvent ? old('venue', $event->venue) : $event->venue }}" required class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('venue') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-organized-by-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Organized By</label>
                                        <input id="edit-organized-by-{{ $event->id }}" name="organized_by" type="text" value="{{ $editingThisEvent ? old('organized_by', $event->organized_by) : $event->organized_by }}" class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('organized_by') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-event-date-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Event Date</label>
                                        <input id="edit-event-date-{{ $event->id }}" name="event_date" type="date" value="{{ $editingThisEvent ? old('event_date', $event->event_date?->format('Y-m-d')) : $event->event_date?->format('Y-m-d') }}" required class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('event_date') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-registration-deadline-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Registration Deadline</label>
                                        <input id="edit-registration-deadline-{{ $event->id }}" name="registration_deadline" type="date" value="{{ $editingThisEvent ? old('registration_deadline', $event->registration_deadline?->format('Y-m-d')) : $event->registration_deadline?->format('Y-m-d') }}" class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('registration_deadline') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-start-time-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Start Time</label>
                                        <input id="edit-start-time-{{ $event->id }}" name="start_time" type="time" value="{{ $editingThisEvent ? old('start_time', $event->start_time?->format('H:i')) : $event->start_time?->format('H:i') }}" required class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('start_time') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-end-time-{{ $event->id }}" class="mb-2 block text-sm font-semibold text-slate-800">End Time</label>
                                        <input id="edit-end-time-{{ $event->id }}" name="end_time" type="time" value="{{ $editingThisEvent ? old('end_time', $event->end_time?->format('H:i')) : $event->end_time?->format('H:i') }}" class="h-12 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisEvent) @error('end_time') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div data-event-dialog-panel="categories" class="event-dialog-panel hidden">
                            <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40">
                                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-slate-600 shadow-sm backdrop-blur-xl">
                                            Categories
                                        </div>
                                        <h3 class="mt-3 text-lg font-bold tracking-tight text-slate-950">{{ $event->categorySectionLabel() }} setup</h3>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">Categories use the existing category management page so pricing, slots, payment setup, and registrations stay in one place.</p>
                                    </div>

                                    <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950/90 px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                                        Manage Categories
                                    </a>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Categories</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($event->categories_count) }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Participants</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($event->registrations_count) }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Results</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($event->race_results_count) }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl border border-amber-200/70 bg-amber-100/60 p-4 text-sm leading-6 text-amber-800 shadow-sm backdrop-blur-xl">
                                    Existing categories are edited separately because they can have registration and result history attached.
                                </div>
                            </section>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <div id="delete-event-{{ $event->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="delete-event-title-{{ $event->id }}">
            <button type="button" data-close-event-modal class="absolute inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

            <div class="relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="pointer-events-none absolute -top-20 left-8 h-48 w-48 rounded-full bg-rose-300/30 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 h-52 w-52 rounded-full bg-sky-300/25 blur-3xl"></div>

                <div class="relative rounded-[1.6rem] border border-white/60 bg-white/35 p-6 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-rose-200/70 bg-rose-100/70 text-rose-700 shadow-sm backdrop-blur-xl">
                                <i class="fas fa-trash-alt text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-rose-700 shadow-sm backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full bg-rose-500 shadow-[0_0_12px_rgba(244,63,94,0.75)]"></span>
                                    Delete Event
                                </div>
                                <h2 id="delete-event-title-{{ $event->id }}" class="mt-3 text-2xl font-bold tracking-tight text-slate-950">Delete this event?</h2>
                            </div>
                        </div>

                        <button type="button" data-close-event-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="mt-5 rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                        <p class="font-bold text-slate-950">{{ $event->title }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full border border-sky-200/70 bg-sky-100/70 px-2.5 py-1 text-xs font-bold text-sky-700 backdrop-blur-xl">
                                {{ $event->interest_type ?: 'Type not set' }}
                            </span>
                            <span class="text-xs text-slate-500">{{ optional($event->event_date)->format('M d, Y') ?: 'TBD' }}</span>
                        </div>
                    </div>

                    <p class="mt-5 text-sm leading-6 text-slate-600">
                        This uses the existing delete route. Deletion is only allowed when the event has no related setup or activity.
                    </p>

                    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="mt-6 flex flex-wrap justify-end gap-3">
                        @csrf
                        @method('DELETE')

                        <button type="button" data-close-event-modal class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-rose-200/70 bg-rose-600 px-5 text-sm font-semibold text-white shadow-lg shadow-rose-200/50 transition hover:-translate-y-0.5 hover:bg-rose-700">
                            Delete Event
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

<script>
    (function () {
        const closeModal = (modal) => {
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        };

        const openModal = (modal) => {
            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        };

        const setDialogPage = (dialog, page) => {
            dialog.querySelectorAll('[data-event-dialog-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.eventDialogPanel !== page);
            });

            dialog.querySelectorAll('[data-event-dialog-page]').forEach((tab) => {
                const isActive = tab.dataset.eventDialogPage === page;

                tab.classList.toggle('bg-slate-950/90', isActive);
                tab.classList.toggle('text-white', isActive);
                tab.classList.toggle('border', !isActive);
                tab.classList.toggle('border-white/60', !isActive);
                tab.classList.toggle('bg-white/45', !isActive);
                tab.classList.toggle('text-slate-700', !isActive);
            });
        };

        document.querySelectorAll('[data-open-event-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById(button.dataset.openEventModal);

                if (modal?.id.startsWith('view-event-')) {
                    setDialogPage(modal, 'overview');
                }

                if (modal?.id.startsWith('edit-event-')) {
                    setDialogPage(modal, 'details');
                }

                openModal(modal);
            });
        });

        document.querySelectorAll('[data-close-event-modal]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
        });

        document.querySelectorAll('[data-event-dialog-page]').forEach((button) => {
            button.addEventListener('click', () => setDialogPage(button.closest('[role="dialog"]'), button.dataset.eventDialogPage));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            document.querySelectorAll('[role="dialog"].flex').forEach(closeModal);
        });

        if (document.querySelector('[role="dialog"].flex')) {
            document.body.classList.add('overflow-hidden');
        }
    })();
</script>
@endsection
