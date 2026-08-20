@extends('admin.layouts.app')

@section('title', 'Event Details')

@section('content')
@php
    $canManage = auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EVENT_MANAGER]);
    $canViewAnalytics = auth()->user()->hasAdminRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_EXECUTIVE]);
    $statusItems = collect([
        ['label' => 'Pending', 'value' => $participantStatusCounts['pending'] ?? 0],
        ['label' => 'Approved', 'value' => $participantStatusCounts['approved'] ?? 0],
        ['label' => 'Checked In', 'value' => $participantStatusCounts['checked_in'] ?? 0],
        ['label' => 'Completed', 'value' => $participantStatusCounts['completed'] ?? 0],
        ['label' => 'Rejected', 'value' => $participantStatusCounts['rejected'] ?? 0],
    ]);
    $pendingCount = $participantStatusCounts['pending'] ?? 0;
    $approvedCount = $participantStatusCounts['approved'] ?? 0;
    $checkedInCount = $participantStatusCounts['checked_in'] ?? 0;
    $workflowItems = collect([
        [
            'label' => 'Categories',
            'count' => $event->categories_count,
            'detail' => $event->categories_count > 0 ? 'Race distances are configured' : 'Add race distances first',
            'href' => $event->categories_count > 0
                ? route('admin.categories.index', ['event_id' => $event->id])
                : route('admin.events.edit', $event),
            'enabled' => $canManage,
            'tone' => $event->categories_count > 0 ? 'ready' : 'attention',
        ],
        [
            'label' => 'Checkpoints',
            'count' => $event->checkpoints->count(),
            'detail' => $event->checkpoints->count() > 0 ? 'Route support points are ready' : 'Add route support points',
            'href' => route('admin.content.checkpoints', ['event_id' => $event->id]),
            'enabled' => $canManage,
            'tone' => $event->checkpoints->count() > 0 ? 'ready' : 'attention',
        ],
        [
            'label' => 'Pending Participants',
            'count' => $pendingCount,
            'detail' => $pendingCount > 0 ? 'Review and approve registrations' : 'No approvals waiting',
            'href' => route('admin.participants.index', ['event_id' => $event->id, 'status' => 'pending']),
            'enabled' => $canManage,
            'tone' => $pendingCount > 0 ? 'attention' : 'ready',
        ],
        [
            'label' => 'Check-in Ready',
            'count' => $approvedCount,
            'detail' => $approvedCount > 0 ? 'Approved runners can be checked in' : 'No runners ready for check-in',
            'href' => route('admin.check-in.index', ['event_id' => $event->id]),
            'enabled' => $canManage,
            'tone' => $approvedCount > 0 ? 'active' : 'muted',
        ],
        [
            'label' => 'Awaiting Results',
            'count' => $checkedInCount,
            'detail' => $checkedInCount > 0 ? 'Checked-in runners need results' : 'No results waiting',
            'href' => route('admin.results.index', ['event_id' => $event->id]),
            'enabled' => $canManage,
            'tone' => $checkedInCount > 0 ? 'active' : 'muted',
        ],
    ]);
@endphp

