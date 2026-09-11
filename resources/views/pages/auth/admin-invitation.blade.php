<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Admin Account - Racetech</title>
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
                        Secure Admin Invitation
                    </span>
                    <h1 class="mt-6 max-w-3xl text-5xl font-semibold leading-[1.03] tracking-tight text-[#111827] sm:text-6xl">
                        Set up your account to access Racetech administration.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-[#556070]">
                        Confirm your invitation and create a private password before managing events, participants, and platform operations.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Private Access</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Create Your Password</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">Your password is created privately and is not visible to the administrator who invited you.</p>
                        </div>
                        <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Protected Link</p>
                            <p class="mt-2 text-lg font-semibold text-[#111827]">Single-use Invitation</p>
                            <p class="mt-2 text-sm leading-6 text-[#5a6473]">For security, this invitation expires after 24 hours and cannot be reused after activation.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-lg sm:p-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Account Activation</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#111827]">Administrator invitation</h2>
                        <p class="mt-2 text-sm leading-6 text-[#6d7685]">Review your account details and create a secure password.</p>
                    </div>

                    <div class="mt-6 rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] p-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#e8eefb] text-[#315fa8]">
                                <i class="fas fa-user-shield text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-[#111827]">{{ $user->name }}</p>
                                <p class="mt-1 text-sm text-[#5a6473]">{{ $user->roleLabel() }}</p>
                                <p class="mt-1 break-all text-sm text-[#5a6473]">{{ $user->email }}</p>
                            </div>
                        </div>
                    </div>

                    @if($expired)
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800" role="alert">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-clock mt-1"></i>
                                <div>
                                    <p class="font-semibold">This invitation has expired.</p>
                                    <p class="mt-1">Ask a Super Admin to resend it from User Management. Only the newest invitation link will work.</p>
                                </div>
                            </div>
                        </div>
                    @else
                        @if ($errors->any())
                            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.invitations.accept', ['user' => $user, 'token' => $token]) }}" class="mt-8 space-y-5">
                            @csrf

                            <div>
                                <label for="password" class="mb-2 block text-sm font-medium text-[#111827]">Create password</label>
                                <div class="relative">
                                    <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                                        class="w-full rounded-xl border {{ $errors->has('password') ? 'border-rose-300' : 'border-[#d9dee7]' }} bg-white px-4 py-3 pr-12 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                        placeholder="Enter a secure password">
                                    <button type="button" onclick="togglePassword('password')"
                                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-[#7d8694]"
                                        aria-label="Show or hide password">
                                        <i class="fas fa-eye" id="password-toggle"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-[#111827]">Confirm password</label>
                                <div class="relative">
                                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                        class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 pr-12 text-[#111827] outline-none transition placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"
                                        placeholder="Enter the password again">
                                    <button type="button" onclick="togglePassword('password_confirmation')"
                                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-[#7d8694]"
                                        aria-label="Show or hide password confirmation">
                                        <i class="fas fa-eye" id="password_confirmation-toggle"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-[#d9dee7] bg-[#f8f9fb] px-4 py-4 text-sm leading-6 text-[#5a6473]">
                                <i class="fas fa-lock mr-2 text-[#7d8694]"></i>
                                Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.
                            </div>

                            <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-[#111827] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]"
                                data-test="activate-account-button">
                                Activate Account
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('login') }}" class="mt-6 inline-flex w-full items-center justify-center gap-2 text-center text-sm font-medium text-[#315fa8] hover:text-[#244c8a]">
                        <i class="fas fa-arrow-left text-xs"></i>
                        Back to sign in
                    </a>
                </section>
            </div>
        </main>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(inputId + '-toggle');

            if (!input || !toggle) {
                return;
            }

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
