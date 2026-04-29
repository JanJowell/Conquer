@extends('admin.layouts.app')

@section('title', 'Edit User')

@section('content')
@php
    $roleLabels = \App\Models\User::roleLabels();
    $runnerRole = \App\Models\User::ROLE_RUNNER;
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">User Management</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Edit User</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Update account access, contact details, and profile information for {{ $user->name }}.</p>
    </div>

    <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
        View profile
    </a>
</div>

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="space-y-8 p-6">
            <section>
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Basic Information</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Account details</h2>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium text-[#111827]">Name</label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $user->name) }}"
                               class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                        @error('name')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-[#111827]">Email</label>
                        <input type="email" name="email" id="email" required value="{{ old('email', $user->email) }}"
                               class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                        @error('email')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="role" class="mb-2 block text-sm font-medium text-[#111827]">Role</label>
                        <select name="role" id="role" required
                                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                            @foreach (\App\Models\User::manageableRoles() as $role)
                                <option value="{{ $role }}" {{ old('role', $user->normalizedRole()) == $role ? 'selected' : '' }}>
                                    {{ $roleLabels[$role] ?? str($role)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="mb-2 block text-sm font-medium text-[#111827]">Phone</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}"
                               inputmode="numeric"
                               pattern="[0-9]{11}"
                               maxlength="11"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                               class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                        @error('phone')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="gender" class="mb-2 block text-sm font-medium text-[#111827]">Gender</label>
                        <select name="gender" id="gender"
                                class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                            <option value="">Select gender</option>
                            <option value="male" {{ old('gender', $user->gender) === 'male' ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ old('gender', $user->gender) === 'female' ? 'selected' : '' }}>Female</option>
                            <option value="other" {{ old('gender', $user->gender) === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('gender')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="birthdate" class="mb-2 block text-sm font-medium text-[#111827]">Birthdate</label>
                        <input type="date" name="birthdate" id="birthdate" value="{{ old('birthdate', $user->birthdate?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                        @error('birthdate')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section>
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Additional Information</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Profile and safety details</h2>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="address" class="mb-2 block text-sm font-medium text-[#111827]">Address</label>
                        <textarea name="address" id="address" rows="3"
                                  class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ old('address', $user->address) }}</textarea>
                        @error('address')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2 {{ old('role', $user->normalizedRole()) === $runnerRole ? '' : 'hidden' }}" id="medical-conditions-group">
                        <label for="medical_conditions" class="mb-2 block text-sm font-medium text-[#111827]">Medical Conditions</label>
                        <textarea name="medical_conditions" id="medical_conditions" rows="3"
                                  placeholder="Any medical conditions or allergies organizers should know about"
                                  class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">{{ old('medical_conditions', $user->medical_conditions) }}</textarea>
                        @error('medical_conditions')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="emergency_contact_name" class="mb-2 block text-sm font-medium text-[#111827]">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" id="emergency_contact_name"
                               value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}"
                               placeholder="Emergency contact full name"
                               class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                        @error('emergency_contact_name')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="emergency_contact_number" class="mb-2 block text-sm font-medium text-[#111827]">Emergency Contact Number</label>
                        <input type="text" name="emergency_contact_number" id="emergency_contact_number"
                               value="{{ old('emergency_contact_number', $user->emergency_contact_number) }}"
                               placeholder="Emergency contact phone number"
                               inputmode="numeric"
                               pattern="[0-9]{11}"
                               maxlength="11"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                               class="w-full rounded-xl border border-[#d9dee7] bg-white px-4 py-3 text-[#111827] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                        @error('emergency_contact_number')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-[#eef1f4] bg-[#fbfcfd] px-6 py-4">
            <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Save Changes
            </button>
        </div>
    </form>
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
