@extends('admin.layouts.app')

@section('title', auth()->user()->isSuperAdmin() ? 'User Management' : 'Users Overview')

@section('content')
<style>
    .users-pagination nav > div:first-child {
        color: #64748b;
    }

    .users-pagination nav > div:last-child {
        align-items: center;
        gap: 0.75rem;
    }

    .users-pagination nav p {
        color: #64748b;
        font-size: 0.875rem;
    }

    .users-pagination nav span[aria-current="page"] span,
    .users-pagination nav a,
    .users-pagination nav span[aria-disabled="true"] span {
        border-radius: 9999px !important;
        border: 0 !important;
        background: #f8fafc !important;
        color: #64748b !important;
        box-shadow: none !important;
        backdrop-filter: none !important;
        transition: background 160ms ease, color 160ms ease, transform 160ms ease;
    }

    .users-pagination nav span[aria-current="page"] span {
        background: #818cf8 !important;
        color: #ffffff !important;
    }

    .users-pagination nav a:hover {
        background: #f1f5f9 !important;
        color: #475569 !important;
        transform: translateY(-1px);
    }

    .users-pagination nav span[aria-disabled="true"] span {
        color: #cbd5e1 !important;
        opacity: 0.8;
    }
</style>

@php
    $roleLabels = \App\Models\User::roleLabels();
    $canManageUsers = auth()->user()->isSuperAdmin();
    $runnerRole = \App\Models\User::ROLE_RUNNER;
@endphp

