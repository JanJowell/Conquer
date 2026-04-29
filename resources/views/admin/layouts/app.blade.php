<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer Admin - @yield('title', 'Dashboard')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-bg: #f7f8fa;
            --admin-card: #ffffff;
            --admin-border: #d9dee7;
            --admin-text: #151b26;
            --admin-muted: #6d7685;
            --admin-accent: #111827;
            --admin-active: #eef0f3;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--admin-bg);
            color: var(--admin-text);
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
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Dashboard'],
                ['route' => route('admin.users.index'), 'match' => 'admin.users.*', 'icon' => 'fa-users', 'label' => 'Users'],
                ['route' => route('admin.events.index'), 'match' => 'admin.events.*', 'icon' => 'fa-calendar-days', 'label' => 'Events'],
                ['route' => route('admin.participants.index'), 'match' => 'admin.participants.*', 'icon' => 'fa-id-card', 'label' => 'Participants'],
                ['route' => route('admin.check-in.index'), 'match' => 'admin.check-in.*', 'icon' => 'fa-clipboard-check', 'label' => 'Check-in'],
                ['route' => route('admin.results.index'), 'match' => 'admin.results.*', 'icon' => 'fa-trophy', 'label' => 'Results'],
                ['route' => route('admin.announcements.index'), 'match' => 'admin.announcements.*', 'icon' => 'fa-bullhorn', 'label' => 'Announcements'],
                ['route' => route('admin.analytics'), 'match' => 'admin.analytics', 'icon' => 'fa-chart-column', 'label' => 'Analytics'],
                ['route' => route('admin.reports'), 'match' => 'admin.reports', 'icon' => 'fa-file-export', 'label' => 'Reports'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-message', 'label' => 'Feedback Insights'],
                ['route' => route('admin.notifications.index'), 'match' => 'admin.notifications.*', 'icon' => 'fa-bell', 'label' => 'Notifications'],
                ['route' => route('admin.content.community-posts'), 'match' => 'admin.content.community-posts*', 'icon' => 'fa-comments', 'label' => 'Community'],
                ['route' => route('admin.content.training-modules'), 'match' => 'admin.content.training-modules*', 'icon' => 'fa-image', 'label' => 'Training'],
                ['route' => route('admin.content.checkpoints'), 'match' => 'admin.content.checkpoints*', 'icon' => 'fa-file-lines', 'label' => 'Checkpoints'],
                ['route' => route('admin.security.dashboard'), 'match' => 'admin.security.*', 'icon' => 'fa-gear', 'label' => 'Settings'],
            ],
            \App\Models\User::ROLE_EXECUTIVE => [
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Executive Dashboard'],
                ['route' => route('admin.analytics'), 'match' => 'admin.analytics', 'icon' => 'fa-chart-column', 'label' => 'Analytics'],
                ['route' => route('admin.events.index'), 'match' => 'admin.events.index', 'icon' => 'fa-calendar-days', 'label' => 'Events Overview'],
                ['route' => route('admin.users.index'), 'match' => 'admin.users.*', 'icon' => 'fa-user', 'label' => 'Users Overview'],
                ['route' => route('admin.reports'), 'match' => 'admin.reports', 'icon' => 'fa-file-export', 'label' => 'Reports'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-message', 'label' => 'Feedback Insights'],
            ],
            \App\Models\User::ROLE_CONTENT_MODERATOR => [
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Dashboard'],
                ['route' => route('admin.announcements.index'), 'match' => 'admin.announcements.*', 'icon' => 'fa-bullhorn', 'label' => 'Announcements'],
                ['route' => route('admin.content.training-modules'), 'match' => 'admin.content.training-modules*', 'icon' => 'fa-file-pen', 'label' => 'Content Review'],
                ['route' => route('admin.content.community-posts'), 'match' => 'admin.content.community-posts*', 'icon' => 'fa-comments', 'label' => 'Comments / Feedback'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-flag', 'label' => 'Reports / Flags'],
            ],
            \App\Models\User::ROLE_EVENT_MANAGER => [
                ['route' => route('admin.dashboard'), 'match' => 'admin.dashboard', 'icon' => 'fa-house', 'label' => 'Dashboard'],
                ['route' => route('admin.events.index'), 'match' => 'admin.events.*', 'icon' => 'fa-calendar-days', 'label' => 'My Events'],
                ['route' => route('admin.participants.index'), 'match' => 'admin.participants.*', 'icon' => 'fa-id-card', 'label' => 'Participants'],
                ['route' => route('admin.check-in.index'), 'match' => 'admin.check-in.*', 'icon' => 'fa-clipboard-check', 'label' => 'Check-in'],
                ['route' => route('admin.results.index'), 'match' => 'admin.results.*', 'icon' => 'fa-trophy', 'label' => 'Results'],
                ['route' => route('admin.announcements.index'), 'match' => 'admin.announcements.*', 'icon' => 'fa-bullhorn', 'label' => 'Announcements'],
                ['route' => route('admin.categories.index'), 'match' => 'admin.categories.*', 'icon' => 'fa-road', 'label' => 'Categories'],
                ['route' => route('admin.content.checkpoints'), 'match' => 'admin.content.checkpoints*', 'icon' => 'fa-location-dot', 'label' => 'Checkpoints'],
                ['route' => route('admin.reports'), 'match' => 'admin.reports', 'icon' => 'fa-chart-line', 'label' => 'Reports'],
                ['route' => route('admin.feedback-insights'), 'match' => 'admin.feedback-insights', 'icon' => 'fa-message', 'label' => 'Feedback'],
            ],
        ];

        $navigation = $navigationByRole[$user->normalizedRole()] ?? $navigationByRole[\App\Models\User::ROLE_SUPER_ADMIN];
    @endphp

    <div class="min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside class="hidden border-r border-[var(--admin-border)] bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
            <div class="border-b border-[var(--admin-border)] px-4 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-16 w-16 items-center justify-center rounded-sm border border-[#cfd5de] bg-[#f4f5f7] text-[#98a1ae]">
                        <i class="fas fa-flag-checkered text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold tracking-tight text-[var(--admin-text)]">Conquer</p>
                    </div>
                </div>
            </div>

            <nav class="admin-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-6">
                @foreach ($navigation as $item)
                    @php
                        $active = request()->routeIs($item['match']);
                    @endphp
                    <a
                        href="{{ $item['route'] }}"
                        class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition {{ $active ? 'bg-[var(--admin-active)] text-[var(--admin-text)]' : 'text-[#202733] hover:bg-[#f4f6f8]' }}"
                    >
                        <span class="flex h-5 w-5 items-center justify-center text-[15px] text-[#5e6878]">
                            <i class="fas {{ $item['icon'] }}"></i>
                        </span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="p-4">
                <div class="rounded-2xl border border-[var(--admin-border)] bg-white p-4 shadow-sm">
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
            <header class="border-b border-[var(--admin-border)] bg-white">
                <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[var(--admin-border)] text-[#606978] lg:hidden">
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
                                class="h-11 w-full rounded-xl border border-[var(--admin-border)] bg-white pl-11 pr-4 text-sm text-[var(--admin-text)] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
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

            <main class="min-w-0 flex-1 overflow-x-hidden bg-[var(--admin-bg)] px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
