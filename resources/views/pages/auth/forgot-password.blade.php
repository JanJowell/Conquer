<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Conquer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(29, 78, 216, 0.10), transparent 24%),
                radial-gradient(circle at 86% 12%, rgba(148, 163, 184, 0.18), transparent 18%),
                #f7f8fa;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-8 sm:px-6 lg:px-8">
        <header class="flex items-center justify-between gap-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-sm border border-[#cfd5de] bg-white text-[#6b7280] shadow-sm">
                    <i class="fas fa-flag-checkered text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-[#111827]">Conquer</p>
                    <p class="mt-1 text-sm text-[#6d7685]">Password recovery for admin access</p>
                </div>
            </a>
        </header>

        <main class="flex flex-1 items-center">
            <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_460px]">
                <section class="self-center py-8 lg:pr-10">
                    <span class="inline-flex rounded-full border border-[#bfd1f8] bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-[#315fa8] shadow-sm">
                        Account Recovery
                    </span>
                    <h1 class="mt-6 max-w-3xl text-5xl font-semibold leading-[1.03] tracking-tight text-[#111827] sm:text-6xl">
                        Reset your admin password without leaving the Conquer workflow.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-[#556070]">
                        Enter your admin email and we will send a secure reset link so you can get back to managing events, users, and platform operations.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Secure Access</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Email-Based Recovery</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">We send reset instructions to the email address linked to your admin account.</p>
                        </div>
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Admin Flow</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Back to Operations Fast</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">Recover access quickly and continue managing race schedules, participants, and announcements.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-lg sm:p-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Forgot Password</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">Reset your password</h2>
                        <p class="mt-2 text-sm leading-6 text-[#6d7685]">We will email you a password reset link for your admin account.</p>
                    </div>

                    @if (session('status'))
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ $errors->first('email') ?? $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.password.email') }}" class="mt-8 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-[#111827]">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                placeholder="admin@conquer.com"
                                required
                                autofocus
                                autocomplete="email"
                            >
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-[#111827] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]"
                            data-test="email-password-reset-link-button"
                        >
                            Send Reset Link
                        </button>
                    </form>

                    @if (session('reset_url'))
                        <div class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm leading-6 text-sky-800">
                            Local development is using the `log` mailer, so the reset link is shown here instead of being emailed.
                            <div class="mt-3">
                                <a href="{{ session('reset_url') }}" class="font-semibold text-sky-900 underline underline-offset-4">
                                    Open password reset link
                                </a>
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 py-4 text-sm leading-6 text-[#5a6473]">
                        If you remember your credentials, you can return to the admin sign-in page instead of requesting a new link.
                    </div>

                    <div class="mt-6 text-center">
                        <a href="{{ route('login') }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">
                            Back to login
                        </a>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
