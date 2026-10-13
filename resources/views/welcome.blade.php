<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer - Event Management Platform</title>
    <meta name="description" content="Conquer is a running and recreational event management platform for race publishing, registrations, announcements, payments, and event-day operations.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --surface: #eef4fc;
            --ink: #334155;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at 15% 0%, rgba(129, 140, 248, 0.16), transparent 45%),
                radial-gradient(circle at 85% 10%, rgba(56, 189, 248, 0.18), transparent 50%),
                radial-gradient(circle at 50% 100%, rgba(45, 212, 191, 0.14), transparent 55%),
                linear-gradient(180deg, #eaf2fc 0%, #f4f8fd 55%, #eef4fc 100%);
            background-attachment: fixed;
            color: var(--ink);
            overflow-x: hidden;
        }

        /* Keep small labels readable against the light pastel surfaces. */
        .text-slate-400 { color: #64748b !important; }
        .text-indigo-400 { color: #4f46e5 !important; }
        .bg-indigo-400 { background-color: #4f46e5 !important; }

        /* Sections land below the sticky header when jumped to via #anchor links. */
        [id] { scroll-margin-top: 6rem; }

        .soft-card {
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(148, 163, 184, 0.12);
        }

        .soft-card-lift {
            transition: transform 200ms ease, box-shadow 200ms ease;
        }

        .soft-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(148, 163, 184, 0.20);
        }

        .hero-gradient-text {
            background: linear-gradient(120deg, #6366f1 0%, #38bdf8 55%, #2dd4bf 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Gentle entrance for hero content. */
        @keyframes rise-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .rise-in { animation: rise-in 700ms cubic-bezier(0.22, 1, 0.36, 1) both; }
        .rise-in-delay-1 { animation-delay: 80ms; }
        .rise-in-delay-2 { animation-delay: 160ms; }
        .rise-in-delay-3 { animation-delay: 240ms; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .rise-in { animation: none; }
            .soft-card-lift, .verse-slide { transition: none !important; }
            .soft-card-lift:hover { transform: none; }
        }
    </style>
</head>
<body class="min-h-screen antialiased">
    <a href="#events" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-5 focus:py-3 focus:text-sm focus:font-semibold focus:text-indigo-500 focus:shadow-lg">
        Skip to events
    </a>

    @php
        $featureCards = [
            [
                'eyebrow' => 'Operations',
                'title' => 'Event Posting',
                'description' => 'Build event pages, organize venues, and keep schedules ready for participants and staff.',
                'icon' => 'fa-calendar-days',
                'tone' => 'text-sky-500 bg-sky-50',
            ],
            [
                'eyebrow' => 'Participants',
                'title' => 'Registration Tracking',
                'description' => 'Follow signups, categories, payments, and event-day progress in one workspace.',
                'icon' => 'fa-id-card',
                'tone' => 'text-emerald-500 bg-emerald-50',
            ],
            [
                'eyebrow' => 'Communication',
                'title' => 'Announcements',
                'description' => 'Publish notices, reminders, and updates directly from event operations.',
                'icon' => 'fa-bullhorn',
                'tone' => 'text-amber-500 bg-amber-50',
            ],
            [
                'eyebrow' => 'Mobile',
                'title' => 'Participants Ready',
                'description' => 'Keep participant-facing content organized for mobile access and coordination.',
                'icon' => 'fa-mobile-screen-button',
                'tone' => 'text-violet-400 bg-violet-50',
            ],
        ];

        $workflowSteps = [
            ['icon' => 'fa-pen-to-square', 'title' => 'Publish', 'description' => 'Post the event, venue, schedule, and categories.'],
            ['icon' => 'fa-user-plus', 'title' => 'Register', 'description' => 'Participants sign up and settle payments.'],
            ['icon' => 'fa-clipboard-check', 'title' => 'Check in', 'description' => 'Verify participants quickly on event day.'],
            ['icon' => 'fa-trophy', 'title' => 'Finish', 'description' => 'Record results and issue e-certificates.'],
        ];

        $stewardshipVerses = [
            [
                'reference' => '1 Corinthians 6:19-20',
                'text' => 'What? know ye not that your body is the temple of the Holy Ghost which is in you, which ye have of God, and ye are not your own? For ye are bought with a price: therefore glorify God in your body, and in your spirit, which are God’s.',
            ],
            [
                'reference' => '1 Corinthians 3:16-17',
                'text' => 'Know ye not that ye are the temple of God, and that the Spirit of God dwelleth in you? If any man defile the temple of God, him shall God destroy; for the temple of God is holy, which temple ye are.',
            ],
            [
                'reference' => '1 Peter 4:10',
                'text' => 'As every man hath received the gift, even so minister the same one to another, as good stewards of the manifold grace of God.',
            ],
            [
                'reference' => '1 Corinthians 4:2',
                'text' => 'Moreover it is required in stewards, that a man be found faithful.',
            ],
            [
                'reference' => 'Psalm 24:1',
                'text' => 'The earth is the LORD’s, and the fulness thereof; the world, and they that dwell therein.',
            ],
            [
                'reference' => 'Luke 16:10',
                'text' => 'He that is faithful in that which is least is faithful also in much: and he that is unjust in the least is unjust also in much.',
            ],
        ];
    @endphp

    {{-- HEADER --}}
    <header class="sticky top-0 z-30 border-b border-white/60 bg-white/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4 lg:px-8">
            <a href="#top" class="flex min-w-0 items-center gap-3 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-500 sm:h-14 sm:w-14">
                    <i class="fas fa-flag-checkered text-lg sm:text-xl"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xl font-bold text-slate-700 sm:text-2xl">Conquer</p>
                    <p class="hidden truncate text-sm text-slate-400 sm:block">Running and recreational event management</p>
                </div>
            </a>

            <nav class="flex items-center gap-1 sm:gap-2" aria-label="Main">
                <a href="#events" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-slate-500 transition hover:bg-white hover:text-slate-700 md:inline-flex">Events</a>
                <a href="#announcements" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-slate-500 transition hover:bg-white hover:text-slate-700 md:inline-flex">Announcements</a>
                <a href="{{ route('login') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-white px-5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 sm:px-6">
                    <i class="fas fa-right-to-bracket text-xs text-slate-400"></i>
                    Login
                </a>
            </nav>
        </div>
    </header>

    {{-- HERO --}}
    <section id="top" class="relative overflow-hidden">
        {{-- Decorative background silhouettes (removed automatically if a file cannot be loaded) --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <img src="{{ asset('images/runner-female.png') }}" alt="" onerror="this.remove()" class="absolute -right-4 top-0 h-52 w-auto opacity-[0.06] sm:h-64 lg:h-72">
            <img src="{{ asset('images/cyclist.png') }}" alt="" onerror="this.remove()" class="absolute bottom-0 left-1/2 hidden h-64 w-auto -translate-x-1/2 opacity-[0.05] md:block lg:h-80">
            <img src="{{ asset('images/hiker.png') }}" alt="" onerror="this.remove()" class="absolute -bottom-4 right-6 hidden h-48 w-auto opacity-[0.06] sm:block lg:h-64">
        </div>

        <div class="relative mx-auto max-w-4xl px-4 py-12 text-center sm:px-6 md:py-20 lg:px-8">
            <span class="rise-in inline-flex items-center gap-2 rounded-full bg-white/80 px-5 py-2 text-xs font-bold uppercase tracking-[0.28em] text-indigo-400 shadow-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                Conquer Platform
            </span>

            <h1 class="rise-in rise-in-delay-1 mx-auto mt-7 max-w-3xl text-4xl font-extrabold leading-[1.08] tracking-tight text-slate-700 [overflow-wrap:anywhere] sm:text-6xl sm:leading-[1.05] lg:text-7xl">
                Run your race <span class="hero-gradient-text">well</span>, from start to finish.
            </h1>

            <p class="rise-in rise-in-delay-2 mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-500 sm:text-xl sm:leading-9">
                A cleaner control system for race publishing, registration tracking, announcements, payments, and event-day operations.
            </p>

            <div class="rise-in rise-in-delay-3 mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="#events" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-indigo-400 px-7 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(129,140,248,0.35)] transition hover:-translate-y-0.5 hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:ring-offset-2 sm:w-auto">
                    View Events
                    <i class="fas fa-arrow-down text-xs"></i>
                </a>
                <a href="{{ route('login') }}" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-white px-7 text-sm font-semibold text-slate-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:ring-offset-2 sm:w-auto">
                    <i class="fas fa-lock text-xs text-slate-400"></i>
                    Admin Login
                </a>
            </div>

            <dl class="mx-auto mt-12 grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="soft-card rounded-2xl p-4 text-left">
                    <dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <i class="fas fa-calendar-days text-sky-400"></i> Events
                    </dt>
                    <dd class="mt-2 text-2xl font-bold text-slate-700 sm:text-3xl">{{ $events->count() }}</dd>
                </div>
                <div class="soft-card rounded-2xl p-4 text-left">
                    <dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <i class="fas fa-bullhorn text-amber-400"></i> Updates
                    </dt>
                    <dd class="mt-2 text-2xl font-bold text-slate-700 sm:text-3xl">{{ $announcements->count() }}</dd>
                </div>
                <div class="soft-card rounded-2xl p-4 text-left">
                    <dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <i class="fas fa-globe text-emerald-400"></i> Access
                    </dt>
                    <dd class="mt-2 text-2xl font-bold text-slate-700 sm:text-3xl">Web</dd>
                </div>
            </dl>
        </div>

        {{-- PRODUCT PREVIEW --}}
        <div class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 md:pb-16 lg:px-8">
            <div class="soft-card relative overflow-hidden rounded-[2rem] p-4 sm:p-5">
                <div class="rounded-[1.5rem] bg-[#f7f8fc] p-5">
                    <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Operations Board</p>
                            <h2 class="mt-2 text-2xl font-bold text-slate-700">Event Control</h2>
                        </div>
                        <span class="inline-flex items-center gap-2 whitespace-nowrap rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-400">
                            <i class="fas fa-eye text-[10px]"></i>
                            Sample preview
                        </span>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['label' => 'Registrations', 'value' => '248', 'bar' => 'w-3/4 bg-sky-300'],
                            ['label' => 'Payments', 'value' => '92%', 'bar' => 'w-[92%] bg-emerald-300'],
                            ['label' => 'Check-ins', 'value' => '36', 'bar' => 'w-1/4 bg-indigo-300'],
                        ] as $metric)
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-semibold text-slate-400">{{ $metric['label'] }}</p>
                                <p class="mt-2 text-2xl font-bold text-slate-700">{{ $metric['value'] }}</p>
                                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full {{ $metric['bar'] }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach (['Event details verified', 'Categories open for registration', 'Announcement queued for mobile', 'Results workspace ready'] as $index => $item)
                            <div class="flex items-center gap-3 rounded-2xl bg-white p-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $index === 2 ? 'bg-amber-50 text-amber-500' : 'bg-emerald-50 text-emerald-500' }}">
                                    <i class="fas {{ $index === 2 ? 'fa-bell' : 'fa-check' }} text-xs"></i>
                                </span>
                                <p class="text-sm font-medium text-slate-600">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($featureCards as $card)
                        <article class="soft-card-lift rounded-3xl bg-[#f7f8fc] p-5">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $card['tone'] }}">
                                <i class="fas {{ $card['icon'] }}"></i>
                            </div>
                            <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $card['eyebrow'] }}</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-700">{{ $card['title'] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-500">{{ $card['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 md:pb-16 lg:px-8" aria-labelledby="how-it-works-title">
        <div class="mb-8 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">How it works</p>
            <h2 id="how-it-works-title" class="mt-2 text-3xl font-bold text-slate-700 sm:text-4xl">From posting to finish line</h2>
        </div>

        <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($workflowSteps as $index => $step)
                <li class="soft-card relative rounded-3xl p-6">
                    <span class="absolute right-5 top-5 text-4xl font-extrabold text-slate-100">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-400">
                        <i class="fas {{ $step['icon'] }}"></i>
                    </div>
                    <h3 class="mt-5 text-lg font-bold text-slate-700">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $step['description'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <main class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
        {{-- EVENTS --}}
        <section id="events" class="soft-card rounded-[2rem] p-5 sm:p-8" aria-labelledby="events-title">
            <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Live Schedule</p>
                    <h2 id="events-title" class="mt-2 text-3xl font-bold text-slate-700 sm:text-4xl">Upcoming Events</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Browse the latest event listings highlighted in Conquer.</p>
                </div>
                @if ($events->count() > 0)
                    <span class="inline-flex w-fit items-center gap-2 rounded-full bg-sky-50 px-4 py-2 text-xs font-bold text-sky-500">
                        <i class="fas fa-calendar-check"></i>
                        {{ $events->count() }} {{ \Illuminate\Support\Str::plural('event', $events->count()) }} listed
                    </span>
                @endif
            </div>

            <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                @forelse($events as $event)
                    <article class="soft-card-lift flex flex-col rounded-3xl bg-[#f7f8fc] p-6">
                        <div class="flex items-start gap-4">
                            {{-- Calendar-style date badge --}}
                            <div class="flex w-16 shrink-0 flex-col items-center overflow-hidden rounded-2xl bg-white text-center shadow-sm">
                                <span class="w-full bg-indigo-400 py-1 text-[10px] font-bold uppercase tracking-wider text-white">{{ $event->event_date->format('M') }}</span>
                                <span class="py-1.5 text-2xl font-extrabold leading-none text-slate-700">{{ $event->event_date->format('d') }}</span>
                                <span class="pb-1.5 text-[10px] font-semibold text-slate-400">{{ $event->event_date->format('Y') }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-500">
                                    Upcoming
                                </span>
                                <h3 class="mt-3 text-xl font-bold leading-tight text-slate-700 [overflow-wrap:anywhere]">{{ $event->title }}</h3>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3 text-sm text-slate-500">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-slate-400">
                                    <i class="fas fa-location-dot"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">Venue</p>
                                    <p class="font-semibold text-slate-700 [overflow-wrap:anywhere]">{{ $event->venue }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-slate-400">
                                    <i class="fas fa-calendar-days"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">Date</p>
                                    <p class="font-semibold text-slate-700">
                                        <time datetime="{{ $event->event_date->toDateString() }}">{{ $event->event_date->format('F d, Y') }}</time>
                                        @if ($event->event_end_date && ! $event->event_end_date->isSameDay($event->event_date))
                                            – <time datetime="{{ $event->event_end_date->toDateString() }}">{{ $event->event_end_date->format('F d, Y') }}</time>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if ($event->description)
                            <p class="mt-6 line-clamp-4 text-sm leading-7 text-slate-500">{{ $event->description }}</p>
                        @endif
                    </article>
                @empty
                    <div class="flex flex-col items-center rounded-3xl bg-[#f7f8fc] px-6 py-12 text-center lg:col-span-2 xl:col-span-3">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-300 shadow-sm">
                            <i class="fas fa-calendar-xmark text-xl"></i>
                        </span>
                        <p class="mt-4 text-base font-semibold text-slate-600">No events available right now.</p>
                        <p class="mt-1 text-sm text-slate-400">Check back soon for new race listings.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ANNOUNCEMENTS --}}
        <section id="announcements" class="mt-8 soft-card rounded-[2rem] p-5 sm:p-8" aria-labelledby="announcements-title">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Updates</p>
                <h2 id="announcements-title" class="mt-2 text-3xl font-bold text-slate-700 sm:text-4xl">Announcements</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Stay informed with organizer updates, reminders, and notices.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                @forelse($announcements as $announcement)
                    <article class="flex flex-col overflow-hidden rounded-3xl bg-[#f7f8fc]">
                        @if ($announcement->image_path)
                            <img src="{{ asset('storage/'.$announcement->image_path) }}" alt="{{ $announcement->title }}" loading="lazy" class="max-h-80 w-full bg-slate-100 object-contain">
                        @endif
                        <div class="flex items-start gap-4 p-6">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-500">
                                <i class="fas fa-bullhorn"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Announcement</p>
                                <h3 class="mt-2 text-xl font-bold text-slate-700 [overflow-wrap:anywhere] sm:text-2xl">{{ $announcement->title }}</h3>
                                <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-500 [overflow-wrap:anywhere]">{{ $announcement->content }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="flex flex-col items-center rounded-3xl bg-[#f7f8fc] px-6 py-12 text-center lg:col-span-2">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-300 shadow-sm">
                            <i class="fas fa-bell-slash text-xl"></i>
                        </span>
                        <p class="mt-4 text-base font-semibold text-slate-600">No announcements yet.</p>
                        <p class="mt-1 text-sm text-slate-400">Organizer updates will show up here.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- STEWARDSHIP & TEMPLE OF GOD --}}
        <section class="mt-8 soft-card overflow-hidden rounded-[2rem] p-5 sm:p-8" aria-labelledby="stewardship-title">
            <div class="mb-8 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">A Word of Encouragement</p>
                <h2 id="stewardship-title" class="mt-2 text-3xl font-bold text-slate-700 sm:text-4xl">Caring for the Temple, Faithful with What's Given</h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-500">
                    Every participants who trains and every organizer who serves is caring for something entrusted to them.
                    Scripture reminds us that our bodies are a temple, and that we are called to be faithful stewards
                    of the gifts, time, and resources God has given.
                </p>
            </div>

            {{-- All slides share one grid cell, so the box always fits the longest verse. --}}
            <div id="verse-carousel" class="mx-auto max-w-2xl" aria-roledescription="carousel" aria-label="Stewardship verses">
                <div class="grid" aria-live="polite">
                    @foreach ($stewardshipVerses as $index => $verse)
                        <figure
                            data-verse-slide
                            aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                            class="verse-slide col-start-1 row-start-1 flex flex-col items-center justify-center rounded-3xl bg-[#f7f8fc] p-8 text-center transition-opacity duration-700 ease-in-out {{ $index === 0 ? 'opacity-100' : 'pointer-events-none opacity-0' }}"
                        >
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-400">
                                <i class="fas fa-book-bible text-sm"></i>
                            </div>
                            <blockquote class="mt-5 max-w-xl text-base italic leading-8 text-slate-600">&ldquo;{{ $verse['text'] }}&rdquo;</blockquote>
                            <figcaption class="mt-5 text-xs font-bold uppercase tracking-wide text-indigo-400">{{ $verse['reference'] }} (KJV)</figcaption>
                        </figure>
                    @endforeach
                </div>

                <div class="mt-5 flex items-center justify-center gap-3">
                    <button type="button" data-verse-prev class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300" aria-label="Previous verse">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </button>
                    <div class="flex items-center gap-2">
                        @foreach ($stewardshipVerses as $index => $verse)
                            <button
                                type="button"
                                data-verse-dot="{{ $index }}"
                                class="h-2 rounded-full transition-all {{ $index === 0 ? 'w-6 bg-indigo-400' : 'w-2 bg-slate-200 hover:bg-slate-300' }}"
                                aria-label="Show verse {{ $index + 1 }}: {{ $verse['reference'] }}"
                                aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                            ></button>
                        @endforeach
                    </div>
                    <button type="button" data-verse-next class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300" aria-label="Next verse">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>
        </section>
    </main>

    {{-- FOOTER --}}
    <footer class="border-t border-white/60 bg-white/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-center sm:flex-row sm:px-6 sm:text-left lg:px-8">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-500">
                    <i class="fas fa-flag-checkered text-sm"></i>
                </div>
                <p class="text-sm text-slate-400">&copy; {{ date('Y') }} Conquer. Built to help stewards of events run their race well.</p>
            </div>
            <nav class="flex items-center gap-4 text-sm font-semibold text-slate-400" aria-label="Footer">
                <a href="#events" class="transition hover:text-slate-600">Events</a>
                <a href="#announcements" class="transition hover:text-slate-600">Announcements</a>
                <a href="{{ route('login') }}" class="transition hover:text-slate-600">Login</a>
            </nav>
        </div>
    </footer>

    <script>
        (function () {
            const carousel = document.getElementById('verse-carousel');
            const slides = document.querySelectorAll('[data-verse-slide]');
            const dots = document.querySelectorAll('[data-verse-dot]');

            if (!carousel || slides.length === 0) {
                return;
            }

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let current = 0;
            let timer = null;

            const showSlide = (index) => {
                current = (index + slides.length) % slides.length;

                slides.forEach((slide, i) => {
                    const active = i === current;
                    slide.classList.toggle('opacity-100', active);
                    slide.classList.toggle('opacity-0', !active);
                    slide.classList.toggle('pointer-events-none', !active);
                    slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                });

                dots.forEach((dot, i) => {
                    const active = i === current;
                    dot.classList.toggle('w-6', active);
                    dot.classList.toggle('bg-indigo-400', active);
                    dot.classList.toggle('w-2', !active);
                    dot.classList.toggle('bg-slate-200', !active);
                    dot.classList.toggle('hover:bg-slate-300', !active);
                    dot.setAttribute('aria-current', active ? 'true' : 'false');
                });
            };

            const stop = () => {
                clearInterval(timer);
                timer = null;
            };

            const start = () => {
                if (reduceMotion || timer) return;
                timer = setInterval(() => showSlide(current + 1), 6000);
            };

            const restart = () => {
                stop();
                start();
            };

            carousel.querySelector('[data-verse-prev]')?.addEventListener('click', () => { showSlide(current - 1); restart(); });
            carousel.querySelector('[data-verse-next]')?.addEventListener('click', () => { showSlide(current + 1); restart(); });
            dots.forEach((dot) => dot.addEventListener('click', () => { showSlide(Number(dot.dataset.verseDot)); restart(); }));

            // Pause while the reader is hovering or focused inside, or the tab is hidden.
            carousel.addEventListener('mouseenter', stop);
            carousel.addEventListener('mouseleave', start);
            carousel.addEventListener('focusin', stop);
            carousel.addEventListener('focusout', start);
            document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));

            start();
        })();
    </script>
</body>
</html>
