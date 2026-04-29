<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="h-screen overflow-hidden bg-slate-950 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">
    @php
        $user = auth()->user();

        $navigation = [
            [
                'label' => 'Platform',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'fa-grid-2'],
                ],
            ],
            [
                'label' => 'Events',
                'items' => [
                    ['label' => 'All Events', 'href' => '/admin/events', 'active' => request()->is('admin/events'), 'icon' => 'fa-calendar-days'],
                    ['label' => 'Create Event', 'href' => '/admin/events/create', 'active' => request()->is('admin/events/create'), 'icon' => 'fa-square-plus'],
                    ['label' => 'Results', 'href' => '/admin/events', 'active' => false, 'icon' => 'fa-trophy'],
                ],
            ],
            [
                'label' => 'Administration',
                'items' => [
                    ['label' => 'User Management', 'href' => '/admin/users', 'active' => request()->is('admin/users*'), 'icon' => 'fa-users'],
                    ['label' => 'Community', 'href' => '/admin/content/community-posts', 'active' => request()->is('admin/content/community-posts*'), 'icon' => 'fa-comments'],
                    ['label' => 'Training', 'href' => '/admin/content/training-modules', 'active' => request()->is('admin/content/training-modules*'), 'icon' => 'fa-graduation-cap'],
                    ['label' => 'Checkpoints', 'href' => '/admin/content/checkpoints', 'active' => request()->is('admin/content/checkpoints*'), 'icon' => 'fa-location-dot'],
                    ['label' => 'Security', 'href' => '/admin/security/dashboard', 'active' => request()->is('admin/security*'), 'icon' => 'fa-shield-halved'],
                    ['label' => 'Notifications', 'href' => '/admin/notifications', 'active' => request()->is('admin/notifications*'), 'icon' => 'fa-bell'],
                ],
            ],
            [
                'label' => 'Analytics',
                'items' => [
                    ['label' => 'Reports', 'href' => '/admin/analytics', 'active' => request()->is('admin/analytics'), 'icon' => 'fa-chart-line'],
                    ['label' => 'Statistics', 'href' => '/admin/analytics', 'active' => false, 'icon' => 'fa-chart-column'],
                ],
            ],
        ];
    @endphp

    <div class="relative h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.15),_transparent_22%),radial-gradient(circle_at_80%_10%,_rgba(16,185,129,0.14),_transparent_18%),linear-gradient(180deg,_#020617_0%,_#020617_34%,_#e2e8f0_34%,_#f8fafc_100%)]">
        <div class="absolute inset-0 opacity-50">
            <div class="absolute left-0 top-0 h-72 w-72 rounded-full bg-sky-400/10 blur-3xl"></div>
            <div class="absolute right-0 top-16 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"></div>
        </div>

        <div class="relative h-screen bg-transparent">
            <aside class="fixed inset-y-0 left-0 z-40 hidden h-screen w-72 border-r border-white/10 bg-slate-950/90 backdrop-blur-xl lg:block">
                <div class="flex h-full flex-col">
                    <div class="border-b border-white/10 px-6 py-7">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 via-sky-500 to-blue-700 text-lg text-white shadow-lg shadow-cyan-950/40">
                                <i class="fas fa-flag-checkered"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.34em] text-cyan-300/80">Conquer</p>
                                <h1 class="mt-1 text-2xl font-bold text-white">Admin</h1>
                                <p class="text-sm text-slate-400">Operations command center</p>
                            </div>
                        </div>
                    </div>

                    <nav class="flex-1 overflow-y-auto px-4 py-6">
                        <div class="space-y-7">
                            @foreach ($navigation as $group)
                                <section>
                                    <p class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.32em] text-slate-500">
                                        {{ $group['label'] }}
                                    </p>

                                    <div class="space-y-1.5">
                                        @foreach ($group['items'] as $item)
                                            <a
                                                href="{{ $item['href'] }}"
                                                class="group flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition-all duration-200 {{ $item['active'] ? 'bg-white text-slate-950 shadow-lg shadow-slate-950/20' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                                            >
                                                <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $item['active'] ? 'bg-slate-100 text-sky-600' : 'bg-white/5 text-slate-400 group-hover:bg-white/10 group-hover:text-cyan-300' }}">
                                                    <i class="fas {{ $item['icon'] }}"></i>
                                                </span>
                                                <span>{{ $item['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </nav>

                    <div class="border-t border-white/10 p-4">
                        <div class="rounded-[1.5rem] border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-600 text-sm font-bold text-white">
                                    {{ $user ? strtoupper(substr($user->name, 0, 2)) : 'RA' }}
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-white">
                                        {{ $user->name ?? 'Conquer Admin' }}
                                    </p>
                                    <p class="truncate text-xs uppercase tracking-[0.24em] text-slate-400">
                                        {{ $user ? str_replace('_', ' ', $user->role) : 'Administrator' }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a
                                    href="{{ route('profile.edit') }}"
                                    class="flex items-center justify-center rounded-2xl border border-white/10 bg-slate-900 px-3 py-3 text-sm font-medium text-slate-200 transition hover:border-cyan-400/40 hover:text-white"
                                >
                                    Settings
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-3 text-sm font-medium text-rose-200 transition hover:bg-rose-500/20"
                                    >
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <header class="sticky top-0 z-30 flex items-center justify-between border-b border-white/10 bg-slate-950/95 px-4 py-4 backdrop-blur lg:hidden">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-cyan-300/80">Conquer</p>
                    <h1 class="text-lg font-bold text-white">Admin</h1>
                </div>

                <details class="relative">
                    <summary class="list-none cursor-pointer rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white">
                        Menu
                    </summary>

                    <div class="absolute right-0 mt-2 w-52 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-xl">
                        <a href="{{ route('dashboard') }}" class="block px-4 py-3 text-sm text-zinc-200 transition hover:bg-white/5">
                            Dashboard
                        </a>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-3 text-sm text-zinc-200 transition hover:bg-white/5">
                            Settings
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-3 text-left text-sm text-rose-300 transition hover:bg-rose-500/10">
                                Logout
                            </button>
                        </form>
                    </div>
                </details>
            </header>

            <main class="h-screen overflow-y-auto px-4 py-6 lg:ml-72 lg:px-8 lg:py-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
