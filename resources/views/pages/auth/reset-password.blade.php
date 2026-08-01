<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Racetech</title>
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
                    <p class="mt-1 text-sm text-[#6d7685]">Secure password reset for admin access</p>
                </div>
            </a>
        </header>

        <main class="flex flex-1 items-center">
            <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_460px]">
                <section class="self-center py-8 lg:pr-10">
                    <span class="inline-flex rounded-full border border-[#bfd1f8] bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-[#315fa8] shadow-sm">
                        Password Reset
                    </span>
                    <h1 class="mt-6 max-w-3xl text-5xl font-semibold leading-[1.03] tracking-tight text-[#111827] sm:text-6xl">
                        Set a new admin password and return to Racetech securely.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-[#556070]">
                        Use a strong password for your admin account so you can get back to managing events, users, announcements, and operations with confidence.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Reset Security</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Token-Protected Access</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">This reset session is tied to your email and secure reset token.</p>
                        </div>
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Account Safety</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Use a Strong Password</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">Choose a password with at least 8 characters and confirm it before saving.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-lg sm:p-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Reset Password</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">Create your new password</h2>
                        <p class="mt-2 text-sm leading-6 text-[#6d7685]">Enter and confirm a new password for your admin account.</p>
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

                    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
                        @csrf
                        <input type="hidden" name="token" value="{{ request()->route('token') }}">

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-[#111827]">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ request('email') }}"
                                class="w-full rounded-xl border border-[#d9dee7] bg-[#f8f9fb] px-4 py-3 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                required
                                autocomplete="email"
                                readonly
                            >
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-medium text-[#111827]">New Password</label>
                                <span class="text-xs text-[#7a8392]">Minimum 8 characters</span>
                            </div>

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 pr-12 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                    placeholder="Enter your new password"
                                    required
                                    autocomplete="new-password"
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

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-[#111827]">Confirm New Password</label>

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 pr-12 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                    placeholder="Confirm your new password"
                                    required
                                    autocomplete="new-password"
                                >
                                <button
                                    type="button"
                                    onclick="togglePassword('password_confirmation')"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-[#7d8694]"
                                >
                                    <i class="fas fa-eye" id="password_confirmation-toggle"></i>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-[#111827] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]"
                            data-test="reset-password-button"
                        >
                            Reset Password
                        </button>
                    </form>

                    <div class="mt-6 rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 py-4 text-sm leading-6 text-[#5a6473]">
                        After resetting your password, use your updated credentials to sign back in to the admin portal.
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
