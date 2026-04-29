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
            --surface: #f7f8fa;
            --card: #ffffff;
            --border: #d9dee7;
            --text: #151b26;
            --muted: #6d7685;
            --soft: #eef1f5;
            --brand: #1d4ed8;
            --brand-dark: #111827;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(29, 78, 216, 0.08), transparent 26%),
                radial-gradient(circle at 85% 12%, rgba(148, 163, 184, 0.16), transparent 18%),
                var(--surface);
            color: var(--text);
        }
    </style>
</head>
<body class="min-h-screen">
    @php
        $featureCards = [
            [
                'eyebrow' => 'Operations',
                'title' => 'Event Posting',
                'description' => 'Create race pages, organize venue details, and keep schedules visible for runners and organizers.',
                'icon' => 'fa-calendar-days',
            ],
            [
                'eyebrow' => 'Participants',
                'title' => 'Registration Tracking',
                'description' => 'Monitor signups, categories, and participant progress through a cleaner registration workflow.',
                'icon' => 'fa-id-card',
            ],
            [
                'eyebrow' => 'Communication',
                'title' => 'Announcements',
                'description' => 'Share reminders, updates, and race-day notices from one centralized event workspace.',
                'icon' => 'fa-bullhorn',
            ],
            [
                'eyebrow' => 'Platform',
                'title' => 'Mobile Ready',
                'description' => 'Support a web-first workflow today while staying ready for mobile event access and coordination.',
                'icon' => 'fa-mobile-screen-button',
            ],
        ];
    @endphp

    <header class="sticky top-0 z-30 border-b border-[#d9dee7] bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-sm border border-[#cfd5de] bg-[#f4f5f7] text-[#6b7280]">
                    <i class="fas fa-flag-checkered text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-[#111827]">Conquer</p>
                    <p class="mt-1 text-sm text-[#6d7685]">Running and recreational event management</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="rounded-xl border border-[#d9dee7] bg-white px-5 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                    Login
                </a>
            </div>
        </div>
    </header>

    <section class="relative overflow-hidden border-b border-[#d9dee7] bg-[linear-gradient(135deg,#f8fafc_0%,#eff4fb_48%,#e8eef8_100%)]">
        <div class="absolute inset-0">
            <div class="absolute left-[-5rem] top-[-4rem] h-56 w-56 rounded-full bg-blue-200/35 blur-3xl"></div>
            <div class="absolute right-[-4rem] top-10 h-64 w-64 rounded-full bg-slate-300/30 blur-3xl"></div>
        </div>

        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:py-20 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)] lg:px-8">
            <div class="self-center">
                <span class="inline-flex rounded-full border border-[#bfd1f8] bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-[#315fa8] shadow-sm">
                    Conquer Platform
                </span>

                <h1 class="mt-6 max-w-4xl text-5xl font-semibold leading-[1.02] tracking-tight text-[#111827] sm:text-6xl">
                    Manage races, registrations, events, and announcements with one clear control system.
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-8 text-[#556070]">
                    Conquer gives organizers and participants a cleaner way to handle event publishing, category management, announcements, and race coordination without jumping across disconnected tools.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-xl bg-[#111827] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                        Admin Login
                    </a>
                </div>

                <div class="mt-6 rounded-2xl border border-[#d9dee7] bg-white/90 px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Access Notice</p>
                    <p class="mt-2 text-sm leading-6 text-[#5a6473]">
                        The web platform is reserved for admin operations. Runner access is planned for the mobile application.
                    </p>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-[#d9dee7] bg-white/90 p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Events</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">{{ $events->count() }}</p>
                        <p class="mt-2 text-sm text-[#6d7685]">Latest events highlighted on the platform</p>
                    </div>
                    <div class="rounded-2xl border border-[#d9dee7] bg-white/90 p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Announcements</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">{{ $announcements->count() }}</p>
                        <p class="mt-2 text-sm text-[#6d7685]">Published updates and race notices</p>
                    </div>
                    <div class="rounded-2xl border border-[#d9dee7] bg-white/90 p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Access</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Web</p>
                        <p class="mt-2 text-sm text-[#6d7685]">Built for fast organizer and participant workflows</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 self-center sm:grid-cols-2">
                @foreach ($featureCards as $card)
                    <article class="rounded-3xl border border-[#d9dee7] bg-white/90 p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] text-[#4f5968]">
                            <i class="fas {{ $card['icon'] }}"></i>
                        </div>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">{{ $card['eyebrow'] }}</p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-[#111827]">{{ $card['title'] }}</h2>
                        <p class="mt-3 text-sm leading-7 text-[#5a6473]">{{ $card['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <section>
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Live Schedule</p>
                    <h2 class="mt-2 text-4xl font-semibold tracking-tight text-[#111827]">Upcoming Events</h2>
                    <p class="mt-2 text-sm leading-6 text-[#6d7685]">Browse the latest event listings currently highlighted in Conquer.</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
                @forelse($events as $event)
                    <article class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Event</p>
                                <h3 class="mt-3 text-3xl font-semibold leading-tight tracking-tight text-[#111827]">{{ $event->title }}</h3>
                            </div>
                            <span class="rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-semibold text-[#315fa8]">
                                Upcoming
                            </span>
                        </div>

                        <div class="mt-6 space-y-3 text-sm text-[#556070]">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#d9dee7] bg-[#f8f9fb] text-[#5e6878]">
                                    <i class="fas fa-location-dot"></i>
                                </span>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#8a93a1]">Venue</p>
                                    <p class="font-medium text-[#202733]">{{ $event->venue }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#d9dee7] bg-[#f8f9fb] text-[#5e6878]">
                                    <i class="fas fa-calendar-days"></i>
                                </span>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#8a93a1]">Date</p>
                                    <p class="font-medium text-[#202733]">{{ $event->event_date->format('F d, Y') }}</p>
                                </div>
                            </div>
                        </div>

                        <p class="mt-6 text-sm leading-7 text-[#5a6473]">
                            {{ $event->description }}
                        </p>
                    </article>
                @empty
                    <div class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm lg:col-span-2 xl:col-span-3">
                        <p class="text-sm text-[#6d7685]">No events available right now.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="mt-14">
            <div class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Updates</p>
                <h2 class="mt-2 text-4xl font-semibold tracking-tight text-[#111827]">Announcements</h2>
                <p class="mt-2 text-sm leading-6 text-[#6d7685]">Stay informed with the latest organizer updates, reminders, and notices.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                @forelse($announcements as $announcement)
                    <article class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] text-[#5e6878]">
                                <i class="fas fa-bullhorn"></i>
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Announcement</p>
                                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-[#111827]">{{ $announcement->title }}</h3>
                                <p class="mt-4 text-sm leading-7 text-[#5a6473]">
                                    {{ $announcement->content }}
                                </p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm lg:col-span-2">
                        <p class="text-sm text-[#6d7685]">No announcements yet.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
