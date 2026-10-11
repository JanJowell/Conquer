@extends('admin.layouts.app')

@section('title', auth()->user()->normalizedRole() === \App\Models\User::ROLE_EXECUTIVE ? 'Events Overview' : 'My Events')

@section('content')
@php
    $canManageEvents = auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]);
@endphp

<div class="mx-auto max-w-[1400px] space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Event Operations</p>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">
                {{ auth()->user()->normalizedRole() === \App\Models\User::ROLE_EXECUTIVE ? 'Events Overview' : 'My Events' }}
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-slate-500">
                Manage event details, monitor registrations, and keep each race day setup current.
            </p>
        </div>

        @if ($canManageEvents)
            <a
                href="{{ route('admin.events.create') }}"
                class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
            >
                <i class="fas fa-plus mr-2 text-xs"></i>
                Create Event
            </a>
        @endif
    </div>

    <form method="GET" class="grid gap-3 rounded-[1.75rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:grid-cols-[minmax(0,1fr)_180px_180px_auto] md:items-end">
        <div>
            <label for="search" class="mb-1.5 block text-sm font-medium text-slate-600">Search</label>
            <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Event title, type, venue, organizer"
                class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-300 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
        </div>
        <div>
            <label for="interest_type" class="mb-1.5 block text-sm font-medium text-slate-600">Event Type</label>
            <select id="interest_type" name="interest_type"
                class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                <option value="">All types</option>
                @foreach (($interestTypes ?? []) as $interestType)
                    <option value="{{ $interestType }}" @selected(request('interest_type') === $interestType)>{{ $interestType }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="mb-1.5 block text-sm font-medium text-slate-600">Status</label>
            <select id="status" name="status"
                class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                <option value="">All statuses</option>
                @foreach (['draft', 'upcoming', 'ongoing', 'completed'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="h-11 rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                Filter
            </button>
            <a href="{{ route('admin.events.index') }}" class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                Clear
            </a>
        </div>
    </form>

    <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead>
                    <tr class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <th class="px-6 py-4">Event</th>
                        <th class="px-6 py-4">Schedule</th>
                        <th class="px-6 py-4">Venue</th>
                        <th class="px-6 py-4">Manager</th>
                        <th class="px-6 py-4">Categories</th>
                        <th class="px-6 py-4">Participants</th>
                        <th class="px-6 py-4">Results</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-600">
                    @forelse ($events as $event)
                        <tr class="align-top transition hover:bg-[#f7f8fc]">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-700">{{ $event->title }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-500">
                                        {{ $event->interest_type ?: 'Type not set' }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $event->organized_by ?: 'Organizer not set' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p>{{ optional($event->event_date)->format('M d, Y') ?: 'TBD' }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $event->start_time?->format('H:i') ?: '--:--' }} to {{ $event->end_time?->format('H:i') ?: '--:--' }}
                                </p>
                            </td>
                            <td class="px-6 py-4">{{ $event->venue ?: '-' }}</td>
                            <td class="px-6 py-4">{{ $event->manager?->name ?: 'Unassigned' }}</td>
                            <td class="px-6 py-4">{{ number_format($event->categories_count) }}</td>
                            <td class="px-6 py-4">{{ number_format($event->registrations_count) }}</td>
                            <td class="px-6 py-4">{{ number_format($event->race_results_count) }}</td>
                            <td class="px-6 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-slate-100 text-slate-500' => $event->effective_status === 'draft',
                                    'bg-sky-50 text-sky-500' => $event->effective_status === 'upcoming',
                                    'bg-emerald-50 text-emerald-500' => $event->effective_status === 'ongoing',
                                    'bg-indigo-50 text-indigo-400' => $event->effective_status === 'completed',
                                    'bg-amber-50 text-amber-500' => ! in_array($event->effective_status, ['draft', 'upcoming', 'ongoing', 'completed'], true),
                                ])>
                                    {{ str($event->effective_status)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button" data-open-event-modal="view-event-{{ $event->id }}" class="inline-flex h-10 items-center justify-center rounded-full bg-slate-50 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-100">
                                        View
                                    </button>
                                    @if ($canManageEvents)
                                        <button type="button" data-open-event-modal="edit-event-{{ $event->id }}" class="inline-flex h-10 items-center justify-center rounded-full bg-slate-50 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-100">
                                            Edit
                                        </button>
                                        <button type="button" data-open-event-modal="delete-event-{{ $event->id }}" class="inline-flex h-10 items-center justify-center rounded-full bg-rose-50 px-4 text-xs font-semibold text-rose-400 transition hover:bg-rose-100">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-500">
                                No events found for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-50 px-6 py-4">
            {{ $events->links() }}
        </div>
    </div>
</div>

@foreach($events as $event)
    @php
        $eventStatusClasses = match ($event->effective_status) {
            'draft' => 'bg-slate-100 text-slate-500',
            'upcoming' => 'bg-sky-50 text-sky-500',
            'ongoing' => 'bg-emerald-50 text-emerald-500',
            'completed' => 'bg-indigo-50 text-indigo-400',
            default => 'bg-amber-50 text-amber-500',
        };
    @endphp

    <div id="view-event-{{ $event->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="view-event-title-{{ $event->id }}">
        <button type="button" data-close-event-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

        <div class="relative flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
            <div class="flex items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Event Details</p>
                    <h2 id="view-event-title-{{ $event->id }}" class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-700">{{ $event->title }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $eventStatusClasses }}">
                            {{ str($event->effective_status)->replace('_', ' ')->title() }}
                        </span>
                        <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-500">
                            {{ $event->interest_type ?: 'Type not set' }}
                        </span>
                    </div>
                </div>

                <button type="button" data-close-event-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Close dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex flex-wrap gap-2 px-6 pt-4">
                <button type="button" data-event-dialog-page="overview" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-full bg-indigo-400 px-4 text-xs font-semibold text-white transition hover:bg-indigo-500">
                    Overview
                </button>
                <button type="button" data-event-dialog-page="operations" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-full bg-slate-50 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-100">
                    Operations
                </button>
            </div>

            <div class="max-h-[calc(90vh-214px)] overflow-y-auto px-6 pb-2 pt-4">
                <div data-event-dialog-panel="overview" class="event-dialog-panel space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-4 md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $event->description ?: 'No description provided.' }}</p>
                        </div>
                        @foreach ($event->formattedTypeDetails() as $detail)
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $detail['label'] }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm font-medium text-slate-700">{{ $detail['value'] }}</p>
                            </div>
                        @endforeach
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ $event->event_date?->format('F d, Y') ?: 'TBD' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Time</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ $event->start_time?->format('H:i') ?: '--:--' }} to {{ $event->end_time?->format('H:i') ?: '--:--' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Venue</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ $event->venue ?: 'Not set' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registration Deadline</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ $event->registration_deadline?->format('F d, Y') ?: 'No deadline set' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Organizer</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ $event->organized_by ?: 'Not set' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Manager</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">{{ $event->manager?->name ?: 'Unassigned' }}</p>
                        </div>
                    </div>
                </div>

                <div data-event-dialog-panel="operations" class="event-dialog-panel hidden space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Categories</p>
                            <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($event->categories_count) }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Participants</p>
                            <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($event->registrations_count) }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Results</p>
                            <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($event->race_results_count) }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        @if($canManageEvents)
                            <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Categories</a>
                            <a href="{{ route('admin.participants.index', ['event_id' => $event->id]) }}" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Participants</a>
                            <a href="{{ route('admin.results.index', ['event_id' => $event->id]) }}" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Results</a>
                        @endif
                        <a href="{{ route('admin.events.show', $event) }}" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Full details page</a>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-50 px-6 py-4">
                <button type="button" data-close-event-modal class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
    </div>

    @if($canManageEvents)
        @php
            $editingThisEvent = (string) old('_editing_event') === (string) $event->id;
        @endphp

        <div id="edit-event-{{ $event->id }}" class="fixed inset-0 z-50 {{ $editingThisEvent && $errors->any() ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="edit-event-title-{{ $event->id }}">
            <button type="button" data-close-event-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                <form method="POST" action="{{ route('admin.events.update', $event) }}" class="flex max-h-[90vh] flex-col">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_event" value="{{ $event->id }}">
                    <input type="hidden" name="manager_id" value="{{ $event->manager_id }}">
                    <input type="hidden" name="banner_image" value="{{ $event->banner_image }}">

                    <div class="flex items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Edit Event</p>
                            <h2 id="edit-event-title-{{ $event->id }}" class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-700">{{ $event->title }}</h2>
                            <p class="mt-2 max-w-2xl text-sm text-slate-500">Update event details here, then manage {{ strtolower($event->categorySectionLabel()) }} on the second page.</p>
                        </div>

                        <button type="button" data-close-event-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="flex flex-col gap-3 px-6 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-event-dialog-page="details" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-full bg-indigo-400 px-4 text-xs font-semibold text-white transition hover:bg-indigo-500">
                                Details
                            </button>
                            <button type="button" data-event-dialog-page="categories" class="event-dialog-tab inline-flex h-10 items-center justify-center rounded-full bg-slate-50 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-100">
                                Categories
                            </button>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-indigo-400 px-5 text-xs font-semibold text-white transition hover:bg-indigo-500">
                                Save Event
                            </button>
                        </div>
                    </div>

                    <div class="max-h-[calc(90vh-274px)] overflow-y-auto px-6 pb-2 pt-4">
                        @if($editingThisEvent && $errors->any())
                            <div class="mb-5 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-400">
                                Please review the event details and try again.
                            </div>
                        @endif

                        <div data-event-dialog-panel="details" class="event-dialog-panel">
                            <section class="rounded-[1.75rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                                <div class="mb-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Event Details</p>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="edit-title-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event Name</label>
                                        <input id="edit-title-{{ $event->id }}" name="title" type="text" value="{{ $editingThisEvent ? old('title', $event->title) : $event->title }}" required class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('title') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="edit-description-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Description</label>
                                        <textarea id="edit-description-{{ $event->id }}" name="description" rows="4" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ $editingThisEvent ? old('description', $event->description) : $event->description }}</textarea>
                                        @if($editingThisEvent) @error('description') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-interest-type-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event Type</label>
                                        <select id="edit-interest-type-{{ $event->id }}" name="interest_type" required class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                            <option value="">Select event type</option>
                                            @foreach (($interestTypes ?? []) as $interestType)
                                                <option value="{{ $interestType }}" @selected(($editingThisEvent ? old('interest_type', $event->interest_type) : $event->interest_type) === $interestType)>{{ $interestType }}</option>
                                            @endforeach
                                        </select>
                                        @if($editingThisEvent) @error('interest_type') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-venue-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Venue</label>
                                        <input id="edit-venue-{{ $event->id }}" name="venue" type="text" value="{{ $editingThisEvent ? old('venue', $event->venue) : $event->venue }}" required class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('venue') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-organized-by-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Organized By</label>
                                        <input id="edit-organized-by-{{ $event->id }}" name="organized_by" type="text" value="{{ $editingThisEvent ? old('organized_by', $event->organized_by) : $event->organized_by }}" class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('organized_by') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-event-date-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event Start Date</label>
                                        <input id="edit-event-date-{{ $event->id }}" name="event_date" type="date" value="{{ $editingThisEvent ? old('event_date', $event->event_date?->format('Y-m-d')) : $event->event_date?->format('Y-m-d') }}" required class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('event_date') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-event-end-date-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event End Date</label>
                                        <input id="edit-event-end-date-{{ $event->id }}" name="event_end_date" type="date" value="{{ $editingThisEvent ? old('event_end_date', $event->event_end_date?->format('Y-m-d') ?? $event->event_date?->format('Y-m-d')) : ($event->event_end_date?->format('Y-m-d') ?? $event->event_date?->format('Y-m-d')) }}" required class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('event_end_date') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-registration-deadline-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Registration Deadline</label>
                                        <input id="edit-registration-deadline-{{ $event->id }}" name="registration_deadline" type="date" value="{{ $editingThisEvent ? old('registration_deadline', $event->registration_deadline?->format('Y-m-d')) : $event->registration_deadline?->format('Y-m-d') }}" class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('registration_deadline') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-start-time-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event Start Time</label>
                                        <input id="edit-start-time-{{ $event->id }}" name="start_time" type="time" value="{{ $editingThisEvent ? old('start_time', $event->start_time?->format('H:i')) : $event->start_time?->format('H:i') }}" required class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('start_time') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="edit-end-time-{{ $event->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Event End Time</label>
                                        <input id="edit-end-time-{{ $event->id }}" name="end_time" type="time" value="{{ $editingThisEvent ? old('end_time', $event->end_time?->format('H:i')) : $event->end_time?->format('H:i') }}" class="h-12 w-full rounded-2xl border-0 bg-slate-50 px-4 text-sm text-slate-700 outline-none focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisEvent) @error('end_time') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div data-event-dialog-panel="categories" class="event-dialog-panel hidden">
                            <section class="rounded-[1.75rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Categories</p>
                                        <h3 class="mt-3 text-lg font-bold tracking-tight text-slate-700">{{ $event->categorySectionLabel() }} setup</h3>
                                        <p class="mt-1 text-sm text-slate-500">Categories use the existing category management page so pricing, slots, payment setup, and registrations stay in one place.</p>
                                    </div>

                                    <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="inline-flex h-11 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                        Manage Categories
                                    </a>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Categories</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($event->categories_count) }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Participants</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($event->registrations_count) }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Results</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($event->race_results_count) }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-500">
                                    Existing categories are edited separately because they can have registration and result history attached.
                                </div>
                            </section>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <div id="delete-event-{{ $event->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="delete-event-title-{{ $event->id }}">
            <button type="button" data-close-event-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative w-full max-w-xl overflow-hidden rounded-[1.75rem] bg-white p-6 shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-400">
                            <i class="fas fa-trash-alt text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-rose-400">Delete Event</p>
                            <h2 id="delete-event-title-{{ $event->id }}" class="mt-3 text-2xl font-bold tracking-tight text-slate-700">Delete this event?</h2>
                        </div>
                    </div>

                    <button type="button" data-close-event-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                    <p class="font-semibold text-slate-700">{{ $event->title }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-500">
                            {{ $event->interest_type ?: 'Type not set' }}
                        </span>
                        <span class="text-xs text-slate-400">{{ optional($event->event_date)->format('M d, Y') ?: 'TBD' }}</span>
                    </div>
                </div>

                <p class="mt-5 text-sm leading-6 text-slate-500">
                    This uses the existing delete route. Deletion is only allowed when the event has no related setup or activity.
                </p>

                <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="mt-6 flex flex-wrap justify-end gap-3">
                    @csrf
                    @method('DELETE')

                    <button type="button" data-close-event-modal class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-rose-50 px-5 text-sm font-semibold text-rose-400 transition hover:bg-rose-100">
                        Delete Event
                    </button>
                </form>
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

                tab.classList.toggle('bg-indigo-400', isActive);
                tab.classList.toggle('text-white', isActive);
                tab.classList.toggle('hover:bg-indigo-500', isActive);
                tab.classList.toggle('bg-slate-50', !isActive);
                tab.classList.toggle('text-slate-500', !isActive);
                tab.classList.toggle('hover:bg-slate-100', !isActive);
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
