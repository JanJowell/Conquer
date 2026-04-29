@extends('admin.layouts.app')

@section('title', 'Search')

@section('content')
@php
    $resultCount = $users->count() + $events->count() + $registrations->count() + $announcements->count() + $communityPosts->count();
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Global Search</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Search Results</h1>
            <p class="mt-2 text-sm text-[#6d7685]">
                @if ($term !== '')
                    Showing {{ number_format($resultCount) }} result{{ $resultCount === 1 ? '' : 's' }} for "{{ $term }}".
                @else
                    Enter a keyword above to search across users, events, registrations, announcements, and community content.
                @endif
            </p>
        </div>
    </div>

    @if ($term === '')
        <div class="rounded-3xl border border-dashed border-[#d9dee7] bg-white p-8 text-sm text-[#6d7685] shadow-sm">
            Start by typing a name, email, bib number, event title, venue, or announcement keyword in the header search bar.
        </div>
    @elseif ($resultCount === 0)
        <div class="rounded-3xl border border-dashed border-[#d9dee7] bg-white p-8 text-sm text-[#6d7685] shadow-sm">
            No results matched "{{ $term }}".
        </div>
    @endif

    @if ($users->isNotEmpty())
        <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Users</h2>
                <a href="{{ route('admin.users.index', ['search' => $term]) }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">Open users</a>
            </div>
            <div class="divide-y divide-[#eef1f4]">
                @foreach ($users as $result)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                        <div>
                            <p class="font-semibold text-[#111827]">{{ $result->name }}</p>
                            <p class="mt-1 text-sm text-[#6d7685]">{{ $result->email }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#7a8392]">{{ $result->roleLabel() }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @if (auth()->user()->isSuperAdmin())
                                <a href="{{ route('admin.users.show', $result) }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">View</a>
                            @endif
                            <a href="{{ route('admin.users.index', ['search' => $result->email]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1f2937]">Filter List</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($events->isNotEmpty())
        <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Events</h2>
                <a href="{{ route('admin.events.index', ['search' => $term]) }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">Open events</a>
            </div>
            <div class="divide-y divide-[#eef1f4]">
                @foreach ($events as $result)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                        <div>
                            <p class="font-semibold text-[#111827]">{{ $result->title }}</p>
                            <p class="mt-1 text-sm text-[#6d7685]">{{ $result->venue ?: 'Venue not set' }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#7a8392]">{{ $result->event_date?->format('M d, Y') ?: 'Date TBD' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @if (auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]))
                                <a href="{{ route('admin.events.show', $result) }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">View</a>
                            @endif
                            <a href="{{ route('admin.events.index', ['search' => $result->title]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1f2937]">Filter List</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($registrations->isNotEmpty())
        <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Registrations</h2>
                <a href="{{ route('admin.participants.index', ['search' => $term]) }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">Open participants</a>
            </div>
            <div class="divide-y divide-[#eef1f4]">
                @foreach ($registrations as $result)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                        <div>
                            <p class="font-semibold text-[#111827]">{{ $result->user?->name ?: 'Participant unavailable' }}</p>
                            <p class="mt-1 text-sm text-[#6d7685]">{{ $result->event?->title ?: 'Event unavailable' }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#7a8392]">Bib {{ $result->bib_number ?: 'N/A' }} · {{ ucfirst($result->status ?? 'pending') }}</p>
                        </div>
                        <a href="{{ route('admin.participants.index', ['search' => $result->bib_number ?: ($result->user?->email ?: $term)]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1f2937]">Filter List</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($announcements->isNotEmpty())
        <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Announcements</h2>
                <a href="{{ route('admin.announcements.index') }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">Open announcements</a>
            </div>
            <div class="divide-y divide-[#eef1f4]">
                @foreach ($announcements as $result)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-[#111827]">{{ $result->title }}</p>
                            <p class="mt-1 text-sm text-[#6d7685]">{{ \Illuminate\Support\Str::limit($result->content, 130) }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#7a8392]">{{ $result->event?->title ?: 'General announcement' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($communityPosts->isNotEmpty())
        <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-[#eef1f4] px-6 py-4">
                <h2 class="text-xl font-semibold tracking-tight text-[#111827]">Community Posts</h2>
                <a href="{{ route('admin.content.community-posts', ['search' => $term]) }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">Open community</a>
            </div>
            <div class="divide-y divide-[#eef1f4]">
                @foreach ($communityPosts as $result)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-[#111827]">{{ $result->user?->name ?: 'Participant' }}</p>
                            <p class="mt-1 text-sm text-[#6d7685]">{{ \Illuminate\Support\Str::limit($result->content, 130) }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-[#7a8392]">{{ $result->event?->title ?: 'General discussion' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
