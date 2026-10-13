@extends('admin.layouts.app')

@section('title', 'Create User')

@section('content')
@php
    $roleLabels = \App\Models\User::roleLabels();
    $runnerRole = \App\Models\User::ROLE_RUNNER;
@endphp
<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Header --}}
        <div class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)] sm:p-7">
            <div class="rounded-[1.5rem] bg-white px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Admin Panel</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-700">Create New User</h1>
                        <p class="mt-2 text-sm text-slate-500">
                            Add a new user account and complete their profile details.
                        </p>
                    </div>

                    <div class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-400">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        User Creation Form
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Card --}}
        <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="space-y-8 p-5 sm:p-6 lg:p-8">
                    {{-- Basic Information --}}
                    <section class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                        <div class="mb-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Section 01</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-700">Basic Information</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Set the account credentials and essential user details.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="name" class="block mb-1.5 text-sm font-medium text-slate-600">Name</label>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    required
                                    value="{{ old('name') }}"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                @error('name')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block mb-1.5 text-sm font-medium text-slate-600">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    required
                                    value="{{ old('email') }}"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                @error('email')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="admin-invitation-notice" class="hidden md:col-span-2 rounded-2xl bg-indigo-50 p-4 text-sm leading-6 text-indigo-500">
                                Administrator accounts do not use a password chosen here. A secure 24-hour invitation will be emailed so the staff member can verify their address and create a private password.
                            </div>

                            <div id="password-group">
                                <label for="password" class="block mb-1.5 text-sm font-medium text-slate-600">Password</label>
                                <div class="relative">
                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        required
                                        autocomplete="new-password"
                                        class="block w-full rounded-2xl border-0 bg-slate-50 py-2.5 pl-4 pr-12 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300"
                                    >
                                    <button
                                        type="button"
                                        data-password-toggle="password"
                                        aria-controls="password"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                        title="Show password"
                                        class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center rounded-r-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-300"
                                    >
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        <span class="sr-only" data-password-toggle-label>Show password</span>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="password-confirmation-group">
                                <label for="password_confirmation" class="block mb-1.5 text-sm font-medium text-slate-600">Confirm Password</label>
                                <div class="relative">
                                    <input
                                        type="password"
                                        name="password_confirmation"
                                        id="password_confirmation"
                                        required
                                        autocomplete="new-password"
                                        class="block w-full rounded-2xl border-0 bg-slate-50 py-2.5 pl-4 pr-12 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300"
                                    >
                                    <button
                                        type="button"
                                        data-password-toggle="password_confirmation"
                                        aria-controls="password_confirmation"
                                        aria-label="Show password confirmation"
                                        aria-pressed="false"
                                        title="Show password confirmation"
                                        class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center rounded-r-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-300"
                                    >
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        <span class="sr-only" data-password-toggle-label>Show password confirmation</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label for="role" class="block mb-1.5 text-sm font-medium text-slate-600">Role</label>
                                <select
                                    name="role"
                                    id="role"
                                    required
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                    @foreach (\App\Models\User::manageableRoles() as $role)
                                        <option value="{{ $role }}" {{ old('role') == $role ? 'selected' : '' }}>
                                            {{ $roleLabels[$role] ?? str($role)->replace('_', ' ')->title() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block mb-1.5 text-sm font-medium text-slate-600">Phone</label>
                                <input
                                    type="text"
                                    name="phone"
                                    id="phone"
                                    value="{{ old('phone') }}"
                                    inputmode="numeric"
                                    pattern="[0-9]{11}"
                                    maxlength="11"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                @error('phone')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block mb-1.5 text-sm font-medium text-slate-600">Gender</label>
                                <select
                                    name="gender"
                                    id="gender"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                    <option value="">Select gender</option>
                                    <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="other" {{ old('gender') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('gender')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="birthdate" class="block mb-1.5 text-sm font-medium text-slate-600">Birthdate</label>
                                <input
                                    type="date"
                                    name="birthdate"
                                    id="birthdate"
                                    value="{{ old('birthdate') }}"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                @error('birthdate')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- Personal Information --}}
                    <section class="rounded-[1.5rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                        <div class="mb-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Section 02</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-700">Personal Information</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Add address, health, and emergency details for the user profile.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="address" class="block mb-1.5 text-sm font-medium text-slate-600">Address</label>
                                <textarea
                                    name="address"
                                    id="address"
                                    rows="3"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >{{ old('address') }}</textarea>
                                @error('address')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2 {{ old('role') === $runnerRole ? '' : 'hidden' }}" id="medical-conditions-group">
                                <label for="medical_conditions" class="block mb-1.5 text-sm font-medium text-slate-600">Medical Conditions</label>
                                <textarea
                                    name="medical_conditions"
                                    id="medical_conditions"
                                    rows="3"
                                    placeholder="Any medical conditions or allergies organizers should know about"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >{{ old('medical_conditions') }}</textarea>
                                @error('medical_conditions')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="emergency_contact_name" class="block mb-1.5 text-sm font-medium text-slate-600">Emergency Contact Name</label>
                                <input
                                    type="text"
                                    name="emergency_contact_name"
                                    id="emergency_contact_name"
                                    value="{{ old('emergency_contact_name') }}"
                                    placeholder="Emergency contact full name"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                @error('emergency_contact_name')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="emergency_contact_number" class="block mb-1.5 text-sm font-medium text-slate-600">Emergency Contact Number</label>
                                <input
                                    type="text"
                                    name="emergency_contact_number"
                                    id="emergency_contact_number"
                                    value="{{ old('emergency_contact_number') }}"
                                    placeholder="Emergency contact phone number"
                                    inputmode="numeric"
                                    pattern="[0-9]{11}"
                                    maxlength="11"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    class="block w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none transition focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-300 placeholder:text-slate-300"
                                >
                                @error('emergency_contact_number')
                                    <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Footer actions --}}
                <div class="border-t border-slate-50 bg-white px-5 py-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="inline-flex items-center justify-center rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
                        >
                            Create User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const roleSelect = document.getElementById('role');
        const medicalConditionsGroup = document.getElementById('medical-conditions-group');
        const medicalConditionsInput = document.getElementById('medical_conditions');
        const passwordGroup = document.getElementById('password-group');
        const passwordConfirmationGroup = document.getElementById('password-confirmation-group');
        const passwordInput = document.getElementById('password');
        const passwordConfirmationInput = document.getElementById('password_confirmation');
        const passwordToggleButtons = Array.from(document.querySelectorAll('[data-password-toggle]'));
        const invitationNotice = document.getElementById('admin-invitation-notice');
        const runnerRole = @json($runnerRole);

        if (!roleSelect || !medicalConditionsGroup || !medicalConditionsInput) {
            return;
        }

        const setPasswordVisibility = (button, input, visible) => {
            const fieldName = input.id === 'password_confirmation' ? 'password confirmation' : 'password';
            const actionLabel = `${visible ? 'Hide' : 'Show'} ${fieldName}`;
            const icon = button.querySelector('i');

            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', visible ? 'true' : 'false');
            button.setAttribute('aria-label', actionLabel);
            button.setAttribute('title', actionLabel);

            const accessibleLabel = button.querySelector('[data-password-toggle-label]');
            if (accessibleLabel) {
                accessibleLabel.textContent = actionLabel;
            }

            if (icon) {
                icon.classList.toggle('fa-eye', !visible);
                icon.classList.toggle('fa-eye-slash', visible);
            }
        };

        passwordToggleButtons.forEach((button) => {
            const input = document.getElementById(button.dataset.passwordToggle);

            if (!input) {
                return;
            }

            button.addEventListener('click', () => {
                setPasswordVisibility(button, input, input.type === 'password');
            });
        });

        const toggleMedicalConditions = () => {
            const isRunner = roleSelect.value === runnerRole;

            medicalConditionsGroup.classList.toggle('hidden', !isRunner);
            passwordGroup?.classList.toggle('hidden', !isRunner);
            passwordConfirmationGroup?.classList.toggle('hidden', !isRunner);
            invitationNotice?.classList.toggle('hidden', isRunner);

            if (passwordInput) {
                passwordInput.required = isRunner;
            }

            if (passwordConfirmationInput) {
                passwordConfirmationInput.required = isRunner;
            }

            if (!isRunner) {
                medicalConditionsInput.value = '';
                if (passwordInput) passwordInput.value = '';
                if (passwordConfirmationInput) passwordConfirmationInput.value = '';

                passwordToggleButtons.forEach((button) => {
                    const input = document.getElementById(button.dataset.passwordToggle);
                    if (input) setPasswordVisibility(button, input, false);
                });
            }
        };

        roleSelect.addEventListener('change', toggleMedicalConditions);
        toggleMedicalConditions();
    })();
</script>
@endsection
