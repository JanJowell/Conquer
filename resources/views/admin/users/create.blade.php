@extends('admin.layouts.app')

@section('title', 'Create User')

@section('content')
@php
    $roleLabels = \App\Models\User::roleLabels();
    $runnerRole = \App\Models\User::ROLE_RUNNER;
@endphp
<div class="relative min-h-screen overflow-hidden bg-[#eaf2f9] px-4 py-6 sm:px-6 lg:px-8">
    {{-- Background blobs --}}
    <div class="pointer-events-none absolute -top-24 left-8 h-72 w-72 rounded-full bg-sky-300/35 blur-3xl"></div>
    <div class="pointer-events-none absolute top-32 right-0 h-96 w-96 rounded-full bg-cyan-300/25 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-indigo-300/20 blur-3xl"></div>

    <div class="relative mx-auto max-w-6xl space-y-6">
        {{-- Header --}}
        <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-white/35 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.10)] backdrop-blur-2xl">
            <div class="rounded-[1.6rem] border border-white/60 bg-white/30 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.28em] text-sky-700">Admin Panel</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Create New User</h1>
                        <p class="mt-2 text-sm text-slate-600">
                            Add a new user account and complete their profile details.
                        </p>
                    </div>

                    <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)]"></span>
                        User Creation Form
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Card --}}
        <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="space-y-8 p-5 sm:p-6 lg:p-8">
                    {{-- Basic Information --}}
                    <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-sm backdrop-blur-xl">
                        <div class="mb-5">
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-sky-700">Section 01</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-950">Basic Information</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Set the account credentials and essential user details.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-semibold text-slate-700">Name</label>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    required
                                    value="{{ old('name') }}"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('name')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    required
                                    value="{{ old('email') }}"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('email')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    required
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('password')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">Confirm Password</label>
                                <input
                                    type="password"
                                    name="password_confirmation"
                                    id="password_confirmation"
                                    required
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                            </div>

                            <div>
                                <label for="role" class="block text-sm font-semibold text-slate-700">Role</label>
                                <select
                                    name="role"
                                    id="role"
                                    required
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                    @foreach (\App\Models\User::manageableRoles() as $role)
                                        <option value="{{ $role }}" {{ old('role') == $role ? 'selected' : '' }}>
                                            {{ $roleLabels[$role] ?? str($role)->replace('_', ' ')->title() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-semibold text-slate-700">Phone</label>
                                <input
                                    type="text"
                                    name="phone"
                                    id="phone"
                                    value="{{ old('phone') }}"
                                    inputmode="numeric"
                                    pattern="[0-9]{11}"
                                    maxlength="11"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('phone')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-semibold text-slate-700">Gender</label>
                                <select
                                    name="gender"
                                    id="gender"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                    <option value="">Select gender</option>
                                    <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="other" {{ old('gender') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('gender')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="birthdate" class="block text-sm font-semibold text-slate-700">Birthdate</label>
                                <input
                                    type="date"
                                    name="birthdate"
                                    id="birthdate"
                                    value="{{ old('birthdate') }}"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('birthdate')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- Personal Information --}}
                    <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-sm backdrop-blur-xl">
                        <div class="mb-5">
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-sky-700">Section 02</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-950">Personal Information</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Add address, health, and emergency details for the user profile.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-semibold text-slate-700">Address</label>
                                <textarea
                                    name="address"
                                    id="address"
                                    rows="3"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >{{ old('address') }}</textarea>
                                @error('address')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2 {{ old('role') === $runnerRole ? '' : 'hidden' }}" id="medical-conditions-group">
                                <label for="medical_conditions" class="block text-sm font-semibold text-slate-700">Medical Conditions</label>
                                <textarea
                                    name="medical_conditions"
                                    id="medical_conditions"
                                    rows="3"
                                    placeholder="Any medical conditions or allergies organizers should know about"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >{{ old('medical_conditions') }}</textarea>
                                @error('medical_conditions')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="emergency_contact_name" class="block text-sm font-semibold text-slate-700">Emergency Contact Name</label>
                                <input
                                    type="text"
                                    name="emergency_contact_name"
                                    id="emergency_contact_name"
                                    value="{{ old('emergency_contact_name') }}"
                                    placeholder="Emergency contact full name"
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('emergency_contact_name')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="emergency_contact_number" class="block text-sm font-semibold text-slate-700">Emergency Contact Number</label>
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
                                    class="mt-2 block w-full rounded-2xl border border-white/60 bg-white/50 px-4 py-3 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                                >
                                @error('emergency_contact_number')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Footer actions --}}
                <div class="border-t border-white/50 bg-white/25 px-5 py-4 backdrop-blur-xl sm:px-6 lg:px-8">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="inline-flex items-center justify-center rounded-2xl border border-white/60 bg-white/45 px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-950/90 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800"
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
        const runnerRole = @json($runnerRole);

        if (!roleSelect || !medicalConditionsGroup || !medicalConditionsInput) {
            return;
        }

        const toggleMedicalConditions = () => {
            const isRunner = roleSelect.value === runnerRole;

            medicalConditionsGroup.classList.toggle('hidden', !isRunner);

            if (!isRunner) {
                medicalConditionsInput.value = '';
            }
        };

        roleSelect.addEventListener('change', toggleMedicalConditions);
        toggleMedicalConditions();
    })();
</script>
@endsection