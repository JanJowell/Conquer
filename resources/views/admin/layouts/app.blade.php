<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Racetech Admin - @yield('title', 'Dashboard')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-bg: #eaf2f9;
            --admin-card: rgba(255, 255, 255, 0.35);
            --admin-border: rgba(255, 255, 255, 0.60);
            --admin-text: #0f172a;
            --admin-muted: #64748b;
            --admin-accent: #0f172a;
            --admin-active: rgba(255, 255, 255, 0.55);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--admin-bg);
            color: var(--admin-text);
        }

        body.admin-mobile-sidebar-open {
            overflow: hidden;
        }

        #admin-sidebar {
            transform: translateX(-100%);
        }

        #admin-sidebar[data-open="true"] {
            transform: translateX(0);
        }

        #admin-sidebar-backdrop {
            opacity: 0;
            pointer-events: none;
        }

        #admin-sidebar-backdrop[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        @media (min-width: 1024px) {
            body.admin-mobile-sidebar-open {
                overflow: auto;
            }

            #admin-sidebar {
                position: sticky;
                transform: none;
            }

            #admin-sidebar-backdrop {
                display: none;
            }
        }

        .admin-shell-main {
            background:
                radial-gradient(circle at 6% -8%, rgba(125, 211, 252, 0.36), transparent 32rem),
                radial-gradient(circle at 100% 18%, rgba(103, 232, 249, 0.25), transparent 34rem),
                radial-gradient(circle at 38% 100%, rgba(165, 180, 252, 0.22), transparent 30rem),
                var(--admin-bg);
        }

        .admin-shell-main > * {
            position: relative;
            z-index: 1;
        }

        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-white"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-white"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-[#fafbfc]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-[#fafbfc]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-[#f8f9fb]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-[#f8f9fb]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-[#fbfcfd]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-[#fbfcfd]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[var(--admin-border)]"][class*="bg-white"] {
            border-color: rgba(255, 255, 255, 0.60) !important;
            background: rgba(255, 255, 255, 0.35) !important;
            box-shadow: 0 18px 55px rgba(15, 23, 42, 0.10) !important;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        .admin-legacy-glass :is(section, article, div, form)[class*="rounded-3xl"],
        .admin-legacy-glass :is(section, article, div, form)[class*="rounded-2xl"] {
            border-radius: 1.6rem !important;
        }

        .admin-legacy-glass :is(input, select, textarea)[class*="border-[#d9dee7]"],
        .admin-legacy-glass :is(input, select, textarea)[class*="border-[var(--admin-border)]"] {
            border-color: rgba(255, 255, 255, 0.60) !important;
            background: rgba(255, 255, 255, 0.50) !important;
            color: #0f172a !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .admin-legacy-glass :is(input, select, textarea):focus {
            border-color: rgba(125, 211, 252, 0.80) !important;
            box-shadow: 0 0 0 4px rgba(224, 242, 254, 0.85) !important;
        }

        .admin-legacy-glass :is(a, button)[class*="border-[#d9dee7]"],
        .admin-legacy-glass :is(a, button)[class*="border-[var(--admin-border)]"] {
            border-color: rgba(255, 255, 255, 0.60) !important;
            background: rgba(255, 255, 255, 0.45) !important;
            color: #334155 !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .admin-legacy-glass :is(a, button)[class*="border-[#d9dee7]"]:hover,
        .admin-legacy-glass :is(a, button)[class*="border-[var(--admin-border)]"]:hover {
            background: rgba(255, 255, 255, 0.70) !important;
            transform: translateY(-1px);
        }

        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > :is(a, button)[class*="border-[#d9dee7]"],
        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > form > :is(a, button)[class*="border-[#d9dee7]"],
        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > :is(a, button)[class*="border-[var(--admin-border)]"],
        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > form > :is(a, button)[class*="border-[var(--admin-border)]"],
        .admin-legacy-glass a[class*="text-blue-600"],
        .admin-legacy-glass a[class*="text-indigo-600"] {
            display: inline-flex !important;
            min-height: 2.5rem;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.60) !important;
            border-radius: 0.75rem !important;
            background: rgba(255, 255, 255, 0.45) !important;
            padding: 0 1rem !important;
            color: #334155 !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            line-height: 1rem !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            transition: transform 150ms ease, background-color 150ms ease, color 150ms ease;
        }

        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > :is(a, button)[class*="border-[#d9dee7]"]:hover,
        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > form > :is(a, button)[class*="border-[#d9dee7]"]:hover,
        .admin-legacy-glass a[class*="text-blue-600"]:hover,
        .admin-legacy-glass a[class*="text-indigo-600"]:hover {
            background: rgba(255, 255, 255, 0.70) !important;
            color: #0f172a !important;
            transform: translateY(-1px);
        }

        .admin-legacy-glass :is(a, button)[class*="bg-[#151b26]"],
        .admin-legacy-glass :is(a, button)[class*="bg-[#111827]"] {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem !important;
            font-weight: 700 !important;
            transition: transform 150ms ease, background-color 150ms ease;
        }

        .admin-legacy-glass :is(a, button)[class*="bg-[#151b26]"]:hover,
        .admin-legacy-glass :is(a, button)[class*="bg-[#111827]"]:hover {
            background: rgba(30, 41, 59, 0.95) !important;
            transform: translateY(-1px);
        }

        .admin-legacy-glass > .mb-6.flex,
        .admin-legacy-glass > .space-y-6 > .flex:first-child,
        .admin-legacy-glass > .mx-auto > .flex:first-child,
        .admin-legacy-glass > .mx-auto > .mb-6.flex,
        .admin-legacy-glass > div > .mb-6.flex:first-child {
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.60);
            border-radius: 2rem;
            background: rgba(255, 255, 255, 0.35);
            padding: 1.25rem;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.10);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        .admin-legacy-glass > .mb-6.flex::before,
        .admin-legacy-glass > .space-y-6 > .flex:first-child::before,
        .admin-legacy-glass > .mx-auto > .flex:first-child::before,
        .admin-legacy-glass > .mx-auto > .mb-6.flex::before,
        .admin-legacy-glass > div > .mb-6.flex:first-child::before {
            content: "";
            position: absolute;
            pointer-events: none;
            inset: auto auto -4rem -4rem;
            width: 14rem;
            height: 14rem;
            border-radius: 999px;
            background: rgba(125, 211, 252, 0.22);
            filter: blur(48px);
        }

        .admin-legacy-glass > .mb-6.flex,
        .admin-legacy-glass > .space-y-6 > .flex:first-child,
        .admin-legacy-glass > .mx-auto > .flex:first-child,
        .admin-legacy-glass > .mx-auto > .mb-6.flex,
        .admin-legacy-glass > div > .mb-6.flex:first-child {
            position: relative;
        }

        .admin-legacy-glass > .mb-6.flex > *,
        .admin-legacy-glass > .space-y-6 > .flex:first-child > *,
        .admin-legacy-glass > .mx-auto > .flex:first-child > *,
        .admin-legacy-glass > .mx-auto > .mb-6.flex > *,
        .admin-legacy-glass > div > .mb-6.flex:first-child > * {
            position: relative;
            z-index: 1;
        }

        .admin-legacy-glass > .mb-6.flex p:first-child,
        .admin-legacy-glass > .space-y-6 > .flex:first-child p:first-child,
        .admin-legacy-glass > .mx-auto > .flex:first-child p:first-child,
        .admin-legacy-glass > .mx-auto > .mb-6.flex p:first-child,
        .admin-legacy-glass > div > .mb-6.flex:first-child p:first-child {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.60);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.45);
            padding: 0.5rem 1rem;
            color: #0369a1 !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .admin-legacy-glass form > div[class*="border-t"][class*="bg-[#fbfcfd]"],
        .admin-legacy-glass form > div[class*="border-t"][class*="bg-[#fafbfc]"],
        .admin-legacy-glass form > div[class*="border-t"][class*="bg-[#f8f9fb]"] {
            border-color: rgba(255, 255, 255, 0.60) !important;
            background: rgba(255, 255, 255, 0.35) !important;
            box-shadow: 0 -1px 0 rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .admin-legacy-glass dialog {
            border: 1px solid rgba(255, 255, 255, 0.60) !important;
            border-radius: 2rem !important;
            background: rgba(234, 242, 249, 0.92) !important;
            padding: 1rem !important;
            color: #0f172a;
            box-shadow: 0 28px 90px rgba(15, 23, 42, 0.28) !important;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        .admin-legacy-glass dialog::backdrop {
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .admin-legacy-glass dialog > div {
            border-color: rgba(255, 255, 255, 0.60) !important;
            background: rgba(255, 255, 255, 0.35) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.70);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .admin-legacy-glass table[class*="divide-y"] {
            border-collapse: separate !important;
            border-spacing: 0 0.75rem !important;
        }

        .admin-legacy-glass :is(thead, tbody, tr)[class*="divide-y"],
        .admin-legacy-glass [class*="divide-y"] > :not([hidden]) ~ :not([hidden]) {
            border-color: transparent !important;
        }

        .admin-legacy-glass thead[class*="bg-[#fafbfc]"],
        .admin-legacy-glass [class*="bg-[#fafbfc]"],
        .admin-legacy-glass [class*="bg-[#f8f9fb]"],
        .admin-legacy-glass [class*="bg-[#fbfcfd]"],
        .admin-legacy-glass tbody[class*="bg-white"] {
            background: transparent !important;
        }

        .admin-legacy-glass tbody tr > td {
            border-top: 1px solid rgba(255, 255, 255, 0.60);
            border-bottom: 1px solid rgba(255, 255, 255, 0.60);
            background: rgba(255, 255, 255, 0.40);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .admin-legacy-glass tbody tr > td:first-child {
            border-left: 1px solid rgba(255, 255, 255, 0.60);
            border-top-left-radius: 1rem;
            border-bottom-left-radius: 1rem;
        }

        .admin-legacy-glass tbody tr > td:last-child {
            border-right: 1px solid rgba(255, 255, 255, 0.60);
            border-top-right-radius: 1rem;
            border-bottom-right-radius: 1rem;
        }

        .admin-legacy-glass [class*="text-[#151b26]"],
        .admin-legacy-glass [class*="text-[#111827]"],
        .admin-legacy-glass [class*="text-[#202733]"] {
            color: #0f172a !important;
        }

        .admin-legacy-glass [class*="text-[#6d7685]"],
        .admin-legacy-glass [class*="text-[#7a8495]"],
        .admin-legacy-glass [class*="text-[#7a8392]"],
        .admin-legacy-glass [class*="text-[#5e6878]"] {
            color: #64748b !important;
        }

        .admin-legacy-glass [class*="bg-[#151b26]"],
        .admin-legacy-glass [class*="bg-[#111827]"] {
            background: rgba(15, 23, 42, 0.92) !important;
            color: #ffffff !important;
            box-shadow: 0 14px 30px rgba(148, 163, 184, 0.35) !important;
        }

        .admin-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .admin-scrollbar::-webkit-scrollbar-thumb {
            background: #d2d8e1;
            border-radius: 999px;
        }
    </style>
</head>
<body class="min-h-screen">
    @php
        $user = auth()->user();
        $navigationByRole = [
            \App\Models\User::ROLE_SUPER_ADMIN => [
                [
                    'heading' => 'MAIN',
                    'items' => [
                        ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Dashboard'],
                        ['route' => route('admin.analytics'), 'match' => 'admin.analytics', 'icon' => 'fa-chart-column', 'label' => 'Analytics'],
                        ['route' => route('admin.notifications.index'), 'match' => 'admin.notifications.*', 'icon' => 'fa-bell', 'label' => 'Notifications'],
                        ['route' => route('admin.security.dashboard'), 'match' => 'admin.security.*', 'icon' => 'fa-gear', 'label' => 'Settings'],
                    ],
                ],
                [
                    'heading' => 'PEOPLE',
                    'items' => [
                        ['route' => route('admin.users.index'), 'match' => 'admin.users.*', 'icon' => 'fa-users', 'label' => 'Users'],
                        ['route' => route('admin.participants.index'), 'match' => 'admin.participants.*', 'icon' => 'fa-id-card', 'label' => 'Participants'],
                        ['route' => route('admin.content.pending-review'), 'match' => 'admin.content.pending-review', 'icon' => 'fa-list-check', 'label' => 'Pending Review'],
                        ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-message', 'label' => 'Feedback Insights'],
                    ],
                ],
                [
                    'heading' => 'EVENT OPERATIONS',
                    'items' => [
                        ['route' => route('admin.events.index'), 'match' => 'admin.events.*', 'icon' => 'fa-calendar-days', 'label' => 'Events'],
                        ['route' => route('admin.check-in.index'), 'match' => 'admin.check-in.*', 'icon' => 'fa-clipboard-check', 'label' => 'Check-in'],
                        ['route' => route('admin.payments.index'), 'match' => 'admin.payments.*', 'icon' => 'fa-credit-card', 'label' => 'Payments'],
                        ['route' => route('admin.results.index'), 'match' => 'admin.results.*', 'icon' => 'fa-trophy', 'label' => 'Results'],
                        ['route' => route('admin.e-badges.index'), 'match' => 'admin.e-badges.*', 'icon' => 'fa-award', 'label' => 'E-Badges'],
                    ],
                ],
                [
                    'heading' => 'CONTENT & COMMUNITY',
                    'items' => [
                        ['route' => route('admin.announcements.index'), 'match' => 'admin.announcements.*', 'icon' => 'fa-bullhorn', 'label' => 'Announcements'],
                        ['route' => route('admin.content.community-posts'), 'match' => 'admin.content.community-posts*', 'icon' => 'fa-comments', 'label' => 'Community'],
                        ['route' => route('admin.content.training-modules'), 'match' => 'admin.content.training-modules*', 'icon' => 'fa-image', 'label' => 'Training'],
                    ],
                ],
            ],
            \App\Models\User::ROLE_EXECUTIVE => [
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Executive Dashboard'],
                ['route' => route('admin.analytics'), 'match' => 'admin.analytics', 'icon' => 'fa-chart-column', 'label' => 'Analytics'],
                ['route' => route('admin.reports'), 'match' => 'admin.reports', 'icon' => 'fa-chart-line', 'label' => 'Reports'],
                ['route' => route('admin.events.index'), 'match' => 'admin.events.index', 'icon' => 'fa-calendar-days', 'label' => 'Events Overview'],
                ['route' => route('admin.users.index'), 'match' => 'admin.users.*', 'icon' => 'fa-user', 'label' => 'Users Overview'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-message', 'label' => 'Feedback Insights'],
            ],
            \App\Models\User::ROLE_CONTENT_MODERATOR => [
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Dashboard'],
                ['route' => route('admin.announcements.index'), 'match' => 'admin.announcements.*', 'icon' => 'fa-bullhorn', 'label' => 'Announcements'],
                ['route' => route('admin.notifications.index'), 'match' => 'admin.notifications.*', 'icon' => 'fa-bell', 'label' => 'Notifications'],
                ['route' => route('admin.content.pending-review'), 'match' => 'admin.content.pending-review', 'icon' => 'fa-list-check', 'label' => 'Pending Review'],
                ['route' => route('admin.content.training-modules'), 'match' => 'admin.content.training-modules*', 'icon' => 'fa-file-pen', 'label' => 'Training Content'],
                ['route' => route('admin.content.community-posts'), 'match' => 'admin.content.community-posts*', 'icon' => 'fa-comments', 'label' => 'Community Queue'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-flag', 'label' => 'Feedback Insights'],
            ],
            \App\Models\User::ROLE_EVENT_MANAGER => [
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Dashboard'],
                ['route' => route('admin.events.index'), 'match' => 'admin.events.*', 'icon' => 'fa-calendar-days', 'label' => 'My Events'],
                ['route' => route('admin.participants.index'), 'match' => 'admin.participants.*', 'icon' => 'fa-id-card', 'label' => 'Participants'],
                ['route' => route('admin.payments.index'), 'match' => 'admin.payments.*', 'icon' => 'fa-credit-card', 'label' => 'Payments'],
                ['route' => route('admin.check-in.index'), 'match' => 'admin.check-in.*', 'icon' => 'fa-clipboard-check', 'label' => 'Check-in'],
                ['route' => route('admin.results.index'), 'match' => 'admin.results.*', 'icon' => 'fa-trophy', 'label' => 'Results'],
                ['route' => route('admin.e-badges.index'), 'match' => 'admin.e-badges.*', 'icon' => 'fa-award', 'label' => 'E-Badges'],
                ['route' => route('admin.announcements.index'), 'match' => 'admin.announcements.*', 'icon' => 'fa-bullhorn', 'label' => 'Announcements'],
                ['route' => route('admin.reports'), 'match' => 'admin.reports', 'icon' => 'fa-chart-line', 'label' => 'Reports'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-message', 'label' => 'Feedback'],
            ],
        ];

        $navigation = $navigationByRole[$user->normalizedRole()] ?? $navigationByRole[\App\Models\User::ROLE_SUPER_ADMIN];
        $currentRouteName = request()->route()?->getName();
        $successCreateAction = null;

        if ($currentRouteName === 'admin.announcements.index') {
            $successCreateAction = ['label' => 'Create another announcement', 'target' => 'create-announcement'];
        } elseif ($currentRouteName === 'admin.notifications.index') {
            $successCreateAction = ['label' => 'Create another notification', 'target' => 'create-notification'];
        } elseif ($currentRouteName === 'admin.users.index' && \Illuminate\Support\Facades\Route::has('admin.users.create')) {
            $successCreateAction = ['label' => 'Create another user', 'url' => route('admin.users.create')];
        } elseif ($currentRouteName === 'admin.events.index' && \Illuminate\Support\Facades\Route::has('admin.events.create')) {
            $successCreateAction = ['label' => 'Create another event', 'url' => route('admin.events.create')];
        } elseif ($currentRouteName === 'admin.categories.index' && \Illuminate\Support\Facades\Route::has('admin.categories.create')) {
            $successCreateAction = ['label' => 'Create another category', 'url' => route('admin.categories.create')];
        } elseif ($currentRouteName === 'admin.content.training-modules' && \Illuminate\Support\Facades\Route::has('admin.content.training-modules.create')) {
            $successCreateAction = ['label' => 'Create another training module', 'url' => route('admin.content.training-modules.create')];
        }
    @endphp

    <button
        id="admin-sidebar-backdrop"
        type="button"
        data-open="false"
        class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm transition-opacity duration-200 lg:hidden"
        aria-label="Close navigation menu"
        tabindex="-1"
    ></button>

    <div class="min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside
            id="admin-sidebar"
            data-open="false"
            class="fixed inset-y-0 left-0 z-50 flex h-dvh w-[min(20rem,88vw)] flex-col border-r border-white/60 bg-[#eaf2f9]/95 shadow-[0_18px_55px_rgba(15,23,42,0.18)] backdrop-blur-2xl transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-auto lg:bg-white/35 lg:shadow-[0_18px_55px_rgba(15,23,42,0.08)]"
            aria-label="Admin navigation"
        >
            <div class="border-b border-white/50 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-sm border border-[#cfd5de] bg-[#f4f5f7] text-[#98a1ae]">
                            <i class="fas fa-flag-checkered text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xl font-bold tracking-tight text-[var(--admin-text)]">Racetech</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        data-admin-sidebar-close
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/50 text-[#606978] shadow-sm lg:hidden"
                        aria-label="Close navigation menu"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <nav class="admin-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-6">
                @foreach ($navigation as $section)
                    @if (isset($section['items']))
                        <div class="pb-4">
                            <p class="px-4 pb-2 pt-3 text-[11px] font-bold uppercase tracking-[0.22em] text-[#7a8495]">{{ $section['heading'] }}</p>
                            <div class="space-y-1">
                                @foreach ($section['items'] as $item)
                                    @php
                                        $active = request()->routeIs($item['match']);
                                    @endphp
                                    <a
                                        href="{{ $item['route'] }}"
                                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-sm backdrop-blur-xl transition {{ $active ? 'border border-slate-950/90 bg-slate-950/90 text-white shadow-lg shadow-slate-300/40' : 'text-[#202733] hover:bg-white/45' }}"
                                    >
                                        @if ($active)
                                            <span class="absolute left-1 top-1/2 h-7 w-1 -translate-y-1/2 rounded-full bg-sky-300"></span>
                                        @endif
                                        <span class="flex h-5 w-5 items-center justify-center text-[15px] {{ $active ? 'text-white' : 'text-[#5e6878]' }}">
                                            <i class="fas {{ $item['icon'] }}"></i>
                                        </span>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @php
                            $item = $section;
                            $active = request()->routeIs($item['match']);
                        @endphp
                        <a
                            href="{{ $item['route'] }}"
                            class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-sm backdrop-blur-xl transition {{ $active ? 'border border-slate-950/90 bg-slate-950/90 text-white shadow-lg shadow-slate-300/40' : 'text-[#202733] hover:bg-white/45' }}"
                        >
                            @if ($active)
                                <span class="absolute left-1 top-1/2 h-7 w-1 -translate-y-1/2 rounded-full bg-sky-300"></span>
                            @endif
                            <span class="flex h-5 w-5 items-center justify-center text-[15px] {{ $active ? 'text-white' : 'text-[#5e6878]' }}">
                                <i class="fas {{ $item['icon'] }}"></i>
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="p-4">
                <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                            @if ($user->avatar_path)
                                <img
                                    src="{{ asset('storage/'.$user->avatar_path) }}"
                                    alt="{{ $user->name }}"
                                    class="h-12 w-12 rounded-full border border-[#cfd5de] object-cover"
                                >
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-full border border-[#cfd5de] bg-[#f5f6f8] text-sm font-semibold text-[#606978]">
                                    {{ $user->initials() ?: 'AD' }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-[var(--admin-text)]">{{ $user->name }}</p>
                                <p class="truncate text-sm text-[var(--admin-muted)]">{{ $user->roleLabel() }}</p>
                            </div>
                            <i class="fas fa-angle-down text-xs text-[#7d8694] transition group-open:rotate-180"></i>
                        </summary>

                        <div class="mt-4 space-y-3 border-t border-[#eef1f4] pt-4">
                            <a
                                href="{{ route('profile.edit') }}"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[#d9dee7] px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]"
                            >
                                <i class="fas fa-user-gear text-sm text-[#5e6878]"></i>
                                Profile Settings
                            </a>

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-[#d9dee7] px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-col">
            <header class="border-b border-white/60 bg-white/35 backdrop-blur-2xl">
                <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <button
                            type="button"
                            data-admin-sidebar-open
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#606978] shadow-sm backdrop-blur-xl lg:hidden"
                            aria-label="Open navigation menu"
                            aria-controls="admin-sidebar"
                            aria-expanded="false"
                        >
                            <i class="fas fa-bars"></i>
                        </button>

                        <form method="GET" action="{{ route('admin.search') }}" class="relative w-full max-w-md">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[#8c95a3]">
                                <i class="fas fa-magnifying-glass text-sm"></i>
                            </span>
                            <input
                                type="text"
                                name="q"
                                value="{{ request('q') }}"
                                placeholder="Search anything..."
                                class="h-11 w-full rounded-xl border border-white/60 bg-white/50 pl-11 pr-4 text-sm text-[var(--admin-text)] shadow-sm outline-none backdrop-blur-xl transition placeholder:text-[#9aa3af] focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                            >
                        </form>
                    </div>

                </div>

                @if (session('success') || session('error') || $errors->any())
                    <div class="px-4 pb-4 sm:px-6 lg:px-8">
                        @if (session('success'))
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {{ session('error') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif
                    </div>
                @endif
            </header>

            <main class="admin-shell-main min-w-0 flex-1 overflow-x-hidden px-4 py-6 sm:px-6 lg:px-8">
                <div class="admin-legacy-glass">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-sidebar-backdrop');
            const openButton = document.querySelector('[data-admin-sidebar-open]');
            const closeButton = document.querySelector('[data-admin-sidebar-close]');
            const desktopQuery = window.matchMedia('(min-width: 1024px)');
            let lastFocusedElement = null;

            if (!sidebar || !backdrop || !openButton || !closeButton) {
                return;
            }

            const isOpen = () => sidebar.dataset.open === 'true' && !desktopQuery.matches;

            const syncSidebar = (open, restoreFocus = false) => {
                const mobileOpen = Boolean(open) && !desktopQuery.matches;

                sidebar.dataset.open = mobileOpen ? 'true' : 'false';
                backdrop.dataset.open = mobileOpen ? 'true' : 'false';
                openButton.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
                sidebar.setAttribute('aria-hidden', desktopQuery.matches || mobileOpen ? 'false' : 'true');
                sidebar.inert = !desktopQuery.matches && !mobileOpen;
                document.body.classList.toggle('admin-mobile-sidebar-open', mobileOpen);

                if (mobileOpen) {
                    window.requestAnimationFrame(() => closeButton.focus());
                } else if (restoreFocus && lastFocusedElement) {
                    lastFocusedElement.focus();
                }
            };

            const openSidebar = () => {
                lastFocusedElement = document.activeElement;
                syncSidebar(true);
            };

            const closeSidebar = (restoreFocus = true) => syncSidebar(false, restoreFocus);

            openButton.addEventListener('click', openSidebar);
            closeButton.addEventListener('click', () => closeSidebar());
            backdrop.addEventListener('click', () => closeSidebar());

            sidebar.querySelectorAll('nav a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (!desktopQuery.matches) {
                        closeSidebar(false);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (!isOpen()) {
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeSidebar();
                    return;
                }

                if (event.key !== 'Tab') {
                    return;
                }

                const focusableElements = Array.from(sidebar.querySelectorAll(
                    'a[href], button:not([disabled]), summary, input:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )).filter((element) => !element.hidden && element.offsetParent !== null);

                if (focusableElements.length === 0) {
                    return;
                }

                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];

                if (event.shiftKey && document.activeElement === firstElement) {
                    event.preventDefault();
                    lastElement.focus();
                } else if (!event.shiftKey && document.activeElement === lastElement) {
                    event.preventDefault();
                    firstElement.focus();
                }
            });

            desktopQuery.addEventListener('change', () => syncSidebar(false));
            syncSidebar(false);
        });
    </script>

    @if (session('success'))
        <div id="admin-success-dialog" class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto px-4 py-8 sm:px-6" role="dialog" aria-modal="true" aria-labelledby="admin-success-dialog-title">
            <button type="button" data-close-success-dialog class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative z-10 mt-10 w-full max-w-lg overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/90 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-emerald-200/70 bg-emerald-100/80 text-emerald-700 shadow-sm backdrop-blur-xl">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Success</p>
                                <h2 id="admin-success-dialog-title" class="mt-1 text-2xl font-semibold tracking-tight text-[#151b26]">Entry Completed</h2>
                            </div>
                        </div>
                        <button type="button" data-close-success-dialog class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#151b26]" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <p class="text-sm leading-7 text-[#202733]">{{ session('success') }}</p>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-white/50 bg-white/40 px-6 py-4 backdrop-blur-xl">
                    <button type="button" data-close-success-dialog class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-5 text-sm font-semibold text-[#202733] shadow-sm backdrop-blur-xl transition hover:bg-white/70">
                        Done
                    </button>
                    @if ($successCreateAction)
                        @if (isset($successCreateAction['target']))
                            <button type="button" data-success-create-target="{{ $successCreateAction['target'] }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#151b26] px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                                {{ $successCreateAction['label'] }}
                            </button>
                        @else
                            <a href="{{ $successCreateAction['url'] }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#151b26] px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                                {{ $successCreateAction['label'] }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const successDialog = document.getElementById('admin-success-dialog');

                if (!successDialog) {
                    return;
                }

                document.body.classList.add('overflow-hidden');

                const closeSuccessDialog = () => {
                    successDialog.classList.add('hidden');
                    successDialog.classList.remove('flex');

                    if (!document.querySelector('[role="dialog"].flex')) {
                        document.body.classList.remove('overflow-hidden');
                    }
                };

                document.querySelectorAll('[data-close-success-dialog]').forEach((button) => {
                    button.addEventListener('click', closeSuccessDialog);
                });

                document.querySelectorAll('[data-success-create-target]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const targetModal = document.getElementById(button.dataset.successCreateTarget);
                        closeSuccessDialog();

                        if (!targetModal) {
                            return;
                        }

                        targetModal.classList.remove('hidden');
                        targetModal.classList.add('flex');
                        document.body.classList.add('overflow-hidden');
                    });
                });
            });
        </script>
    @endif
</body>
</html>
