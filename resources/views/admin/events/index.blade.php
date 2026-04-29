@extends('admin.layouts.app')

@section('title', auth()->user()->normalizedRole() === \App\Models\User::ROLE_EXECUTIVE ? 'Events Overview' : 'My Events')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">
                    {{ auth()->user()->normalizedRole() === \App\Models\User::ROLE_EXECUTIVE ? 'Events Overview' : 'My Events' }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">
                    Manage event details, monitor registrations, and keep each race day setup current.
                </p>
            </div>

            @if (auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]))
                <a
                    href="{{ route('admin.events.create') }}"
                    class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]"
                >
                    <i class="fas fa-plus mr-2 text-xs"></i>
                    Create Event
                </a>
            @endif
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_200px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Event title, venue, organizer"
                    class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                <select id="status" name="status"
                    class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'published', 'ongoing', 'completed', 'upcoming'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="h-11 rounded-2xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    Filter
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Schedule</th>
                            <th class="px-6 py-4">Venue</th>
                            <th class="px-6 py-4">Manager</th>
                            <th class="px-6 py-4">Categories</th>
                            <th class="px-6 py-4">Participants</th>
                            <th class="px-6 py-4">Results</th>
                            <th class="px-6 py-4">Status</th>
                            @if (auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]))
                                <th class="px-6 py-4 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($events as $event)
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $event->title }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $event->organized_by ?: 'Organizer not set' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ optional($event->event_date)->format('M d, Y') ?: 'TBD' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">
                                        {{ $event->start_time?->format('H:i') ?: '--:--' }} to {{ $event->end_time?->format('H:i') ?: '--:--' }}
                                    </p>
                                </td>
                                <td class="px-6 py-5">{{ $event->venue }}</td>
                                <td class="px-6 py-5">{{ $event->manager?->name ?: 'Unassigned' }}</td>
                                <td class="px-6 py-5">{{ number_format($event->categories_count) }}</td>
                                <td class="px-6 py-5">{{ number_format($event->registrations_count) }}</td>
                                <td class="px-6 py-5">{{ number_format($event->race_results_count) }}</td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full bg-[#eef1f4] px-3 py-1 text-xs font-semibold text-[#4f5a6a]">
                                        {{ str($event->effective_status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                @if (auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]))
                                    <td class="px-6 py-5">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.events.edit', $event) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm('Delete this event?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]) ? 9 : 8 }}" class="px-6 py-12 text-center text-sm text-[#6d7685]">No events found for the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $events->links() }}
            </div>
        </div>
    </div>
@endsection
