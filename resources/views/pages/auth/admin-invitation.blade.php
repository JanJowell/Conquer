<x-layouts::auth :title="__('Activate administrator account')">
    <div class="mt-4 flex flex-col gap-6">
        <div class="text-center">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Administrator invitation</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ $user->name }} · {{ $user->roleLabel() }}
            </p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $user->email }}</p>
        </div>

        @if($expired)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                This invitation has expired. Ask a Super Admin to resend it from User Management.
            </div>
        @else
            @error('invitation')
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('admin.invitations.accept', ['user' => $user, 'token' => $token]) }}" class="flex flex-col gap-5">
                @csrf

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">Create password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="block w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                    @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="block w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                </div>

                <p class="text-xs leading-5 text-zinc-500">Use at least 12 characters with uppercase, lowercase, number, and symbol.</p>

                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Activate account
                </button>
            </form>
        @endif

        <a href="{{ route('login') }}" class="text-center text-sm font-medium text-sky-700 hover:text-sky-800">Back to sign in</a>
    </div>
</x-layouts::auth>
