<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Racetech</title>
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
                    <p class="text-2xl font-bold tracking-tight text-[#111827]">Racetech</p>
                    <p class="mt-1 text-sm text-[#6d7685]">Admin operations access</p>
                </div>
            </a>
        </header>

        <main class="flex flex-1 items-center">
            <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_460px]">
                <section class="self-center py-8 lg:pr-10">
                    <span class="inline-flex rounded-full border border-[#bfd1f8] bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-[#315fa8] shadow-sm">
                        Web Admin Access
                    </span>
                    <h1 class="mt-6 max-w-3xl text-5xl font-semibold leading-[1.03] tracking-tight text-[#111827] sm:text-6xl">
                        Sign in to manage events, users, analytics, and platform operations.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-[#556070]">
                        This web portal is reserved for administrators. Participant access is intended for the mobile application experience.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Admin Use</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Dashboard and Control</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">Access internal tools for users, events, notifications, and analytics.</p>
                        </div>
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Participants</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Mobile App Flow</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">Runner sign-in is planned for mobile, keeping the web app focused on back-office work.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-lg sm:p-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Admin Login</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">Welcome back</h2>
                        <p class="mt-2 text-sm leading-6 text-[#6d7685]">Use your admin credentials to continue into Racetech.</p>
                    </div>

                    @if (session('status'))
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-[#111827]">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                placeholder="admin@racetech.com"
                                required
                                autofocus
                                autocomplete="email"
                            >
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-medium text-[#111827]">Password</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">
                                        Forgot password?
                                    </a>
                                @endif
                            </div>

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 pr-12 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                >
                                <button
                                    type="button"
                                    onclick="togglePassword('password')"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-[#7d8694]"
                                >
                                    <i class="fas fa-eye" id="password-toggle"></i>
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-3 text-sm text-[#556070]">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-[#cfd5de] text-[#111827] focus:ring-[#d9dee7]" {{ old('remember') ? 'checked' : '' }}>
                            <span>Remember me</span>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-[#111827] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]"
                            data-test="login-button"
                        >
                            Sign In
                        </button>
                    </form>

                    <div class="mt-6 rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 py-4 text-sm leading-6 text-[#5a6473]">
                        Participant accounts are not allowed to access the web portal. Mobile access can be connected later without changing your runner data.
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(inputId + '-toggle');

            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
