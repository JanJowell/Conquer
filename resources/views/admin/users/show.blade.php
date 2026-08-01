@extends('admin.layouts.app')

@section('title', 'User Details')

@section('content')
@php
    $statusLabel = $user->banned_at
        ? ['label' => 'Banned', 'classes' => 'bg-red-100 text-red-800']
        : ($user->suspended_at
            ? ['label' => 'Suspended', 'classes' => 'bg-yellow-100 text-yellow-800']
            : ($user->isMobileActive()
                ? ['label' => 'Active', 'classes' => 'bg-green-100 text-green-800']
                : ['label' => 'Inactive', 'classes' => 'bg-gray-100 text-gray-800']));
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">User Account</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">{{ $user->name }}</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Review account details, access status, event activity, and recent admin logs.</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
            Back to users
        </a>
        @if(auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Edit user
            </a>
        @endif
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_360px]">
    <div class="space-y-6">
        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#111827] text-xl font-semibold text-white">
                    {{ $user->initials() ?: strtoupper(substr($user->name, 0, 2)) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold tracking-tight text-[#111827]">{{ $user->name }}</h2>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusLabel['classes'] }}">
                            {{ $statusLabel['label'] }}
                        </span>
                        <span class="inline-flex rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-semibold text-[#315fa8]">
                            {{ $user->roleLabel() }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Email</p>
                            <p class="mt-2 text-sm text-[#202733]">{{ $user->email }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Phone</p>
                            <p class="mt-2 text-sm text-[#202733]">{{ $user->phone ?: 'Not provided' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Joined</p>
                            <p class="mt-2 text-sm text-[#202733]">{{ $user->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Email Verification</p>
                            <p class="mt-2 text-sm text-[#202733]">{{ $user->email_verified_at?->format('F d, Y h:i A') ?: 'Not verified' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Profile Details</p>
                <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Personal information</h2>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Gender</p>
                    <p class="mt-2 text-sm leading-6 text-[#202733]">{{ $user->gender ? str($user->gender)->title() : 'No gender on file.' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Birthdate</p>
                    <p class="mt-2 text-sm leading-6 text-[#202733]">{{ $user->birthdate?->format('F d, Y') ?: 'No birthdate on file.' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Address</p>
                    <p class="mt-2 text-sm leading-6 text-[#202733]">{{ $user->address ?: 'No address on file.' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Emergency Contact</p>
                    <p class="mt-2 text-sm leading-6 text-[#202733]">
                        {{ $user->emergency_contact ?: trim(collect([$user->emergency_contact_name, $user->emergency_contact_number])->filter()->implode(' - ')) ?: 'No emergency contact on file.' }}
                    </p>
                </div>
                @if ($user->normalizedRole() === \App\Models\User::ROLE_RUNNER)
                    <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4 md:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Medical Conditions</p>
                        <p class="mt-2 text-sm leading-6 text-[#202733]">{{ $user->medical_conditions ?: 'No medical conditions recorded.' }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Participation</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Event registrations</h2>
                </div>
                <span class="rounded-full bg-[#f4f6f8] px-3 py-1 text-xs font-semibold text-[#5e6878]">
                    {{ $user->registrations->count() }} total
                </span>
            </div>

            <div class="space-y-3">
                @forelse($user->registrations as $registration)
                    <div class="rounded-2xl border border-[#eef1f4] p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#111827]">{{ $registration->event->title ?? 'Event unavailable' }}</p>
                                <p class="mt-1 text-sm text-[#6d7685]">
                                    Registered {{ $registration->registered_at?->format('F d, Y h:i A') ?: ($registration->created_at?->format('F d, Y h:i A') ?: 'N/A') }}
                                </p>
                            </div>
                            <span class="rounded-full bg-[#f8f9fb] px-3 py-1 text-xs font-medium text-[#5e6878]">
                                {{ ucfirst($registration->status ?? 'pending') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] p-5 text-sm text-[#6d7685]">
                        No event registrations found for this user.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Performance</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Race results</h2>
                </div>
                <span class="rounded-full bg-[#f4f6f8] px-3 py-1 text-xs font-semibold text-[#5e6878]">
                    {{ $user->raceResults->count() }} recorded
                </span>
            </div>

            <div class="space-y-3">
                @forelse($user->raceResults as $result)
                    <div class="rounded-2xl border border-[#eef1f4] p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#111827]">{{ $result->event->title ?? 'Event unavailable' }}</p>
                                <p class="mt-1 text-sm text-[#6d7685]">
                                    Result recorded {{ $result->created_at?->format('F d, Y h:i A') ?: 'N/A' }}
                                </p>
                            </div>
                            <span class="rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-medium text-[#315fa8]">
                                {{ $result->rank_overall ? 'Overall #'.$result->rank_overall : ($result->rank_category ? 'Category #'.$result->rank_category : 'Completed') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] p-5 text-sm text-[#6d7685]">
                        No race results recorded for this user.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Account Snapshot</p>
                <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Status overview</h2>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Current Status</p>
                    <p class="mt-2 text-sm font-medium text-[#202733]">{{ $statusLabel['label'] }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Suspended At</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $user->suspended_at?->format('F d, Y h:i A') ?: 'Not suspended' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Banned At</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $user->banned_at?->format('F d, Y h:i A') ?: 'Not banned' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Last Login</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $user->last_login_at?->format('F d, Y h:i A') ?: 'No login recorded' }}</p>
                    <p class="mt-1 text-xs text-[#7a8392]">{{ $user->last_login_ip ?: 'No IP recorded' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8392]">Recent Activity</p>
                <h2 class="mt-2 text-xl font-semibold tracking-tight text-[#111827]">Admin logs</h2>
            </div>

            <div class="space-y-3">
                @forelse($activities as $activity)
                    <div class="rounded-2xl border border-[#eef1f4] p-4">
                        <p class="text-sm font-medium text-[#202733]">{{ $activity->action }}</p>
                        <p class="mt-1 text-xs text-[#7a8392]">{{ $activity->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                        <p class="mt-1 text-xs text-[#7a8392]">{{ $activity->ip_address ?: 'No IP recorded' }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#d9dee7] bg-[#fbfcfd] p-5 text-sm text-[#6d7685]">
                        No recent admin activity logs found for this user.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
