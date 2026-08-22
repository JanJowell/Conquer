@extends('layouts.app')

@section('content')
@php
    $displayName = $user->name ?? 'Super Admin';
    $displayEmail = $user->email ?? 'admin@eventmanager.com';

    $summaryCards = [
        [
            'label' => 'Total Users',
            'value' => $stats['users'] ?? 0,
            'icon' => 'fa-users',
            'iconBg' => 'bg-sky-100 text-sky-700',
            'meta' => ($stats['active_users_today'] ?? 0) . ' active today',
        ],
        [
            'label' => 'Total Events',
            'value' => $stats['events'] ?? 0,
            'icon' => 'fa-calendar-days',
            'iconBg' => 'bg-emerald-100 text-emerald-700',
            'meta' => ($stats['upcoming_events'] ?? 0) . ' upcoming events',
        ],
        [
            'label' => 'Registrations',
            'value' => $stats['registrations'] ?? 0,
            'icon' => 'fa-clipboard-check',
            'iconBg' => 'bg-amber-100 text-amber-700',
            'meta' => ($stats['pending_registrations'] ?? 0) . ' pending review',
        ],
        [
            'label' => 'Race Results',
            'value' => $stats['results'] ?? 0,
            'icon' => 'fa-trophy',
            'iconBg' => 'bg-violet-100 text-violet-700',
            'meta' => 'Performance data published',
        ],
    ];

    $quickActions = [
        ['label' => 'Create Event', 'href' => '/admin/events/create', 'icon' => 'fa-calendar-plus', 'style' => 'from-sky-600 to-blue-700'],
        ['label' => 'Register Participant', 'href' => '/admin/users/create', 'icon' => 'fa-user-plus', 'style' => 'from-emerald-600 to-teal-700'],
        ['label' => 'Post Announcement', 'href' => '/admin/announcements/create', 'icon' => 'fa-bullhorn', 'style' => 'from-amber-500 to-orange-600'],
        ['label' => 'Upload Results', 'href' => '/admin/events', 'icon' => 'fa-trophy', 'style' => 'from-violet-600 to-fuchsia-700'],
    ];

    $adminTools = [
        ['label' => 'User Management', 'href' => '/admin/users', 'icon' => 'fa-users-gear', 'desc' => 'Manage roles, access, and account status'],
        ['label' => 'Community Moderation', 'href' => '/admin/content/community-posts', 'icon' => 'fa-comments', 'desc' => 'Review reports and flagged content'],
        ['label' => 'Training Modules', 'href' => '/admin/content/training-modules', 'icon' => 'fa-graduation-cap', 'desc' => 'Keep onboarding materials current'],
        ['label' => 'Security Center', 'href' => '/admin/security/dashboard', 'icon' => 'fa-shield-halved', 'desc' => 'Monitor threats and policy actions'],
    ];

    $controlSummary = [
        ['label' => 'Announcements', 'value' => $stats['announcements'] ?? 0, 'icon' => 'fa-bullhorn', 'iconBg' => 'bg-amber-100 text-amber-700'],
        ['label' => 'Results Posted', 'value' => $stats['results'] ?? 0, 'icon' => 'fa-trophy', 'iconBg' => 'bg-violet-100 text-violet-700'],
        ['label' => 'Open Actions', 'value' => $stats['registrations'] ?? 0, 'icon' => 'fa-list-check', 'iconBg' => 'bg-sky-100 text-sky-700'],
    ];

    $announcements = [
        ['title' => 'Race Kit Claim Schedule Released', 'body' => 'Participants can now view their assigned claim schedule and venue through the announcements module.', 'tone' => 'amber'],
        ['title' => 'Volunteer Orientation This Friday', 'body' => 'Marshals and support volunteers are required to attend the pre-event orientation session.', 'tone' => 'blue'],
    ];

    $recentEvents = [
        ['title' => 'Conquer Fun Run 2026', 'date' => 'May 18, 2026', 'location' => 'Bacoor City', 'status' => 'Open', 'statusClass' => 'bg-emerald-100 text-emerald-700', 'iconClass' => 'bg-sky-100 text-sky-700', 'icon' => 'fa-person-running'],
        ['title' => 'Community Wellness Run', 'date' => 'May 24, 2026', 'location' => 'Dasmarinas', 'status' => 'Upcoming', 'statusClass' => 'bg-amber-100 text-amber-700', 'iconClass' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-heart-pulse'],
        ['title' => 'Night Run Challenge', 'date' => 'June 02, 2026', 'location' => 'Imus City', 'status' => 'Finalized', 'statusClass' => 'bg-blue-100 text-blue-700', 'iconClass' => 'bg-violet-100 text-violet-700', 'icon' => 'fa-moon'],
    ];
@endphp

<div class="space-y-8">
    <section class="overflow-hidden rounded-[2rem] bg-slate-950 text-white shadow-2xl shadow-slate-300/40">
        <div class="grid gap-8 px-6 py-8 lg:grid-cols-[1.3fr_0.95fr] lg:px-8">
            <div class="relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[linear-gradient(135deg,_rgba(14,165,233,0.22),_rgba(15,23,42,0.96)_42%,_rgba(2,6,23,1)_100%)] p-8">
                <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-cyan-400/20 blur-3xl"></div>
                <div class="absolute bottom-0 right-6 h-40 w-40 rounded-full bg-emerald-400/10 blur-3xl"></div>

                <p class="relative text-xs font-semibold uppercase tracking-[0.36em] text-cyan-300">Dashboard Overview</p>
                <h1 class="relative mt-4 max-w-2xl text-3xl font-bold leading-tight text-white md:text-4xl">
                    Professional admin control for events, users, content, and platform operations.
                </h1>
                <p class="relative mt-4 max-w-2xl text-sm leading-7 text-slate-300">
                    This workspace puts the highest-value signals first, so your team can move quickly on approvals, race scheduling, moderation, and reporting.
                </p>

                <div class="relative mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Active Today</p>
                        <p class="mt-3 text-3xl font-bold text-white">{{ $stats['active_users_today'] ?? 0 }}</p>
                        <p class="mt-2 text-sm text-slate-300">Users currently active</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Upcoming</p>
                        <p class="mt-3 text-3xl font-bold text-white">{{ $stats['upcoming_events'] ?? 0 }}</p>
                        <p class="mt-2 text-sm text-slate-300">Events on schedule</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.28em] text-slate-400">Pending</p>
                        <p class="mt-3 text-3xl font-bold text-white">{{ $stats['pending_registrations'] ?? 0 }}</p>
                        <p class="mt-2 text-sm text-slate-300">Registrations awaiting action</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 text-slate-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Admin Profile</p>
                        <h2 class="mt-2 text-xl font-bold text-slate-950">Control center access</h2>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Online</span>
                </div>

                <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-700 text-lg font-bold text-white">
                            {{ strtoupper(substr($displayName, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-bold text-slate-950">{{ $displayName }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $displayEmail }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    @foreach ($controlSummary as $item)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-slate-500">{{ $item['label'] }}</p>
                                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ $item['value'] }}</p>
                                </div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $item['iconBg'] }}">
                                    <i class="fas {{ $item['icon'] }}"></i>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500">Session note</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Use the quick actions for urgent tasks, then move into the control modules for deeper admin work.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($summaryCards as $card)
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/50">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-4 text-4xl font-bold tracking-tight text-slate-950">{{ $card['value'] }}</p>
                        <p class="mt-3 text-sm text-slate-500">{{ $card['meta'] }}</p>
                    </div>
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl {{ $card['iconBg'] }}">
                        <i class="fas {{ $card['icon'] }} text-lg"></i>
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.45fr_0.95fr]">
        <div class="space-y-6">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white shadow-lg shadow-slate-200/50">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Events</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-950">Recent events</h3>
                    </div>
                    <a href="/admin/events" class="text-sm font-semibold text-sky-600 transition hover:text-sky-700">View all</a>
                </div>

                <div class="p-6">
                    <div class="space-y-4">
                        @foreach ($recentEvents as $event)
                            <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-4">
                                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $event['iconClass'] }}">
                                        <i class="fas {{ $event['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <p class="text-base font-semibold text-slate-950">{{ $event['title'] }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $event['date'] }} | {{ $event['location'] }}</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $event['statusClass'] }}">
                                    {{ $event['status'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white shadow-lg shadow-slate-200/50">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Announcements</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-950">Latest announcements</h3>
                    </div>
                    <a href="/admin/announcements" class="text-sm font-semibold text-sky-600 transition hover:text-sky-700">Manage</a>
                </div>

                <div class="p-6">
                    <div class="space-y-4">
                        @foreach ($announcements as $announcement)
                            <div class="rounded-2xl border {{ $announcement['tone'] === 'amber' ? 'border-amber-200 bg-amber-50' : 'border-sky-200 bg-sky-50' }} p-5">
                                <div class="flex items-start gap-4">
                                    <span class="mt-1 flex h-10 w-10 items-center justify-center rounded-2xl {{ $announcement['tone'] === 'amber' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700' }}">
                                        <i class="fas {{ $announcement['tone'] === 'amber' ? 'fa-bullhorn' : 'fa-users' }}"></i>
                                    </span>
                                    <div>
                                        <p class="text-base font-semibold text-slate-950">{{ $announcement['title'] }}</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $announcement['body'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Actions</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-950">Quick actions</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Ready</span>
                </div>

                <div class="mt-6 space-y-3">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="flex items-center justify-between rounded-2xl bg-gradient-to-r {{ $action['style'] }} px-4 py-4 text-white shadow-lg shadow-slate-300/30 transition hover:-translate-y-0.5">
                            <span class="flex items-center gap-3 text-sm font-semibold">
                                <i class="fas {{ $action['icon'] }}"></i>
                                {{ $action['label'] }}
                            </span>
                            <i class="fas fa-arrow-right text-xs opacity-80"></i>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/50">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Control Modules</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">Admin management</h3>
                </div>

                <div class="mt-6 space-y-3">
                    @foreach ($adminTools as $tool)
                        <a href="{{ $tool['href'] }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-sky-200 hover:bg-white">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white">
                                <i class="fas {{ $tool['icon'] }}"></i>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">{{ $tool['label'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $tool['desc'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/50">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Scheduling</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">Operations focus</h3>
                </div>

                <div class="mt-6 space-y-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">Review pending registrations</p>
                        <p class="mt-1 text-sm text-slate-500">Prioritize new signups waiting for confirmation and assignment.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">Check latest announcements</p>
                        <p class="mt-1 text-sm text-slate-500">Keep race-day notices and volunteer updates visible to participants.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">Publish event results</p>
                        <p class="mt-1 text-sm text-slate-500">Close completed events quickly by posting standings and performance data.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
