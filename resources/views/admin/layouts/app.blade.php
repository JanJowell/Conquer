<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Racetech Admin - @yield('title', 'Dashboard')</title>
    @livewireStyles
    <script>
        window.adminOnPageReady = (callback) => document.addEventListener('livewire:navigated', callback, { once: true });
    </script>
    <script>
        /*
         * Keeps the sidebar nav scroll position between wire:navigate page
         * changes. Guarded so it only registers once per full page load.
         */
        if (!window.__adminSidebarScrollInit) {
            window.__adminSidebarScrollInit = true;
            let sidebarScrollTop = 0;

            document.addEventListener('livewire:navigating', () => {
                const nav = document.querySelector('#admin-sidebar nav');
                if (nav) sidebarScrollTop = nav.scrollTop;
            });

            document.addEventListener('livewire:navigated', () => {
                const nav = document.querySelector('#admin-sidebar nav');
                if (nav) nav.scrollTop = sidebarScrollTop;
            });
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-bg: #f7f8fc;
            --admin-card: #ffffff;
            --admin-text: #334155;
            --admin-muted: #64748b;
            --admin-accent: #4f46e5;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--admin-bg);
            color: var(--admin-text);
            overflow-x: hidden;
        }

        /* Ensure small labels and actions remain readable on white cards. */
        .admin-shell-main .text-slate-400,
        header .text-slate-400,
        #admin-sidebar .text-slate-400 { color: #64748b !important; }
        .admin-shell-main .text-indigo-400,
        #admin-sidebar .text-indigo-400 { color: #4f46e5 !important; }
        .admin-shell-main .text-rose-400,
        header .text-rose-400 { color: #be123c !important; }
        .admin-shell-main .bg-indigo-400,
        #admin-sidebar .bg-indigo-400 { background-color: #4f46e5 !important; }

        /* Tables may scroll inside their own card, but must never widen the page. */
        .admin-shell-main .overflow-x-auto {
            max-width: 100%;
            overscroll-behavior-inline: contain;
            scrollbar-gutter: stable;
        }

        #admin-sidebar nav a {
            transition: background-color 200ms ease, color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
        }

        #admin-sidebar nav a:hover { transform: translateX(3px); }
        #admin-sidebar nav a:active { transform: translateX(3px) scale(0.98); }
        #admin-sidebar { transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1); }

        /* Disable all sidebar transitions while the active link is being re-synced after navigation. */
        #admin-sidebar.is-syncing,
        #admin-sidebar.is-syncing * {
            transition: none !important;
            animation: none !important;
        }

        @media (prefers-reduced-motion: reduce) {
            #admin-sidebar, #admin-sidebar nav a { transition: none; }
            #admin-sidebar nav a:hover, #admin-sidebar nav a:active { transform: none; }
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
            background: var(--admin-bg);
        }

        /*
         * Soft-UI reskin shim: retroactively restyles any legacy Blade
         * partial that still hardcodes the old bordered/hex-color classes,
         * so every page reads as plain white cards with a soft diffused
         * shadow instead of the old bordered/glass look.
         */
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-white"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-white"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-[#fafbfc]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-[#fafbfc]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-[#f8f9fb]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-[#f8f9fb]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#d9dee7]"][class*="bg-[#fbfcfd]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[#eef1f4]"][class*="bg-[#fbfcfd]"],
        .admin-legacy-glass :is(section, article, div, form)[class*="border-[var(--admin-border)]"][class*="bg-white"] {
            border: 0 !important;
            background: #ffffff !important;
            box-shadow: 0 8px 24px rgba(148, 163, 184, 0.12) !important;
        }

        .admin-legacy-glass :is(section, article, div, form)[class*="rounded-3xl"],
        .admin-legacy-glass :is(section, article, div, form)[class*="rounded-2xl"] {
            border-radius: 1.75rem !important;
        }

        .admin-legacy-glass :is(input, select, textarea)[class*="border-[#d9dee7]"],
        .admin-legacy-glass :is(input, select, textarea)[class*="border-[var(--admin-border)]"] {
            border: 0 !important;
            background: #f8fafc !important;
            color: #334155 !important;
            box-shadow: none !important;
        }

        .admin-legacy-glass :is(input, select, textarea):focus {
            border-color: transparent !important;
            box-shadow: 0 0 0 2px rgba(199, 210, 254, 0.9) !important;
        }

        .admin-legacy-glass :is(a, button)[class*="border-[#d9dee7]"],
        .admin-legacy-glass :is(a, button)[class*="border-[var(--admin-border)]"] {
            border: 0 !important;
            border-radius: 9999px !important;
            background: #f8fafc !important;
            color: #64748b !important;
            box-shadow: none !important;
        }

        .admin-legacy-glass :is(a, button)[class*="border-[#d9dee7]"]:hover,
        .admin-legacy-glass :is(a, button)[class*="border-[var(--admin-border)]"]:hover {
            background: #f1f5f9 !important;
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
            border: 0 !important;
            border-radius: 9999px !important;
            background: #f8fafc !important;
            padding: 0 1rem !important;
            color: #64748b !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            line-height: 1rem !important;
            box-shadow: none !important;
            transition: transform 150ms ease, background-color 150ms ease, color 150ms ease;
        }

        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > :is(a, button)[class*="border-[#d9dee7]"]:hover,
        .admin-legacy-glass :is(td, [class*="justify-end"], [class*="gap-2"]) > form > :is(a, button)[class*="border-[#d9dee7]"]:hover,
        .admin-legacy-glass a[class*="text-blue-600"]:hover,
        .admin-legacy-glass a[class*="text-indigo-600"]:hover {
            background: #f1f5f9 !important;
            color: #334155 !important;
            transform: translateY(-1px);
        }

        .admin-legacy-glass :is(a, button)[class*="bg-[#151b26]"],
        .admin-legacy-glass :is(a, button)[class*="bg-[#111827]"] {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 9999px !important;
            background: #4f46e5 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: none !important;
            transition: transform 150ms ease, background-color 150ms ease;
        }

        .admin-legacy-glass :is(a, button)[class*="bg-[#151b26]"]:hover,
        .admin-legacy-glass :is(a, button)[class*="bg-[#111827]"]:hover {
            background: #6366f1 !important;
            transform: translateY(-1px);
        }

        .admin-legacy-glass > .mb-6.flex p:first-child,
        .admin-legacy-glass > .space-y-6 > .flex:first-child p:first-child,
        .admin-legacy-glass > .mx-auto > .flex:first-child p:first-child,
        .admin-legacy-glass > .mx-auto > .mb-6.flex p:first-child,
        .admin-legacy-glass > div > .mb-6.flex:first-child p:first-child {
            color: #4f46e5 !important;
        }

        .admin-legacy-glass form > div[class*="border-t"][class*="bg-[#fbfcfd]"],
        .admin-legacy-glass form > div[class*="border-t"][class*="bg-[#fafbfc]"],
        .admin-legacy-glass form > div[class*="border-t"][class*="bg-[#f8f9fb]"] {
            border-color: #f8fafc !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .admin-legacy-glass dialog {
            border: 0 !important;
            border-radius: 1.75rem !important;
            background: #ffffff !important;
            padding: 1rem !important;
            color: #334155;
            box-shadow: 0 24px 70px rgba(148, 163, 184, 0.30) !important;
        }

        .admin-legacy-glass dialog::backdrop {
            background: rgba(148, 163, 184, 0.30);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .admin-legacy-glass dialog > div {
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .admin-legacy-glass table[class*="divide-y"] {
            border-collapse: collapse !important;
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

        .admin-legacy-glass tbody tr:hover {
            background: #f7f8fc;
        }

        .admin-legacy-glass [class*="text-[#151b26]"],
        .admin-legacy-glass [class*="text-[#111827]"],
        .admin-legacy-glass [class*="text-[#202733]"] {
            color: #334155 !important;
        }

        .admin-legacy-glass [class*="text-[#6d7685]"],
        .admin-legacy-glass [class*="text-[#7a8495]"],
        .admin-legacy-glass [class*="text-[#7a8392]"],
        .admin-legacy-glass [class*="text-[#5e6878]"] {
            color: #64748b !important;
        }

        /* Shared responsive safeguards for every admin page. */
        .admin-shell-main,
        .admin-legacy-glass,
        .admin-legacy-glass > * {
            min-width: 0;
            max-width: 100%;
        }

        .admin-legacy-glass :is(.flex, .grid) > * {
            min-width: 0;
        }

        .admin-legacy-glass :is(input, select, textarea) {
            max-width: 100%;
        }

        .admin-legacy-glass :is(img, video, canvas) {
            max-width: 100%;
            height: auto;
        }

        .admin-legacy-glass :is(h1, h2, h3, p, dd, td) {
            overflow-wrap: anywhere;
        }

        .admin-table-scroll {
            position: relative;
            max-width: 100%;
            overflow-x: hidden;
            overscroll-behavior-inline: contain;
            -webkit-overflow-scrolling: touch;
        }

        .admin-table-scroll table {
            width: 100%;
            min-width: 100%;
        }

        .admin-table-scroll th {
            white-space: normal;
        }

        .admin-table-action-column {
            position: static;
            min-width: 0;
            white-space: normal;
        }

        .admin-table-action-column :is(.flex, form) {
            flex-wrap: wrap !important;
        }

        .admin-table-action-column :is(a, button) {
            max-width: 100%;
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: normal;
        }

        .admin-responsive-table :is(button, [class~="inline-flex"]) {
            overflow-wrap: normal;
            word-break: normal;
        }

        @media (min-width: 768px) {
            .admin-responsive-table.admin-table-dense {
                table-layout: auto;
            }

            .admin-responsive-table.admin-table-dense :is(th, td) {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            .admin-responsive-table.admin-table-dense :is(input, select, textarea) {
                width: 100%;
                min-width: 0;
            }

            .admin-responsive-table.admin-table-dense td > * {
                max-width: 100%;
            }

            .admin-responsive-table.admin-table-dense td .flex {
                flex-wrap: wrap;
            }

            .admin-responsive-table .admin-registration-actions {
                width: 17rem;
            }

            .admin-responsive-table .admin-registration-actions form,
            .admin-responsive-table .admin-registration-actions .grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            .admin-responsive-table .admin-registration-actions :is(button, textarea) {
                width: 100%;
            }
        }

        @media (max-width: 767px) {
            .admin-shell-main {
                padding: 1rem 0.75rem !important;
            }

            /*
             * Wide data tables become labelled cards on small screens. This
             * keeps every field and action available without forcing the
             * entire table area to scroll horizontally.
             */
            .admin-table-scroll {
                overflow-x: visible;
                scrollbar-gutter: auto;
            }

            .admin-responsive-table {
                display: block;
                width: 100%;
                min-width: 0 !important;
            }

            .admin-responsive-table thead {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            .admin-responsive-table tbody {
                display: grid;
                width: 100%;
                gap: 0.875rem;
            }

            .admin-responsive-table tbody > tr {
                display: grid;
                width: 100%;
                min-width: 0;
                overflow: hidden;
                border: 0 !important;
                border-radius: 1.5rem;
                background: #ffffff;
                box-shadow: 0 8px 24px rgba(148, 163, 184, 0.12);
            }

            .admin-responsive-table tbody > tr > td:not([colspan]) {
                display: grid;
                grid-template-columns: minmax(7.5rem, 0.38fr) minmax(0, 0.62fr);
                align-items: start;
                gap: 0.75rem;
                min-width: 0;
                padding: 0.875rem 1rem !important;
                border: 0 !important;
                border-bottom: 1px solid #f8fafc !important;
                border-radius: 0 !important;
                background: transparent !important;
                text-align: left !important;
                white-space: normal;
                box-shadow: none !important;
            }

            .admin-responsive-table tbody > tr > td:not([colspan]):last-child {
                border-bottom: 0 !important;
            }

            .admin-responsive-table tbody > tr > td:not([colspan])::before {
                content: attr(data-admin-label);
                min-width: 0;
                font-size: 0.6875rem;
                font-weight: 700;
                line-height: 1.25rem;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                color: #64748b;
            }

            .admin-responsive-table tbody > tr > td[colspan] {
                display: block;
                width: 100%;
                padding: 2rem 1rem !important;
                border: 0 !important;
                background: transparent !important;
            }

            .admin-responsive-table .admin-table-action-column {
                position: static;
                right: auto;
                z-index: auto;
                min-width: 0;
            }

            .admin-responsive-table .admin-table-action-column :is(.flex, form) {
                flex-wrap: wrap !important;
                justify-content: flex-start !important;
            }

            .admin-responsive-table .admin-table-action-column :is(a, button),
            .admin-responsive-table td[data-admin-label="Action"] :is(a, button),
            .admin-responsive-table td[data-admin-label="Actions"] :is(a, button),
            .admin-responsive-table td[data-admin-label="Save"] :is(a, button),
            .admin-responsive-table td[data-admin-label="Manage"] :is(a, button) {
                width: 100%;
            }

            .admin-legacy-glass :is(button, input, select),
            .admin-legacy-glass a[class*="inline-flex"],
            .admin-legacy-glass a[class*="flex"] {
                min-height: 2.75rem;
            }

            .admin-legacy-glass form[class*="grid"] > :is(button, a) {
                width: 100%;
            }

            .admin-responsive-actions {
                display: grid !important;
                width: 100%;
                grid-template-columns: minmax(0, 1fr) !important;
                align-items: stretch !important;
                justify-content: stretch !important;
            }

            .admin-responsive-actions > :is(a, button, form),
            .admin-responsive-actions > form > :is(a, button) {
                width: 100%;
            }

            .admin-legacy-glass [role="dialog"] {
                align-items: flex-start !important;
                padding: 0.75rem !important;
            }

            .admin-legacy-glass [role="dialog"] > :is(div, form):not([data-close-user-modal]),
            .admin-legacy-glass dialog {
                max-height: calc(100dvh - 1.5rem) !important;
                max-width: calc(100vw - 1.5rem) !important;
                border-radius: 1.25rem !important;
            }
        }

        @media (max-width: 479px) {
            .admin-responsive-table tbody > tr > td:not([colspan]) {
                grid-template-columns: minmax(0, 1fr);
                gap: 0.3rem;
            }
        }

        .admin-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .admin-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 999px;
        }
    </style>
</head>
<body class="min-h-screen">
    <span id="admin-current-route" data-route="{{ request()->route()?->getName() }}" hidden></span>
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
                        ['route' => route('admin.finish-scans.index'), 'match' => 'admin.finish-scans.*', 'icon' => 'fa-qrcode', 'label' => 'Finish Scans'],
                        ['route' => route('admin.certificates.index'), 'match' => 'admin.certificates.*', 'icon' => 'fa-certificate', 'label' => 'E-Certificates'],
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
                ['route' => route('admin.finish-scans.index'), 'match' => 'admin.finish-scans.*', 'icon' => 'fa-qrcode', 'label' => 'Finish Scans'],
                ['route' => route('admin.certificates.index'), 'match' => 'admin.certificates.*', 'icon' => 'fa-certificate', 'label' => 'E-Certificates'],
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
        class="fixed inset-0 z-40 bg-slate-400/30 backdrop-blur-sm transition-opacity duration-200 lg:hidden"
        aria-label="Close navigation menu"
        tabindex="-1"
    ></button>

    <div class="min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
        @persist('admin-sidebar')
        <aside
            id="admin-sidebar"
            data-open="false"
            class="fixed inset-y-0 left-0 z-50 flex h-dvh w-[min(20rem,88vw)] flex-col bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)] lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-auto"
            aria-label="Admin navigation"
        >
            <div class="border-b border-slate-50 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-400">
                            <i class="fas fa-flag-checkered text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xl font-bold tracking-tight text-slate-700">Racetech</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        data-admin-sidebar-close
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 transition hover:bg-slate-100 lg:hidden"
                        aria-label="Close navigation menu"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <nav
                class="admin-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-6"
                wire:navigate:scroll
                wire:scroll
            >
                @foreach ($navigation as $section)
                    @if (isset($section['items']))
                        <div class="pb-4">
                            <p class="px-4 pb-2 pt-3 text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">{{ $section['heading'] }}</p>
                            <div class="space-y-1">
                                @foreach ($section['items'] as $item)
                                    @php
                                        $active = request()->routeIs($item['match']);
                                    @endphp
                                    <a
                                        href="{{ $item['route'] }}"
                                        wire:navigate.hover
                                        data-route-match="{{ json_encode((array) $item['match']) }}"
                                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium {{ $active ? 'bg-indigo-400 text-white shadow-[0_8px_20px_rgba(129,140,248,0.35)]' : 'text-slate-600 hover:bg-slate-50' }}"
                                        @if ($active) aria-current="page" @endif
                                    >
                                        @if ($active)
                                            <span class="absolute left-1 top-1/2 h-7 w-1 -translate-y-1/2 rounded-full bg-indigo-200"></span>
                                        @endif
                                        <span class="flex h-5 w-5 items-center justify-center text-[15px] {{ $active ? 'text-white' : 'text-slate-400' }}">
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
                            wire:navigate.hover
                            data-route-match="{{ json_encode((array) $item['match']) }}"
                            class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium {{ $active ? 'bg-indigo-400 text-white shadow-[0_8px_20px_rgba(129,140,248,0.35)]' : 'text-slate-600 hover:bg-slate-50' }}"
                            @if ($active) aria-current="page" @endif
                        >
                            @if ($active)
                                <span class="absolute left-1 top-1/2 h-7 w-1 -translate-y-1/2 rounded-full bg-indigo-200"></span>
                            @endif
                            <span class="flex h-5 w-5 items-center justify-center text-[15px] {{ $active ? 'text-white' : 'text-slate-400' }}">
                                <i class="fas {{ $item['icon'] }}"></i>
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="p-4">
                <div class="rounded-2xl bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                            @if ($user->avatar_path)
                                <img
                                    src="{{ asset('storage/'.$user->avatar_path) }}"
                                    alt="{{ $user->name }}"
                                    class="h-12 w-12 rounded-full object-cover"
                                >
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-500">
                                    {{ $user->initials() ?: 'AD' }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-700">{{ $user->name }}</p>
                                <p class="truncate text-sm text-slate-500">{{ $user->roleLabel() }}</p>
                            </div>
                            <i class="fas fa-angle-down text-xs text-slate-400 transition group-open:rotate-180"></i>
                        </summary>

                        <div class="mt-4 space-y-3 border-t border-slate-50 pt-4">
                            <a
                                href="{{ route('profile.edit') }}"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100"
                            >
                                <i class="fas fa-user-gear text-sm text-slate-400"></i>
                                Profile Settings
                            </a>

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </aside>
        @endpersist

        <div class="flex min-h-screen min-w-0 flex-col">
            <header class="border-b border-slate-50 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <button
                            type="button"
                            data-admin-sidebar-open
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 transition hover:bg-slate-100 lg:hidden"
                            aria-label="Open navigation menu"
                            aria-controls="admin-sidebar"
                            aria-expanded="false"
                        >
                            <i class="fas fa-bars"></i>
                        </button>

                        <form method="GET" action="{{ route('admin.search') }}" class="relative w-full max-w-md">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                                <i class="fas fa-magnifying-glass text-sm"></i>
                            </span>
                            <input
                                type="text"
                                name="q"
                                value="{{ request('q') }}"
                                placeholder="Search anything..."
                                class="h-11 w-full rounded-2xl border-0 bg-slate-50 pl-11 pr-4 text-sm text-slate-700 outline-none transition placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            >
                        </form>
                    </div>

                </div>

                @if (session('success') || session('error') || $errors->any())
                    <div class="px-4 pb-4 sm:px-6 lg:px-8">
                        @if (session('success'))
                            <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-500">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-400">
                                {{ session('error') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-400">
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
        window.adminOnPageReady( () => {
            window.adminSidebarListeners?.abort();
            const listeners = new AbortController();
            window.adminSidebarListeners = listeners;
            const listenerOptions = { signal: listeners.signal };
            document.addEventListener('livewire:navigating', () => listeners.abort(), { once: true });
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-sidebar-backdrop');
            const openButton = document.querySelector('[data-admin-sidebar-open]');
            const closeButton = document.querySelector('[data-admin-sidebar-close]');
            const desktopQuery = window.matchMedia('(min-width: 1024px)');
            let lastFocusedElement = null;

            if (!sidebar || !backdrop || !openButton || !closeButton) {
                return;
            }

            // Freeze transitions so the active-link swap happens instantly (no flash / re-animation).
            sidebar.classList.add('is-syncing');

            const routeName = document.getElementById('admin-current-route')?.dataset.route || '';
            sidebar.querySelectorAll('nav a[data-route-match]').forEach((link) => {
                const active = JSON.parse(link.dataset.routeMatch).some((pattern) =>
                    new RegExp('^' + pattern.split('*').map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('.*') + '$').test(routeName)
                );
                ['bg-indigo-400', 'text-white', 'shadow-[0_8px_20px_rgba(129,140,248,0.35)]'].forEach((name) => link.classList.toggle(name, active));
                ['text-slate-600', 'hover:bg-slate-50'].forEach((name) => link.classList.toggle(name, !active));
                if (active) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
                const icon = link.querySelector('span.flex');
                icon?.classList.toggle('text-white', active);
                icon?.classList.toggle('text-slate-400', !active);
                let marker = link.querySelector('span.absolute');
                if (active && !marker) {
                    marker = document.createElement('span');
                    marker.className = 'absolute left-1 top-1/2 h-7 w-1 -translate-y-1/2 rounded-full bg-indigo-200';
                    link.prepend(marker);
                }
                if (marker) marker.hidden = !active;
            });

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

            openButton.addEventListener('click', openSidebar, listenerOptions);
            closeButton.addEventListener('click', () => closeSidebar(), listenerOptions);
            backdrop.addEventListener('click', () => closeSidebar(), listenerOptions);

            sidebar.querySelectorAll('nav a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (!desktopQuery.matches) {
                        closeSidebar(false);
                    }
                }, listenerOptions);
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
            }, listenerOptions);

            desktopQuery.addEventListener('change', () => syncSidebar(false), listenerOptions);
            syncSidebar(false);

            // Re-enable transitions only after the new state has been painted.
            requestAnimationFrame(() => {
                requestAnimationFrame(() => sidebar.classList.remove('is-syncing'));
            });
        });
    </script>

    <script>
        window.adminOnPageReady( () => {
            const adminContent = document.querySelector('.admin-legacy-glass');

            if (!adminContent) {
                return;
            }

            adminContent.querySelectorAll('table').forEach((table) => {
                const scrollContainer = table.closest('.overflow-x-auto');

                table.classList.add('admin-responsive-table');

                if (scrollContainer) {
                    scrollContainer.classList.add('admin-table-scroll');
                    scrollContainer.setAttribute('role', 'region');
                    scrollContainer.setAttribute('aria-label', 'Responsive data table');
                    scrollContainer.setAttribute('tabindex', '0');
                }

                const headerCells = Array.from(table.querySelectorAll('thead tr:first-child th'));

                table.classList.toggle('admin-table-dense', headerCells.length >= 7);

                table.querySelectorAll('tbody tr').forEach((row) => {
                    Array.from(row.children).forEach((cell, columnIndex) => {
                        if (!(cell instanceof HTMLTableCellElement)) {
                            return;
                        }

                        if (cell.hasAttribute('colspan')) {
                            row.classList.add('admin-table-empty-row');
                            return;
                        }

                        const header = headerCells[columnIndex];
                        const label = header
                            ? header.textContent.replace(/\s+/g, ' ').trim()
                            : 'Details';

                        cell.setAttribute('data-admin-label', label || 'Details');
                    });
                });

                headerCells.forEach((header, columnIndex) => {
                    const label = header.textContent.trim().toLowerCase();

                    if (!['action', 'actions', 'save', 'manage', 'manage registration', 'review', 'setup'].includes(label)) {
                        return;
                    }

                    header.classList.add('admin-table-action-column');

                    table.querySelectorAll('tbody tr').forEach((row) => {
                        const cell = row.children[columnIndex];

                        if (cell && !cell.hasAttribute('colspan')) {
                            cell.classList.add('admin-table-action-column');
                        }
                    });
                });
            });

            adminContent.querySelectorAll('[class*="justify-end"]').forEach((container) => {
                if (container.closest('td')) {
                    return;
                }

                const hasDirectAction = Array.from(container.children).some((child) =>
                    child.matches('a, button, form')
                );

                if (hasDirectAction) {
                    container.classList.add('admin-responsive-actions');
                }
            });
        });
    </script>

    @if (session('success'))
        <div id="admin-success-dialog" class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto px-4 py-8 sm:px-6" role="dialog" aria-modal="true" aria-labelledby="admin-success-dialog-title">
            <button type="button" data-close-success-dialog class="fixed inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative z-10 mt-10 w-full max-w-lg overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                <div class="border-b border-slate-50 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-500">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500">Success</p>
                                <h2 id="admin-success-dialog-title" class="mt-1 text-2xl font-bold tracking-tight text-slate-700">Entry Completed</h2>
                            </div>
                        </div>
                        <button type="button" data-close-success-dialog class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Close dialog">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <p class="text-sm leading-7 text-slate-600">{{ session('success') }}</p>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-slate-50 px-6 py-4">
                    <button type="button" data-close-success-dialog class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                        Done
                    </button>
                    @if ($successCreateAction)
                        @if (isset($successCreateAction['target']))
                            <button type="button" data-success-create-target="{{ $successCreateAction['target'] }}" class="inline-flex h-11 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                {{ $successCreateAction['label'] }}
                            </button>
                        @else
                            <a href="{{ $successCreateAction['url'] }}" class="inline-flex h-11 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                {{ $successCreateAction['label'] }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <script>
            window.adminOnPageReady( () => {
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
    @livewireScripts
</body>
</html>