<div class="space-y-6">
    @if ($event->banner_image)
        <section class="overflow-hidden rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <button type="button" class="block w-full text-left" aria-label="Open full banner preview" data-banner-preview-open>
                <img src="{{ asset('storage/'.$event->banner_image) }}" alt="{{ $event->title }} banner" class="h-64 w-full object-cover transition hover:opacity-95">
            </button>

            <dialog data-banner-preview-dialog class="w-full max-w-6xl rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-black/80">
                <div class="max-h-[90vh] overflow-hidden rounded-3xl bg-white">
                    <div class="flex items-center justify-between border-b border-[#eef1f4] px-5 py-3">
                        <p class="text-sm font-semibold text-[#151b26]">{{ $event->title }} banner</p>
                        <button type="button" class="inline-flex h-9 items-center justify-center rounded-xl border border-[#d9dee7] px-3 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]" data-banner-preview-close>
                            Close
                        </button>
                    </div>
                    <img src="{{ asset('storage/'.$event->banner_image) }}" alt="{{ $event->title }} banner preview" class="max-h-[calc(90vh-3.75rem)] w-full object-contain">
                </div>
            </dialog>
        </section>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Details</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">{{ $event->title }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full bg-[#eef1f4] px-3 py-1 text-xs font-semibold text-[#4f5a6a]">
                    {{ $event->interest_type ?: 'Type not set' }}
                </span>
                <span class="text-xs font-medium uppercase tracking-[0.18em] text-[#7a8495]">
                    {{ str($event->effective_status)->replace('_', ' ')->title() }}
                </span>
            </div>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[#6d7685]">{{ $event->description ?: 'No description provided.' }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.events.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                Back
            </a>
            @if ($canManage)
                <a href="{{ route('admin.events.edit', $event) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39]">
                    Edit Event
                </a>
            @endif
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Status</p>
            <p class="mt-3 text-2xl font-semibold tracking-tight text-[#151b26]">{{ str($event->effective_status)->replace('_', ' ')->title() }}</p>
        </article>
        <article class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Event Type</p>
            <p class="mt-3 text-2xl font-semibold tracking-tight text-[#151b26]">{{ $event->interest_type ?: 'Not set' }}</p>
        </article>
        <article class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Participants</p>
            <p class="mt-3 text-2xl font-semibold tracking-tight text-[#151b26]">{{ number_format($event->registrations_count) }}</p>
        </article>
        <article class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-[#6d7685]">Categories</p>
            <p class="mt-3 text-2xl font-semibold tracking-tight text-[#151b26]">{{ number_format($event->categories_count) }}</p>
        </article>
    </section>

    @if ($canManage)
        <section class="rounded-3xl border {{ $readinessErrors === [] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} p-5 text-sm leading-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="font-semibold">{{ $readinessErrors === [] ? 'This event is ready for mobile registration.' : 'This event is not ready for mobile registration yet.' }}</p>
                    @if ($readinessErrors === [])
                        <p class="mt-1">All required public details, category, and payment setup are complete.</p>
                    @else
                        <ul class="mt-3 grid gap-2 md:grid-cols-2">
                            @foreach ($readinessErrors as $readinessError)
                                <li class="flex gap-2">
                                    <span aria-hidden="true">-</span>
                                    <span>{{ ucfirst($readinessError) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($readinessErrors !== [])
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.events.edit', $event) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-950 transition hover:bg-amber-100">
                            Edit Event
                        </a>
                        <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-amber-900 px-4 text-sm font-semibold text-white transition hover:bg-amber-950">
                            Categories
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($canManage || $canViewAnalytics)
        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">Event Command Center</h2>
                    <p class="mt-1 text-sm text-[#6d7685]">Move this event from setup through race day without leaving the event context.</p>
                </div>
                @if ($canManage)
                    <a href="{{ route('admin.announcements.create', ['event_id' => $event->id]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                        New Announcement
                    </a>
                @endif
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($workflowItems as $item)
                    @php
                        $toneClasses = match ($item['tone']) {
                            'attention' => 'border-amber-200 bg-amber-50 text-amber-800',
                            'active' => 'border-sky-200 bg-sky-50 text-sky-800',
                            'ready' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            default => 'border-slate-200 bg-slate-50 text-slate-600',
                        };
                    @endphp

                    @if ($item['enabled'])
                        <a href="{{ $item['href'] }}" class="block rounded-2xl border border-[#d9dee7] p-4 transition hover:border-[#b8c0cc] hover:bg-[#fafbfc]">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-[#151b26]">{{ $item['label'] }}</p>
                                <span class="inline-flex min-w-9 justify-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $toneClasses }}">{{ number_format($item['count']) }}</span>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-[#6d7685]">{{ $item['detail'] }}</p>
                        </a>
                    @endif
                @endforeach

                @if ($canViewAnalytics)
                    <a href="{{ route('admin.analytics') }}" class="block rounded-2xl border border-[#d9dee7] p-4 transition hover:border-[#b8c0cc] hover:bg-[#fafbfc]">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-[#151b26]">Analytics</p>
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">View</span>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-[#6d7685]">Compare this event against system performance.</p>
                    </a>
                @endif
            </div>
        </section>
    @endif

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_360px]">
        <div class="space-y-6">
            <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">Schedule and Location</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Event Type</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->interest_type ?: 'Not set' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Date</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->event_date?->format('F d, Y') ?: 'TBD' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Time</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->start_time?->format('H:i') ?: '--:--' }} to {{ $event->end_time?->format('H:i') ?: '--:--' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Venue</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->venue }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Registration Deadline</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->registration_deadline?->format('F d, Y') ?: 'No deadline set' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Organizer</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->organized_by ?: 'Not set' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Manager</p>
                        <p class="mt-2 text-sm text-[#202733]">{{ $event->manager?->name ?: 'Unassigned' }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">{{ $event->interest_type ?: 'Event' }} Details</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    @forelse ($event->formattedTypeDetails() as $detail)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">{{ $detail['label'] }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-[#202733]">{{ $detail['value'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-[#6d7685] md:col-span-2">No type-specific details have been added yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#eef1f4] px-6 py-4">
                    <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">{{ $event->categorySectionLabel() }}</h2>
                    @if ($canManage)
                        <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="text-sm font-semibold text-[#151b26]">Manage categories</a>
                    @endif
                </div>
                <div class="divide-y divide-[#eef1f4]">
                    @forelse ($event->categories as $category)
                        <div class="grid gap-3 px-6 py-4 text-sm md:grid-cols-[minmax(0,1fr)_120px_130px_120px_120px]">
                            <div>
                                <p class="font-semibold text-[#151b26]">{{ $category->name }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">{{ $category->description ?: 'No description' }}</p>
                            </div>
                            <p>{{ number_format((float) $category->distance_km, 2) }} km</p>
                            <p>
                                {{ $category->scheduled_start_time?->format('g:i A')
                                    ?: ($event->start_time?->format('g:i A') ?? 'Schedule not set') }}
                            </p>
                            <p>{{ $category->slot_limit ?: 'Open slots' }}</p>
                            <p>{{ number_format($category->registrations_count) }} registered</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No categories configured yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#eef1f4] px-6 py-4">
                    <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">Recent Registrations</h2>
                    @if ($canManage)
                        <a href="{{ route('admin.participants.index', ['event_id' => $event->id]) }}" class="text-sm font-semibold text-[#151b26]">View participants</a>
                    @endif
                </div>
                <div class="divide-y divide-[#eef1f4]">
                    @forelse ($recentRegistrations as $registration)
                        <div class="grid gap-3 px-6 py-4 text-sm md:grid-cols-[minmax(0,1fr)_180px_120px]">
                            <div>
                                <p class="font-semibold text-[#151b26]">{{ $registration->user?->name ?: 'Unknown participant' }}</p>
                                <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->user?->email ?: 'No email' }}</p>
                            </div>
                            <p>{{ $registration->category?->name ?: 'No category' }}</p>
                            <p>{{ str($registration->status)->replace('_', ' ')->title() }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-[#6d7685]">No registrations yet.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">Participant Status</h2>
                <div class="mt-5 space-y-4">
                    @foreach ($statusItems as $item)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-[#6d7685]">{{ $item['label'] }}</span>
                            <span class="font-semibold text-[#151b26]">{{ number_format($item['value']) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">Quick Links</h2>
                <div class="mt-5 space-y-3">
                    @if ($canManage)
                        <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Categories</a>
                        <a href="{{ route('admin.content.checkpoints', ['event_id' => $event->id]) }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Checkpoints</a>
                        <a href="{{ route('admin.participants.index', ['event_id' => $event->id]) }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Participants</a>
                        <a href="{{ route('admin.check-in.index', ['event_id' => $event->id]) }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Check-in</a>
                        <a href="{{ route('admin.results.index', ['event_id' => $event->id]) }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Results</a>
                        <a href="{{ route('admin.announcements.index', ['event_id' => $event->id]) }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Announcements</a>
                    @endif
                    @if ($canViewAnalytics)
                        <a href="{{ route('admin.analytics') }}" class="block rounded-2xl border border-[#d9dee7] px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Analytics</a>
                    @endif
                </div>
            </section>

            <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold tracking-tight text-[#151b26]">Checkpoints</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($event->checkpoints->take(5) as $checkpoint)
                        <div class="rounded-2xl border border-[#eef1f4] p-4">
                            <p class="text-sm font-semibold text-[#151b26]">{{ $checkpoint->name }}</p>
                            <p class="mt-1 text-xs text-[#6d7685]">{{ $checkpoint->location ?: str($checkpoint->type)->replace('_', ' ')->title() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-[#6d7685]">No checkpoints configured.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </section>
</div>

@if ($event->banner_image)
    <script>
        (() => {
            const dialog = document.querySelector('[data-banner-preview-dialog]');
            const openButton = document.querySelector('[data-banner-preview-open]');
            const closeButton = document.querySelector('[data-banner-preview-close]');

            openButton?.addEventListener('click', () => dialog?.showModal());
            closeButton?.addEventListener('click', () => dialog?.close());
            dialog?.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        })();
    </script>
@endif
@endsection