<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    {{-- Background glass blobs --}}

    <div class="mx-auto max-w-[1400px] space-y-6">
        {{-- Header --}}
        <div class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="rounded-[1.5rem] bg-white px-6 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-400">
                            <span class="h-2 w-2 rounded-full bg-indigo-400"></span>
                            People
                        </div>

                        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-700 sm:text-4xl">
                            {{ $canManageUsers ? 'User Management' : 'Users Overview' }}
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ $canManageUsers ? 'Manage platform accounts, access roles, and account restrictions.' : 'Review account activity, roles, and participant status across the platform.' }}
                        </p>
                    </div>

                    @if($canManageUsers)
                        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-indigo-400 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                            <i class="fas fa-user-plus mr-2 text-xs"></i>
                            Add New User
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="grid gap-3 rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] lg:grid-cols-[minmax(240px,1fr)_220px_180px_auto_auto] lg:items-end">
            <div>
                <label for="search" class="mb-1.5 block text-sm font-medium text-slate-600">Search</label>
                <input
                    id="search"
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Name or email"
                    class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div>
                <label for="role" class="mb-1.5 block text-sm font-medium text-slate-600">Role</label>
                <select
                    id="role"
                    name="role"
                    class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
                    <option value="">All roles</option>
                    @foreach (\App\Models\User::manageableRoles() as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>
                            {{ $roleLabels[$role] ?? str($role)->replace('_', ' ')->title() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="mb-1.5 block text-sm font-medium text-slate-600">Status</label>
                <select
                    id="status"
                    name="status"
                    class="h-11 w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
                    <option value="">All status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    <option value="pending_verification" @selected(request('status') === 'pending_verification')>Pending Admin Verification</option>
                    <option value="verified" @selected(request('status') === 'verified')>Verified Administrators</option>
                    <option value="banned" @selected(request('status') === 'banned')>Banned</option>
                </select>
            </div>

            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-indigo-400 px-5 text-sm font-semibold text-white   transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200">
                Filter
            </button>

            <a href="{{ route('admin.users.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border bg-slate-50 px-5 text-sm font-semibold text-slate-700   transition hover:-translate-y-0.5 hover:bg-white/65 focus:outline-none focus:ring-4 focus:ring-sky-100/70">
                Clear
            </a>
        </form>

        {{-- Users Table --}}
        <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="overflow-x-auto p-3">
                <table class="min-w-full border-separate border-spacing-y-0 text-left">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Joined</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="text-sm text-slate-600">
                        @forelse($users as $user)
                            <tr class="align-top transition hover:bg-[#f7f8fc]">
                                <td class="rounded-l-2xl px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-sm font-bold text-indigo-400">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <p class="font-bold text-slate-700">{{ $user->name }}</p>

                                                @if($canManageUsers && $user->isAdmin() && $user->email_verified_at === null)
                                                    @php
                                                        $invitationButtonLabel = $user->adminInvitationState() === 'not_sent' ? 'Send Invitation' : 'Resend Invitation';
                                                        $resendAvailableAt = $user->admin_invitation_sent_at?->copy()->addSeconds(
                                                            max(1, (int) config('admin_invitations.resend_cooldown_seconds', 120))
                                                        );
                                                        $resendCoolingDown = $resendAvailableAt?->isFuture() ?? false;
                                                    @endphp
                                                    <form method="POST" action="{{ route('admin.users.resend-invitation', $user) }}" class="inline-flex items-center">
                                                        @csrf
                                                        <button
                                                            type="submit"
                                                            data-invitation-resend-button
                                                            data-default-label="{{ $invitationButtonLabel }}"
                                                            data-resend-available-at="{{ $resendAvailableAt?->getTimestamp() }}"
                                                            @disabled($resendCoolingDown)
                                                            class="text-xs font-semibold text-sky-500 transition hover:text-sky-600 disabled:cursor-not-allowed disabled:text-slate-300"
                                                            title="Invitation emails can be resent once every two minutes, up to five times per hour."
                                                        >
                                                            {{ $invitationButtonLabel }}
                                                        </button>
                                                    </form>

                                                    @if($user->admin_invitation_sent_at)
                                                        <span class="text-[11px] font-medium text-slate-400">
                                                            · Last sent {{ $user->admin_invitation_sent_at->diffForHumans() }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex rounded-full rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-indigo-50 text-indigo-400' => $user->role === 'super_admin',
                                        'bg-indigo-50 text-indigo-400' => $user->role === 'executive',
                                        'bg-amber-50 text-amber-500' => $user->role === 'content_moderator',
                                        'bg-sky-50 text-sky-500' => in_array($user->role, ['event_manager', 'admin'], true),
                                        'bg-slate-100 text-slate-600' => ! in_array($user->role, ['super_admin', 'executive', 'content_moderator', 'event_manager', 'admin'], true),
                                    ])>
                                        {{ $user->roleLabel() }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    {{ $user->phone ?: '-' }}
                                </td>

                                <td class="px-6 py-4">
                                    @if($user->banned_at)
                                        <span class="inline-flex rounded-full border bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-400 ">Banned</span>
                                    @elseif($user->suspended_at)
                                        <span class="inline-flex rounded-full border bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-500 ">Suspended</span>
                                    @elseif($user->isAdmin() && $user->adminInvitationState() === 'pending')
                                        <span class="inline-flex rounded-full border bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-500 ">Pending Verification</span>
                                    @elseif($user->isAdmin() && $user->adminInvitationState() === 'expired')
                                        <span class="inline-flex rounded-full border bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-500 ">Invitation Expired</span>
                                    @elseif($user->isAdmin() && $user->adminInvitationState() === 'not_sent')
                                        <span class="inline-flex rounded-full border bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 ">Invitation Required</span>
                                    @elseif($user->isAdmin())
                                        <span class="inline-flex rounded-full border bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-500 ">Verified</span>
                                    @elseif($user->isMobileActive())
                                        <span class="inline-flex rounded-full border bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-500 ">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full border bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 ">Inactive</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>

                                <td class="rounded-r-2xl px-6 py-4 text-right">
                                    @if($canManageUsers)
                                        <div class="admin-responsive-actions admin-responsive-icon-actions flex flex-wrap justify-end gap-2">
                                            <x-admin.icon-action label="View user" icon="fa-eye" data-open-user-modal="view-user-{{ $user->id }}" />

                                            <x-admin.icon-action label="Edit user" icon="fa-pen" data-open-user-modal="edit-user-{{ $user->id }}" />

                                            @if($user->id !== auth()->id())
                                                <x-admin.icon-action label="Delete user" icon="fa-trash" tone="danger" data-open-user-modal="delete-user-{{ $user->id }}" />

                                                @if($user->banned_at)
                                                    <x-admin.icon-action label="Unban user" icon="fa-user-check" tone="success" data-open-user-modal="unban-user-{{ $user->id }}" />
                                                @else
                                                    <x-admin.icon-action label="Ban user" icon="fa-user-slash" tone="danger" data-open-user-modal="ban-user-{{ $user->id }}" />
                                                @endif

                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex h-9 items-center justify-center rounded-full bg-slate-100 px-4 text-xs font-semibold text-slate-500">
                                            Read only
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="rounded-2xl border bg-white px-6 py-12 text-center text-sm text-slate-500 ">
                                    No users found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-50 px-6 py-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>

@if($canManageUsers)
    @foreach($users as $modalUser)
        @php
            if ($modalUser->banned_at) {
                $modalStatus = ['label' => 'Banned', 'classes' => 'bg-rose-50 text-rose-400'];
            } elseif ($modalUser->suspended_at) {
                $modalStatus = ['label' => 'Suspended', 'classes' => 'bg-amber-50 text-amber-500'];
            } elseif ($modalUser->isAdmin()) {
                $modalStatus = match ($modalUser->adminInvitationState()) {
                    'verified' => ['label' => 'Verified', 'classes' => 'bg-emerald-50 text-emerald-500'],
                    'pending' => ['label' => 'Pending Verification', 'classes' => 'bg-sky-50 text-sky-500'],
                    'expired' => ['label' => 'Invitation Expired', 'classes' => 'bg-amber-50 text-amber-500'],
                    default => ['label' => 'Invitation Required', 'classes' => 'bg-slate-100 text-slate-600'],
                };
            } elseif ($modalUser->isMobileActive()) {
                $modalStatus = ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-500'];
            } else {
                $modalStatus = ['label' => 'Inactive', 'classes' => 'bg-slate-100 text-slate-600'];
            }
            $editingThisUser = (string) old('_editing_user') === (string) $modalUser->id;
        @endphp

        <div id="view-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="view-user-title-{{ $modalUser->id }}">
            <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">

                <div class="relative flex items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-400 text-lg font-bold text-white shadow-md shadow-slate-300/50">
                            {{ $modalUser->initials() ?: strtoupper(substr($modalUser->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border bg-slate-50 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-500  ">
                                <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                                User Profile
                            </div>
                            <h2 id="view-user-title-{{ $modalUser->id }}" class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-700">{{ $modalUser->name }}</h2>
                        </div>
                    </div>

                    <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border bg-slate-50 text-slate-500   transition hover:bg-white/70 hover:text-slate-600" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="relative max-h-[calc(90vh-120px)] overflow-y-auto px-2 pb-2 pt-4 sm:px-0">
                    <div class="mb-6 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full rounded-full px-3 py-1 text-xs font-semibold {{ $modalStatus['classes'] }}">{{ $modalStatus['label'] }}</span>
                        <span class="inline-flex rounded-full border bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-400 ">{{ $modalUser->roleLabel() }}</span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</p>
                            <p class="mt-2 break-words text-sm font-medium text-slate-600">{{ $modalUser->email }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</p>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $modalUser->phone ?: 'Not provided' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Gender</p>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $modalUser->gender ? str($modalUser->gender)->title() : 'Not provided' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Birthdate</p>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $modalUser->birthdate?->format('F d, Y') ?: 'Not provided' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Joined</p>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $modalUser->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email Verification</p>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $modalUser->email_verified_at?->format('F d, Y h:i A') ?: 'Not verified' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Address</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $modalUser->address ?: 'No address on file.' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Emergency Contact</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ $modalUser->emergency_contact ?: trim(collect([$modalUser->emergency_contact_name, $modalUser->emergency_contact_number])->filter()->implode(' - ')) ?: 'No emergency contact on file.' }}
                            </p>
                        </div>
                        @if($modalUser->normalizedRole() === $runnerRole)
                            <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Medical Conditions</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $modalUser->medical_conditions ?: 'No medical conditions recorded.' }}</p>
                            </div>
                            <div class="rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)] md:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Health Measurements</p>
                                @if($modalUser->bmi !== null)
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ number_format((float) $modalUser->height_cm, 2) }} cm · {{ number_format((float) $modalUser->weight_kg, 2) }} kg · BMI {{ number_format($modalUser->bmi, 1) }}@if($modalUser->bmi_category) ({{ $modalUser->bmi_category }})@endif</p>
                                    <p class="mt-1 text-xs text-slate-400">Measured {{ $modalUser->bmi_measured_at?->format('F d, Y') ?: 'date unavailable' }}. Informational screening only.</p>
                                @else
                                    <p class="mt-2 text-sm leading-6 text-slate-600">No height and weight measurements recorded.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div id="edit-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 {{ $editingThisUser && $errors->any() ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="edit-user-title-{{ $modalUser->id }}">
            <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">

                <form method="POST" action="{{ route('admin.users.update', $modalUser) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_user" value="{{ $modalUser->id }}">

                    <div class="relative flex items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-400 text-lg font-bold text-white shadow-md shadow-slate-300/50">
                                {{ $modalUser->initials() ?: strtoupper(substr($modalUser->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="inline-flex items-center gap-2 rounded-full border bg-slate-50 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-500  ">
                                    <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                                    Edit User
                                </div>
                                <h2 id="edit-user-title-{{ $modalUser->id }}" class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-700">{{ $modalUser->name }}</h2>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-indigo-400 px-4 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                Save Changes
                            </button>
                            <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border bg-slate-50 text-slate-500   transition hover:bg-white/70 hover:text-slate-600" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="relative max-h-[calc(90vh-196px)] overflow-y-auto px-2 pb-2 pt-4 sm:px-0">
                        @if($editingThisUser && $errors->any())
                            <div class="mb-5 rounded-2xl border bg-rose-50 px-4 py-3 text-sm font-bold text-rose-400  ">
                                Please review the highlighted fields and try again.
                            </div>
                        @endif

                        <div class="space-y-8">
                            <section class="rounded-[1.75rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                                <div class="mb-5">
                                    <div class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-400">
                                        Basic Information
                                    </div>
                                    <h3 class="mt-3 text-lg font-bold tracking-tight text-slate-700">Account details</h3>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label for="name-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Name</label>
                                        <input type="text" name="name" id="name-{{ $modalUser->id }}" required value="{{ $editingThisUser ? old('name', $modalUser->name) : $modalUser->name }}" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisUser) @error('name') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="email-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Email</label>
                                        <input type="email" name="email" id="email-{{ $modalUser->id }}" required value="{{ $editingThisUser ? old('email', $modalUser->email) : $modalUser->email }}" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisUser) @error('email') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="role-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Role</label>
                                        <select name="role" id="role-{{ $modalUser->id }}" required data-user-role-select data-runner-role="{{ $runnerRole }}" data-medical-target="medical-conditions-group-{{ $modalUser->id }}" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                            @foreach (\App\Models\User::manageableRoles() as $role)
                                                <option value="{{ $role }}" @selected(($editingThisUser ? old('role', $modalUser->normalizedRole()) : $modalUser->normalizedRole()) === $role)>
                                                    {{ $roleLabels[$role] ?? str($role)->replace('_', ' ')->title() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($editingThisUser) @error('role') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="phone-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Phone</label>
                                        <input type="text" name="phone" id="phone-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('phone', $modalUser->phone) : $modalUser->phone }}" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisUser) @error('phone') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="gender-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Gender</label>
                                        <select name="gender" id="gender-{{ $modalUser->id }}" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                            @php($selectedGender = $editingThisUser ? old('gender', $modalUser->gender) : $modalUser->gender)
                                            <option value="">Select gender</option>
                                            <option value="male" @selected($selectedGender === 'male')>Male</option>
                                            <option value="female" @selected($selectedGender === 'female')>Female</option>
                                            <option value="other" @selected($selectedGender === 'other')>Other</option>
                                        </select>
                                        @if($editingThisUser) @error('gender') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="birthdate-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Birthdate</label>
                                        <input type="date" name="birthdate" id="birthdate-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('birthdate', $modalUser->birthdate?->format('Y-m-d')) : $modalUser->birthdate?->format('Y-m-d') }}" min="{{ \App\Support\ParticipantBirthdate::oldestAllowedDate() }}" max="{{ \App\Support\ParticipantBirthdate::latestAllowedDate() }}" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisUser) @error('birthdate') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-[1.75rem] bg-white p-5 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                                <div class="mb-5">
                                    <div class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-400">
                                        Additional Information
                                    </div>
                                    <h3 class="mt-3 text-lg font-bold tracking-tight text-slate-700">Profile and safety details</h3>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="address-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Address</label>
                                        <textarea name="address" id="address-{{ $modalUser->id }}" rows="3" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ $editingThisUser ? old('address', $modalUser->address) : $modalUser->address }}</textarea>
                                        @if($editingThisUser) @error('address') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div id="medical-conditions-group-{{ $modalUser->id }}" class="md:col-span-2">
                                        <label for="medical_conditions-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Medical Conditions</label>
                                        <textarea name="medical_conditions" id="medical_conditions-{{ $modalUser->id }}" rows="3" placeholder="Any medical conditions or allergies organizers should know about" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ $editingThisUser ? old('medical_conditions', $modalUser->medical_conditions) : $modalUser->medical_conditions }}</textarea>
                                        @if($editingThisUser) @error('medical_conditions') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif

                                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                                            <div>
                                                <label for="height_cm-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Height (cm)</label>
                                                <input type="number" name="height_cm" id="height_cm-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('height_cm', $modalUser->height_cm) : $modalUser->height_cm }}" min="50" max="250" step="0.01" inputmode="decimal" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none focus:ring-2 focus:ring-indigo-200">
                                                @if($editingThisUser) @error('height_cm') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                            </div>
                                            <div>
                                                <label for="weight_kg-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Weight (kg)</label>
                                                <input type="number" name="weight_kg" id="weight_kg-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('weight_kg', $modalUser->weight_kg) : $modalUser->weight_kg }}" min="10" max="500" step="0.01" inputmode="decimal" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none focus:ring-2 focus:ring-indigo-200">
                                                @if($editingThisUser) @error('weight_kg') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                            </div>
                                            <p class="text-sm text-slate-500">
                                                @if($modalUser->bmi_measured_at)
                                                    Measurements updated {{ $modalUser->bmi_measured_at->format('F d, Y') }}.
                                                @endif
                                                The date updates automatically when height or weight changes.
                                            </p>
                                            <div class="rounded-2xl bg-slate-50 p-4">
                                                <p class="text-sm font-medium text-slate-600">Current BMI</p>
                                                <p class="mt-1 text-lg font-semibold text-slate-700">{{ $modalUser->bmi !== null ? number_format($modalUser->bmi, 1) : 'Not available' }}</p>
                                                <p class="mt-1 text-xs text-slate-400">{{ $modalUser->bmi_category ?? 'No adult BMI category available.' }}</p>
                                            </div>
                                        </div>
                                        <p class="mt-3 text-xs leading-5 text-slate-400">BMI is informational only and is never used automatically to approve or reject participation.</p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="emergency_contact_name-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Emergency Contact Name</label>
                                        <input type="text" name="emergency_contact_name" id="emergency_contact_name-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('emergency_contact_name', $modalUser->emergency_contact_name) : $modalUser->emergency_contact_name }}" placeholder="Emergency contact full name" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisUser) @error('emergency_contact_name') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="emergency_contact_number-{{ $modalUser->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Emergency Contact Number</label>
                                        <input type="text" name="emergency_contact_number" id="emergency_contact_number-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('emergency_contact_number', $modalUser->emergency_contact_number) : $modalUser->emergency_contact_number }}" placeholder="Emergency contact phone number" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-slate-600 outline-none placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                        @if($editingThisUser) @error('emergency_contact_number') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        @if($modalUser->id !== auth()->id())
            @if($modalUser->banned_at)
                <div id="unban-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="unban-user-title-{{ $modalUser->id }}" aria-describedby="unban-user-description-{{ $modalUser->id }}">
                    <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close unban confirmation"></button>

                    <div class="relative w-full max-w-xl overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                        <i class="fas fa-user-check" aria-hidden="true"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Restore Account Access</p>
                                        <h2 id="unban-user-title-{{ $modalUser->id }}" class="mt-2 text-2xl font-bold tracking-tight text-slate-700">Unban {{ $modalUser->name }}?</h2>
                                    </div>
                                </div>

                                <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Close unban confirmation">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>

                            <p id="unban-user-description-{{ $modalUser->id }}" class="mt-5 text-sm leading-6 text-slate-600">
                                This will remove the account ban. The user may sign in again when all other account and verification requirements are satisfied.
                            </p>

                            <form method="POST" action="{{ route('admin.users.unban', $modalUser) }}" class="mt-6 flex flex-wrap justify-end gap-3" data-confirmation-form>
                                @csrf
                                <button type="button" data-close-user-modal data-modal-initial-focus class="inline-flex h-11 items-center justify-center rounded-full bg-slate-100 px-5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">Cancel</button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
                                    <i class="fas fa-user-check" aria-hidden="true"></i>
                                    <span>Unban User</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <div id="ban-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="ban-user-title-{{ $modalUser->id }}" aria-describedby="ban-user-description-{{ $modalUser->id }}">
                    <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close ban confirmation"></button>

                    <div class="relative w-full max-w-xl overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                                        <i class="fas fa-user-slash" aria-hidden="true"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Restrict Account Access</p>
                                        <h2 id="ban-user-title-{{ $modalUser->id }}" class="mt-2 text-2xl font-bold tracking-tight text-slate-700">Ban {{ $modalUser->name }}?</h2>
                                    </div>
                                </div>

                                <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Close ban confirmation">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>

                            <p id="ban-user-description-{{ $modalUser->id }}" class="mt-5 text-sm leading-6 text-slate-600">
                                This will immediately prevent the user from signing in. Their account and records will remain stored, and an administrator can unban them later.
                            </p>

                            <form method="POST" action="{{ route('admin.users.ban', $modalUser) }}" class="mt-6 flex flex-wrap justify-end gap-3" data-confirmation-form>
                                @csrf
                                <button type="button" data-close-user-modal data-modal-initial-focus class="inline-flex h-11 items-center justify-center rounded-full bg-slate-100 px-5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">Cancel</button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-wait disabled:opacity-60">
                                    <i class="fas fa-user-slash" aria-hidden="true"></i>
                                    <span>Ban User</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div id="delete-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="delete-user-title-{{ $modalUser->id }}">
                <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

                <div class="relative w-full max-w-xl overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">

                    <div class="relative p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border bg-rose-50 text-rose-400  ">
                                    <i class="fas fa-trash-alt text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-400">
                                        <span class="h-2 w-2 rounded-full bg-rose-500 shadow-[0_0_12px_rgba(244,63,94,0.75)]"></span>
                                        Delete User
                                    </div>
                                    <h2 id="delete-user-title-{{ $modalUser->id }}" class="mt-3 text-2xl font-bold tracking-tight text-slate-700">Delete {{ $modalUser->name }}?</h2>
                                </div>
                            </div>

                            <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border bg-slate-50 text-slate-500   transition hover:bg-white/70 hover:text-slate-600" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="mt-5 rounded-[1.5rem] bg-white p-4 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
                            <div class="flex items-center gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-sm font-bold text-indigo-400">
                                    {{ strtoupper(substr($modalUser->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-700">{{ $modalUser->name }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $modalUser->email }}</p>
                                </div>
                            </div>
                        </div>

                        <p class="mt-5 text-sm leading-6 text-slate-600">
                            This will permanently remove the account from the user list. This action uses the existing delete route and cannot be undone.
                        </p>

                        <form method="POST" action="{{ route('admin.users.destroy', $modalUser) }}" class="mt-6 flex flex-wrap justify-end gap-3">
                            @csrf
                            @method('DELETE')

                            <button type="button" data-close-user-modal class="inline-flex h-11 items-center justify-center rounded-full bg-slate-50 px-5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-rose-50 px-5 text-sm font-semibold text-rose-400 transition hover:bg-rose-100">
                                Delete User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    <script>
        (function () {
            let activeModalTrigger = null;

            const closeModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');

                if (activeModalTrigger?.isConnected) {
                    activeModalTrigger.focus();
                }

                activeModalTrigger = null;
            };

            const openModal = (modal, trigger = null) => {
                if (!modal) {
                    return;
                }

                activeModalTrigger = trigger;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');

                window.requestAnimationFrame(() => {
                    modal.querySelector('[data-modal-initial-focus]')?.focus();
                });
            };

            document.querySelectorAll('[data-open-user-modal]').forEach((button) => {
                button.addEventListener('click', () => openModal(document.getElementById(button.dataset.openUserModal), button));
            });

            document.querySelectorAll('[data-close-user-modal]').forEach((button) => {
                button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                document.querySelectorAll('[role="dialog"].flex').forEach(closeModal);
            });

            document.querySelectorAll('[data-user-role-select]').forEach((select) => {
                const target = document.getElementById(select.dataset.medicalTarget);

                if (!target) {
                    return;
                }

                const healthInputs = target.querySelectorAll('input, textarea');
                const toggleMedicalConditions = () => {
                    const isRunner = select.value === select.dataset.runnerRole;

                    target.classList.toggle('hidden', !isRunner);

                    if (!isRunner) {
                        healthInputs.forEach((input) => {
                            input.value = '';
                        });
                    }
                };

                select.addEventListener('change', toggleMedicalConditions);
                toggleMedicalConditions();
            });

            if (document.querySelector('[role="dialog"].flex')) {
                document.body.classList.add('overflow-hidden');
            }

            document.querySelectorAll('[data-confirmation-form]').forEach((form) => {
                form.addEventListener('submit', () => {
                    const submitButton = form.querySelector('button[type="submit"]');

                    if (!submitButton || submitButton.disabled) {
                        return;
                    }

                    submitButton.disabled = true;
                    submitButton.setAttribute('aria-busy', 'true');
                });
            });

            const resendButtons = Array.from(document.querySelectorAll('[data-invitation-resend-button]'));

            const formatCountdown = (seconds) => {
                const minutes = Math.floor(seconds / 60);
                const remainder = seconds % 60;

                return `${minutes}:${String(remainder).padStart(2, '0')}`;
            };

            const updateInvitationCountdowns = () => {
                const nowInSeconds = Math.floor(Date.now() / 1000);

                resendButtons.forEach((button) => {
                    const availableAt = Number(button.dataset.resendAvailableAt || 0);
                    const remaining = Math.max(0, availableAt - nowInSeconds);

                    if (remaining > 0) {
                        button.disabled = true;
                        button.textContent = `Resend in ${formatCountdown(remaining)}`;
                        return;
                    }

                    button.disabled = false;
                    button.textContent = button.dataset.defaultLabel || 'Resend Invitation';
                });
            };

            if (resendButtons.length > 0) {
                updateInvitationCountdowns();
                window.setInterval(updateInvitationCountdowns, 1000);
            }
        })();
    </script>
@endif
@endsection
