@extends(auth()->user()?->isAdmin() ? 'admin.layouts.app' : 'layouts.app')

@section('title', 'Profile Settings')

@section('content')
@php
    $isAdminView = auth()->user()?->isAdmin();
    $isRunner = $user->normalizedRole() === \App\Models\User::ROLE_RUNNER;
    $containerClasses = $isAdminView ? 'space-y-8' : 'max-w-5xl space-y-8';
    $titleClasses = $isAdminView ? 'text-3xl font-semibold tracking-tight text-[#111827]' : 'text-3xl font-bold text-white';
    $subtitleClasses = $isAdminView ? 'mt-2 text-sm leading-6 text-[#6d7685]' : 'mt-2 text-sm text-zinc-400';
    $cardClasses = $isAdminView
        ? 'rounded-2xl border border-[#d9dee7] bg-white p-6 shadow-sm'
        : 'rounded-2xl border border-white/10 bg-zinc-900/80 p-6 shadow-sm';
    $mutedTextClasses = $isAdminView ? 'mt-1 text-sm text-[#6d7685]' : 'mt-1 text-sm text-zinc-400';
    $labelClasses = $isAdminView ? 'mb-2 block text-sm font-medium text-[#111827]' : 'mb-2 block text-sm font-medium text-white';
    $inputClasses = $isAdminView
        ? 'w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] placeholder:text-[#9aa3af] focus:border-[#aeb7c3] focus:outline-none focus:ring-2 focus:ring-[#eef1f5]'
        : 'w-full rounded-xl border border-white/10 bg-zinc-950 px-4 py-3 text-white placeholder:text-zinc-500 focus:border-white/20 focus:outline-none';
    $primaryButtonClasses = $isAdminView
        ? 'rounded-xl bg-[#111827] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]'
        : 'rounded-xl bg-white px-5 py-3 text-sm font-semibold text-black transition hover:bg-zinc-200';
    $secondaryButtonClasses = $isAdminView
        ? 'mt-4 w-full rounded-xl border border-[#d9dee7] bg-[#111827] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1f2937]'
        : 'mt-4 w-full rounded-xl border border-white/10 bg-zinc-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800';
    $avatarBoxClasses = $isAdminView
        ? 'flex h-24 w-24 items-center justify-center rounded-2xl border border-[#d9dee7] bg-[#f5f6f8] text-2xl font-bold text-[#606978]'
        : 'flex h-24 w-24 items-center justify-center rounded-2xl bg-zinc-800 text-2xl font-bold text-white';
    $bannerClasses = $isAdminView
        ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700'
        : 'rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400';
    $passwordBannerClasses = $isAdminView
        ? 'rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700'
        : 'rounded-xl border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm text-sky-400';
    $errorClasses = $isAdminView ? 'mt-2 text-sm text-red-600' : 'mt-2 text-sm text-red-400';
    $fileInputClasses = $isAdminView
        ? 'block w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-sm text-[#4f5968] file:mr-4 file:rounded-lg file:border-0 file:bg-[#111827] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[#1f2937]'
        : 'block w-full rounded-xl border border-white/10 bg-zinc-950 px-4 py-3 text-sm text-zinc-300 file:mr-4 file:rounded-lg file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-black hover:file:bg-zinc-200';
@endphp

