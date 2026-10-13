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
                radial-gradient(circle at 12% 0%, rgba(129, 140, 248, 0.16), transparent 45%),
                radial-gradient(circle at 88% 8%, rgba(56, 189, 248, 0.18), transparent 50%),
                radial-gradient(circle at 50% 100%, rgba(45, 212, 191, 0.12), transparent 55%),
                linear-gradient(180deg, #eaf2fc 0%, #f4f8fd 55%, #eef4fc 100%);
            background-attachment: fixed;
            color: #334155;
            overflow-x: hidden;
        }

        .soft-card {
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(148, 163, 184, 0.12);
        }

        /* Increase contrast for small text on the light pastel background. */
        .text-slate-400 { color: #64748b !important; }
        .text-indigo-400 { color: #4f46e5 !important; }
        .bg-indigo-400 { background-color: #4f46e5 !important; }
    </style>
</head>
<body class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-30 border-b border-white/60 bg-white/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-500 sm:h-14 sm:w-14">
                    <i class="fas fa-flag-checkered text-xl"></i>
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-700 sm:text-2xl">Conquer</p>
                    <p class="mt-1 hidden text-sm text-slate-400 sm:block">Admin operations access</p>
                </div>
            </a>
        </div>
    </header>

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
        <main class="flex flex-1 items-center">
            <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_460px]">
                <section class="order-2 self-center py-4 lg:order-1 lg:py-8 lg:pr-10">
                    <span class="inline-flex rounded-full bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-indigo-400 shadow-sm">
                        Web Admin Access
                    </span>
                    <h1 class="mt-6 max-w-3xl text-4xl font-bold leading-[1.08] text-slate-700 [overflow-wrap:anywhere] sm:text-5xl lg:text-6xl lg:leading-[1.03]">
                        Sign in to manage events, users, analytics, and platform operations.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-500">
                        This web portal is reserved for administrators. Participant access is intended for the mobile application experience.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="soft-card rounded-2xl p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Admin Use</p>
                            <p class="mt-2 text-lg font-bold text-slate-700">Dashboard and Control</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Access internal tools for users, events, notifications, and analytics.</p>
                        </div>
                        <div class="soft-card rounded-2xl p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Participants</p>
                            <p class="mt-2 text-lg font-bold text-slate-700">Mobile App Flow</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Participant sign-in is available in the mobile app, keeping the web portal focused on back-office work.</p>
                        </div>
                    </div>
                </section>

                <section class="soft-card order-1 rounded-[1.75rem] p-6 sm:p-8 lg:order-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Admin Login</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-700">Welcome back</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-400">Use your admin credentials to continue into Racetech.</p>
                    </div>

                    @if (session('status'))
                        <div class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-600">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mt-6 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-500">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-600">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                placeholder="admin@racetech.com"
                                required
                                autocomplete="email"
                            >
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-medium text-slate-600">Password</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-400 hover:text-indigo-500">
                                        Forgot password?
                                    </a>
                                @endif
                            </div>

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 pr-12 text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                >
                                <button
                                    type="button"
                                    onclick="togglePassword('password')"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400"
                                >
                                    <i class="fas fa-eye" id="password-toggle"></i>
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-500">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-0 bg-slate-100 text-indigo-400 focus:ring-2 focus:ring-indigo-200" {{ old('remember') ? 'checked' : '' }}>
                            <span>Remember me</span>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-full bg-indigo-400 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500"
                            data-test="login-button"
                        >
                            Sign In
                        </button>
                    </form>

                    <div class="mt-6 rounded-2xl bg-[#f7f8fc] px-4 py-4 text-sm leading-6 text-slate-500">
                        Participant accounts are not allowed to access the web portal. Use the mobile app to access your participant account and data.
                    </div>

                    <p class="mt-6 text-center text-sm italic text-slate-500">
                        "The Lord bless you and keep you;" - Numbers 6:23
                    </p>
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
