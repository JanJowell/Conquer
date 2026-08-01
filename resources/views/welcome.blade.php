<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer - Event Management Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --surface: #eaf2f9;
            --ink: #111827;
            --muted: #64748b;
            --line: rgba(255, 255, 255, 0.62);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                linear-gradient(135deg, #eaf2f9 0%, #f8fbff 48%, #e6f7f2 100%);
            color: var(--ink);
        }

        .glass {
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.42);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.10);
            backdrop-filter: blur(22px);
        }
    </style>
</head>
<body class="min-h-screen antialiased">
    @php
        $featureCards = [
            [
                'eyebrow' => 'Operations',
                'title' => 'Event Posting',
                'description' => 'Build race pages, organize venues, and keep schedules ready for runners and staff.',
                'icon' => 'fa-calendar-days',
                'tone' => 'text-sky-700 bg-sky-100/80 border-sky-200/70',
            ],
            [
                'eyebrow' => 'Participants',
                'title' => 'Registration Tracking',
                'description' => 'Follow signups, categories, payments, and race-day progress in one workspace.',
                'icon' => 'fa-id-card',
                'tone' => 'text-emerald-700 bg-emerald-100/80 border-emerald-200/70',
            ],
            [
                'eyebrow' => 'Communication',
                'title' => 'Announcements',
                'description' => 'Publish notices, reminders, and updates directly from event operations.',
                'icon' => 'fa-bullhorn',
                'tone' => 'text-amber-700 bg-amber-100/80 border-amber-200/70',
            ],
            [
                'eyebrow' => 'Mobile',
                'title' => 'Runner Ready',
                'description' => 'Keep participant-facing content organized for mobile access and coordination.',
                'icon' => 'fa-mobile-screen-button',
                'tone' => 'text-violet-700 bg-violet-100/80 border-violet-200/70',
            ],
        ];
    @endphp

    <header class="sticky top-0 z-30 border-b border-white/60 bg-white/45 backdrop-blur-2xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/70 bg-white/60 text-[#151b26] shadow-sm">
                    <i class="fas fa-flag-checkered text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-[0] text-[#111827]">Conquer</p>
                    <p class="mt-1 text-sm text-[#64748b]">Running and recreational event management</p>
                </div>
            </div>

            <a href="{{ route('login') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-white/70 bg-white/55 px-6 text-sm font-semibold text-[#202733] shadow-sm backdrop-blur-xl transition hover:bg-white/80">
                Login
            </a>
        </div>
    </header>

    <section class="relative overflow-hidden">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:py-16 lg:grid-cols-[minmax(0,0.95fr)_minmax(360px,1.05fr)] lg:px-8">
            <div class="self-center">
                <span class="inline-flex rounded-full border border-white/70 bg-white/55 px-5 py-2 text-xs font-bold uppercase tracking-[0.28em] text-[#315fa8] shadow-sm backdrop-blur-xl">
                    Conquer Platform
                </span>

                <h1 class="mt-7 max-w-3xl text-6xl font-extrabold leading-[1.02] tracking-[0] text-[#111827] sm:text-7xl">
                    Conquer
                </h1>

                <p class="mt-6 max-w-2xl text-xl leading-9 text-[#475569]">
                    A cleaner control system for race publishing, registration tracking, announcements, payments, and event-day operations.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="inline-flex h-13 items-center justify-center rounded-2xl bg-[#151b26] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                        Admin Login
                    </a>
                    <a href="#events" class="inline-flex h-13 items-center justify-center rounded-2xl border border-white/70 bg-white/55 px-6 py-3.5 text-sm font-semibold text-[#202733] shadow-sm backdrop-blur-xl transition hover:bg-white/80">
                        View Events
                    </a>
                </div>

                <div class="mt-8 grid max-w-2xl gap-3 sm:grid-cols-3">
                    <div class="glass rounded-2xl p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">Events</p>
                        <p class="mt-2 text-3xl font-bold tracking-[0] text-[#111827]">{{ $events->count() }}</p>
                    </div>
                    <div class="glass rounded-2xl p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">Updates</p>
                        <p class="mt-2 text-3xl font-bold tracking-[0] text-[#111827]">{{ $announcements->count() }}</p>
                    </div>
                    <div class="glass rounded-2xl p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">Access</p>
                        <p class="mt-2 text-3xl font-bold tracking-[0] text-[#111827]">Web</p>
                    </div>
                </div>
            </div>

            <div class="glass relative min-h-[520px] overflow-hidden rounded-[2rem] p-5">
                <div class="rounded-[1.5rem] border border-white/70 bg-white/50 p-5 shadow-sm backdrop-blur-xl">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">Operations Board</p>
                            <h2 class="mt-2 text-2xl font-bold tracking-[0] text-[#111827]">Race Control</h2>
                        </div>
                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-100/80 px-3 py-1 text-xs font-bold text-emerald-700">
                            Live
                        </span>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/70 bg-white/60 p-4">
                            <p class="text-xs font-semibold text-[#64748b]">Registrations</p>
                            <p class="mt-2 text-2xl font-bold tracking-[0]">248</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/60 p-4">
                            <p class="text-xs font-semibold text-[#64748b]">Payments</p>
                            <p class="mt-2 text-2xl font-bold tracking-[0]">92%</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/60 p-4">
                            <p class="text-xs font-semibold text-[#64748b]">Check-ins</p>
                            <p class="mt-2 text-2xl font-bold tracking-[0]">36</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach (['Event details verified', 'Categories open for registration', 'Announcement queued for mobile', 'Results workspace ready'] as $index => $item)
                            <div class="flex items-center gap-3 rounded-2xl border border-white/70 bg-white/55 p-4">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $index === 2 ? 'border border-amber-200 bg-amber-100 text-amber-700' : 'border border-emerald-200 bg-emerald-100 text-emerald-700' }}">
                                    <i class="fas {{ $index === 2 ? 'fa-bell' : 'fa-check' }} text-xs"></i>
                                </span>
                                <p class="text-sm font-semibold text-[#202733]">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ($featureCards as $card)
                        <article class="rounded-3xl border border-white/70 bg-white/50 p-5 shadow-sm backdrop-blur-xl transition hover:-translate-y-1 hover:bg-white/70">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl border {{ $card['tone'] }}">
                                <i class="fas {{ $card['icon'] }}"></i>
                            </div>
                            <p class="mt-5 text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">{{ $card['eyebrow'] }}</p>
                            <h3 class="mt-2 text-xl font-bold tracking-[0] text-[#111827]">{{ $card['title'] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-[#475569]">{{ $card['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <main id="events" class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
        <section class="glass rounded-[2rem] p-5 sm:p-6">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-[#64748b]">Live Schedule</p>
                    <h2 class="mt-2 text-4xl font-bold tracking-[0] text-[#111827]">Upcoming Events</h2>
                    <p class="mt-2 text-sm leading-6 text-[#64748b]">Browse the latest event listings highlighted in Conquer.</p>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                @forelse($events as $event)
                    <article class="rounded-3xl border border-white/70 bg-white/55 p-6 shadow-sm backdrop-blur-xl transition hover:-translate-y-1 hover:bg-white/75">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">Event</p>
                                <h3 class="mt-3 text-2xl font-bold leading-tight tracking-[0] text-[#111827]">{{ $event->title }}</h3>
                            </div>
                            <span class="rounded-full border border-sky-200 bg-sky-100/80 px-3 py-1 text-xs font-bold text-sky-700">
                                Upcoming
                            </span>
                        </div>

                        <div class="mt-6 space-y-3 text-sm text-[#475569]">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/70 bg-white/60 text-[#64748b]">
                                    <i class="fas fa-location-dot"></i>
                                </span>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#64748b]">Venue</p>
                                    <p class="font-semibold text-[#202733]">{{ $event->venue }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/70 bg-white/60 text-[#64748b]">
                                    <i class="fas fa-calendar-days"></i>
                                </span>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#64748b]">Date</p>
                                    <p class="font-semibold text-[#202733]">{{ $event->event_date->format('F d, Y') }}</p>
                                </div>
                            </div>
                        </div>

                        <p class="mt-6 text-sm leading-7 text-[#475569]">{{ $event->description }}</p>
                    </article>
                @empty
                    <div class="rounded-3xl border border-white/70 bg-white/55 p-6 shadow-sm backdrop-blur-xl lg:col-span-2 xl:col-span-3">
                        <p class="text-sm text-[#64748b]">No events available right now.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="mt-8 glass rounded-[2rem] p-5 sm:p-6">
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-[#64748b]">Updates</p>
                <h2 class="mt-2 text-4xl font-bold tracking-[0] text-[#111827]">Announcements</h2>
                <p class="mt-2 text-sm leading-6 text-[#64748b]">Stay informed with organizer updates, reminders, and notices.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                @forelse($announcements as $announcement)
                    <article class="rounded-3xl border border-white/70 bg-white/55 p-6 shadow-sm backdrop-blur-xl">
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-amber-200 bg-amber-100/80 text-amber-700">
                                <i class="fas fa-bullhorn"></i>
                            </span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#64748b]">Announcement</p>
                                <h3 class="mt-2 text-2xl font-bold tracking-[0] text-[#111827]">{{ $announcement->title }}</h3>
                                <p class="mt-4 text-sm leading-7 text-[#475569]">{{ $announcement->content }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-white/70 bg-white/55 p-6 shadow-sm backdrop-blur-xl lg:col-span-2">
                        <p class="text-sm text-[#64748b]">No announcements yet.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