<div class="{{ $containerClasses }}">
    <div>
        <h1 class="{{ $titleClasses }}">Profile Settings</h1>
        <p class="{{ $subtitleClasses }}">
            Manage your account information, password, and profile photo.
        </p>
    </div>

    @if (session('status') === 'profile-updated')
        <div class="{{ $bannerClasses }}">
            Profile updated successfully.
        </div>
    @endif

    @if (session('status') === 'password-updated')
        <div class="{{ $passwordBannerClasses }}">
            Password updated successfully.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-1">
            <div class="{{ $cardClasses }}">
                <h2 class="{{ $isAdminView ? 'text-xl font-semibold tracking-tight text-[#111827]' : 'text-lg font-semibold text-white' }}">Profile Photo</h2>
                <p class="{{ $mutedTextClasses }}">
                    Upload and preview your profile avatar.
                </p>

                <div class="mt-6 flex flex-col items-center text-center">
                    @if ($user->avatar_path)
                        <img
                            src="{{ asset('storage/'.$user->avatar_path) }}"
                            alt="{{ $user->name }}"
                            class="h-24 w-24 rounded-2xl border {{ $isAdminView ? 'border-[#d9dee7]' : 'border-white/10' }} object-cover"
                        >
                    @else
                        <div class="{{ $avatarBoxClasses }}">
                            {{ auth()->user() ? strtoupper(substr(auth()->user()->name, 0, 2)) : 'RA' }}
                        </div>
                    @endif

                    <p class="{{ $isAdminView ? 'mt-4 text-lg font-semibold text-[#111827]' : 'mt-4 text-base font-semibold text-white' }}">
                        {{ $user->name }}
                    </p>
                    <p class="{{ $isAdminView ? 'text-sm text-[#6d7685]' : 'text-sm text-zinc-400' }}">
                        {{ $user->email }}
                    </p>

                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="mt-6 w-full">
                        @csrf

                        <label for="avatar" class="{{ $labelClasses }}">
                            Upload Avatar
                        </label>

                        <input
                            id="avatar"
                            type="file"
                            name="avatar"
                            accept="image/*"
                            class="{{ $fileInputClasses }}"
                        >

                        @error('avatar')
                            <p class="{{ $errorClasses }}">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="{{ $secondaryButtonClasses }}">
                            Upload Photo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="space-y-6 xl:col-span-2">
            <div class="{{ $cardClasses }}">
                <div class="mb-5">
                    <h2 class="{{ $isAdminView ? 'text-xl font-semibold tracking-tight text-[#111827]' : 'text-lg font-semibold text-white' }}">Account Information</h2>
                    <p class="{{ $mutedTextClasses }}">
                        Update your personal and contact information.
                    </p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="name" class="{{ $labelClasses }}">Name</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                class="{{ $inputClasses }}"
                            >
                            @error('name')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="{{ $labelClasses }}">Email</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email', $user->email) }}"
                                class="{{ $inputClasses }}"
                            >
                            @error('email')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="{{ $labelClasses }}">Phone</label>
                            <input
                                id="phone"
                                type="text"
                                name="phone"
                                value="{{ old('phone', $user->phone) }}"
                                inputmode="numeric"
                                pattern="[0-9]{11}"
                                maxlength="11"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                class="{{ $inputClasses }}"
                            >
                            @error('phone')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="gender" class="{{ $labelClasses }}">Gender</label>
                            <select id="gender" name="gender" class="{{ $inputClasses }}">
                                <option value="">Select gender</option>
                                <option value="male" {{ old('gender', $user->gender) === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender', $user->gender) === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('gender', $user->gender) === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('gender')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="birthdate" class="{{ $labelClasses }}">Birthdate</label>
                            <input
                                id="birthdate"
                                type="date"
                                name="birthdate"
                                value="{{ old('birthdate', $user->birthdate?->format('Y-m-d')) }}"
                                class="{{ $inputClasses }}"
                            >
                            @error('birthdate')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="{{ $labelClasses }}">Address</label>
                            <textarea
                                id="address"
                                name="address"
                                rows="3"
                                class="{{ $inputClasses }}"
                            >{{ old('address', $user->address) }}</textarea>
                            @error('address')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="emergency_contact_name" class="{{ $labelClasses }}">Emergency Contact Name</label>
                            <input
                                id="emergency_contact_name"
                                type="text"
                                name="emergency_contact_name"
                                value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}"
                                class="{{ $inputClasses }}"
                            >
                            @error('emergency_contact_name')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="emergency_contact_number" class="{{ $labelClasses }}">Emergency Contact Number</label>
                            <input
                                id="emergency_contact_number"
                                type="text"
                                name="emergency_contact_number"
                                value="{{ old('emergency_contact_number', $user->emergency_contact_number) }}"
                                inputmode="numeric"
                                pattern="[0-9]{11}"
                                maxlength="11"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                class="{{ $inputClasses }}"
                            >
                            @error('emergency_contact_number')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($isRunner)
                            <div class="md:col-span-2">
                                <label for="medical_conditions" class="{{ $labelClasses }}">Medical Conditions</label>
                                <textarea
                                    id="medical_conditions"
                                    name="medical_conditions"
                                    rows="3"
                                    class="{{ $inputClasses }}"
                                >{{ old('medical_conditions', $user->medical_conditions) }}</textarea>
                                @error('medical_conditions')
                                    <p class="{{ $errorClasses }}">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="{{ $primaryButtonClasses }}">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div class="{{ $cardClasses }}">
                <div class="mb-5">
                    <h2 class="{{ $isAdminView ? 'text-xl font-semibold tracking-tight text-[#111827]' : 'text-lg font-semibold text-white' }}">Change Password</h2>
                    <p class="{{ $mutedTextClasses }}">
                        Make sure your account uses a strong password.
                    </p>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="current_password" class="{{ $labelClasses }}">Current Password</label>
                        <input
                            id="current_password"
                            type="password"
                            name="current_password"
                            class="{{ $inputClasses }}"
                        >
                        @error('current_password')
                            <p class="{{ $errorClasses }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="{{ $labelClasses }}">New Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="{{ $inputClasses }}"
                        >
                        @error('password')
                            <p class="{{ $errorClasses }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="{{ $labelClasses }}">Confirm New Password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            class="{{ $inputClasses }}"
                        >
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="{{ $isAdminView ? $primaryButtonClasses : $secondaryButtonClasses }}">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
