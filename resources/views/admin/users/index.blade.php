@extends('admin.layouts.app')

@section('title', auth()->user()->isSuperAdmin() ? 'User Management' : 'Users Overview')

@section('content')
<style>
    .users-glass-pagination nav > div:first-child {
        color: #64748b;
    }

    .users-glass-pagination nav > div:last-child {
        align-items: center;
        gap: 0.75rem;
    }

    .users-glass-pagination nav p {
        color: #64748b;
        font-size: 0.875rem;
    }

    .users-glass-pagination nav span[aria-current="page"] span,
    .users-glass-pagination nav a,
    .users-glass-pagination nav span[aria-disabled="true"] span {
        border-radius: 0.875rem !important;
        border: 1px solid rgba(255, 255, 255, 0.6) !important;
        background: rgba(255, 255, 255, 0.45) !important;
        box-shadow: 0 10px 24px rgba(148, 163, 184, 0.18) !important;
        backdrop-filter: blur(18px);
    }

    .users-glass-pagination nav span[aria-current="page"] span {
        background: rgba(15, 23, 42, 0.92) !important;
        border-color: rgba(15, 23, 42, 0.92) !important;
        color: #ffffff !important;
    }

    .users-glass-pagination nav a {
        color: #202733 !important;
        transition: background 160ms ease, transform 160ms ease;
    }

    .users-glass-pagination nav a:hover {
        background: rgba(255, 255, 255, 0.72) !important;
        transform: translateY(-1px);
    }

    .users-glass-pagination nav span[aria-disabled="true"] span {
        color: #94a3b8 !important;
        opacity: 0.75;
    }
</style>

@php
    $roleLabels = \App\Models\User::roleLabels();
    $canManageUsers = auth()->user()->isSuperAdmin();
    $runnerRole = \App\Models\User::ROLE_RUNNER;
@endphp

<div class="relative min-h-screen overflow-hidden bg-[#eaf2f9] px-4 py-6 sm:px-6 lg:px-8">
    {{-- Background glass blobs --}}
    <div class="pointer-events-none absolute -top-24 left-8 h-72 w-72 rounded-full bg-sky-300/35 blur-3xl"></div>
    <div class="pointer-events-none absolute top-40 right-0 h-96 w-96 rounded-full bg-cyan-300/25 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-indigo-300/20 blur-3xl"></div>

    <div class="relative mx-auto max-w-[1600px] space-y-6">
        {{-- Header --}}
        <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-white/35 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.10)] backdrop-blur-2xl">
            <div class="rounded-[1.6rem] border border-white/60 bg-white/30 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-4 py-2 text-xs font-bold uppercase tracking-[0.24em] text-sky-700 shadow-sm backdrop-blur-xl">
                            <span class="h-2.5 w-2.5 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                            People
                        </div>

                        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">
                            {{ $canManageUsers ? 'User Management' : 'Users Overview' }}
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ $canManageUsers ? 'Manage platform accounts, access roles, and account restrictions.' : 'Review account activity, roles, and participant status across the platform.' }}
                        </p>
                    </div>

                    @if($canManageUsers)
                        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-950/90 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                            <i class="fas fa-user-plus mr-2 text-xs"></i>
                            Add New User
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="grid gap-3 rounded-[1.6rem] border border-white/60 bg-white/35 p-4 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40 lg:grid-cols-[minmax(240px,1fr)_220px_180px_auto_auto] lg:items-end">
            <div>
                <label for="search" class="mb-2 block text-sm font-semibold text-slate-700">Search</label>
                <input
                    id="search"
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Name or email"
                    class="h-11 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                >
            </div>

            <div>
                <label for="role" class="mb-2 block text-sm font-semibold text-slate-700">Role</label>
                <select
                    id="role"
                    name="role"
                    class="h-11 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
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
                <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                <select
                    id="status"
                    name="status"
                    class="h-11 w-full rounded-xl border border-white/60 bg-white/50 px-4 text-sm text-slate-800 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70"
                >
                    <option value="">All status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    <option value="banned" @selected(request('status') === 'banned')>Banned</option>
                </select>
            </div>

            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950/90 px-5 text-sm font-semibold text-white shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200">
                Filter
            </button>

            <a href="{{ route('admin.users.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/65 focus:outline-none focus:ring-4 focus:ring-sky-100/70">
                Clear
            </a>
        </form>

        {{-- Users Table --}}
        <div class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.10)] backdrop-blur-2xl ring-1 ring-white/40">
            <div class="overflow-x-auto p-3">
                <table class="min-w-full border-separate border-spacing-y-3 text-left">
                    <thead>
                        <tr class="text-xs font-bold uppercase tracking-[0.18em] text-slate-600">
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Joined</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="text-sm text-slate-700">
                        @forelse($users as $user)
                            <tr class="align-top">
                                <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950/90 text-sm font-bold text-white shadow-md shadow-slate-300/50">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-950">{{ $user->name }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    <span @class([
                                        'inline-flex rounded-full border px-3 py-1.5 text-xs font-bold backdrop-blur-xl',
                                        'border-purple-200/70 bg-purple-100/70 text-purple-700' => $user->role === 'super_admin',
                                        'border-indigo-200/70 bg-indigo-100/70 text-indigo-700' => $user->role === 'executive',
                                        'border-amber-200/70 bg-amber-100/70 text-amber-700' => $user->role === 'content_moderator',
                                        'border-sky-200/70 bg-sky-100/70 text-sky-700' => in_array($user->role, ['event_manager', 'admin'], true),
                                        'border-slate-200/70 bg-slate-100/70 text-slate-600' => ! in_array($user->role, ['super_admin', 'executive', 'content_moderator', 'event_manager', 'admin'], true),
                                    ])>
                                        {{ $user->roleLabel() }}
                                    </span>
                                </td>

                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    {{ $user->phone ?: '-' }}
                                </td>

                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    @if($user->banned_at)
                                        <span class="inline-flex rounded-full border border-rose-200/70 bg-rose-100/70 px-3 py-1.5 text-xs font-bold text-rose-700 backdrop-blur-xl">Banned</span>
                                    @elseif($user->suspended_at)
                                        <span class="inline-flex rounded-full border border-amber-200/70 bg-amber-100/70 px-3 py-1.5 text-xs font-bold text-amber-700 backdrop-blur-xl">Suspended</span>
                                    @elseif($user->isMobileActive())
                                        <span class="inline-flex rounded-full border border-emerald-200/70 bg-emerald-100/70 px-3 py-1.5 text-xs font-bold text-emerald-700 backdrop-blur-xl">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200/70 bg-slate-100/70 px-3 py-1.5 text-xs font-bold text-slate-600 backdrop-blur-xl">Inactive</span>
                                    @endif
                                </td>

                                <td class="border-y border-white/60 bg-white/40 px-4 py-4 backdrop-blur-xl">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>

                                <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/40 px-4 py-4 text-right backdrop-blur-xl">
                                    @if($canManageUsers)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <button type="button" data-open-user-modal="view-user-{{ $user->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                                                View
                                            </button>

                                            <button type="button" data-open-user-modal="edit-user-{{ $user->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                                                Edit
                                            </button>

                                            @if($user->id !== auth()->id())
                                                @if($user->banned_at)
                                                    <form method="POST" action="{{ route('admin.users.unban', $user) }}">
                                                        @csrf
                                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200/70 bg-emerald-100/60 px-4 text-xs font-bold text-emerald-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-emerald-100">
                                                            Unban
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                                                        @csrf
                                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200/70 bg-rose-100/60 px-4 text-xs font-bold text-rose-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-rose-100">
                                                            Ban
                                                        </button>
                                                    </form>
                                                @endif

                                                <button type="button" data-open-user-modal="delete-user-{{ $user->id }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200/70 bg-rose-100/60 px-4 text-xs font-bold text-rose-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-rose-100">
                                                    Delete
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex h-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-4 text-xs font-bold text-slate-500 backdrop-blur-xl">
                                            Read only
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="rounded-2xl border border-white/60 bg-white/40 px-6 py-12 text-center text-sm text-slate-500 backdrop-blur-xl">
                                    No users found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="users-glass-pagination border-t border-white/50 bg-white/25 px-6 py-4 backdrop-blur-xl">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>

@if($canManageUsers)
    @foreach($users as $modalUser)
        @php
            $modalStatus = $modalUser->banned_at
                ? ['label' => 'Banned', 'classes' => 'border-rose-200/70 bg-rose-100/70 text-rose-700']
                : ($modalUser->suspended_at
                    ? ['label' => 'Suspended', 'classes' => 'border-amber-200/70 bg-amber-100/70 text-amber-700']
                    : ($modalUser->isMobileActive()
                        ? ['label' => 'Active', 'classes' => 'border-emerald-200/70 bg-emerald-100/70 text-emerald-700']
                        : ['label' => 'Inactive', 'classes' => 'border-slate-200/70 bg-slate-100/70 text-slate-600']));
            $editingThisUser = (string) old('_editing_user') === (string) $modalUser->id;
        @endphp

        <div id="view-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="view-user-title-{{ $modalUser->id }}">
            <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

            <div class="relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="pointer-events-none absolute -top-24 left-10 h-56 w-56 rounded-full bg-sky-300/35 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-cyan-300/25 blur-3xl"></div>

                <div class="relative flex items-start justify-between gap-4 rounded-[1.6rem] border border-white/60 bg-white/35 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-950/90 text-lg font-bold text-white shadow-md shadow-slate-300/50">
                            {{ $modalUser->initials() ?: strtoupper(substr($modalUser->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-700 shadow-sm backdrop-blur-xl">
                                <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                                User Profile
                            </div>
                            <h2 id="view-user-title-{{ $modalUser->id }}" class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-950">{{ $modalUser->name }}</h2>
                        </div>
                    </div>

                    <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="relative max-h-[calc(90vh-120px)] overflow-y-auto px-2 pb-2 pt-4 sm:px-0">
                    <div class="mb-6 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full border px-3 py-1.5 text-xs font-bold backdrop-blur-xl {{ $modalStatus['classes'] }}">{{ $modalStatus['label'] }}</span>
                        <span class="inline-flex rounded-full border border-indigo-200/70 bg-indigo-100/70 px-3 py-1.5 text-xs font-bold text-indigo-700 backdrop-blur-xl">{{ $modalUser->roleLabel() }}</span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Email</p>
                            <p class="mt-2 break-words text-sm font-medium text-slate-800">{{ $modalUser->email }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Phone</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $modalUser->phone ?: 'Not provided' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Gender</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $modalUser->gender ? str($modalUser->gender)->title() : 'Not provided' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Birthdate</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $modalUser->birthdate?->format('F d, Y') ?: 'Not provided' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Joined</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $modalUser->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Email Verification</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $modalUser->email_verified_at?->format('F d, Y h:i A') ?: 'Not verified' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl md:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Address</p>
                            <p class="mt-2 text-sm leading-6 text-slate-800">{{ $modalUser->address ?: 'No address on file.' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl md:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Emergency Contact</p>
                            <p class="mt-2 text-sm leading-6 text-slate-800">
                                {{ $modalUser->emergency_contact ?: trim(collect([$modalUser->emergency_contact_name, $modalUser->emergency_contact_number])->filter()->implode(' - ')) ?: 'No emergency contact on file.' }}
                            </p>
                        </div>
                        @if($modalUser->normalizedRole() === $runnerRole)
                            <div class="rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl md:col-span-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Medical Conditions</p>
                                <p class="mt-2 text-sm leading-6 text-slate-800">{{ $modalUser->medical_conditions ?: 'No medical conditions recorded.' }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div id="edit-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 {{ $editingThisUser && $errors->any() ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="edit-user-title-{{ $modalUser->id }}">
            <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

            <div class="relative max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="pointer-events-none absolute -top-24 left-10 h-56 w-56 rounded-full bg-sky-300/35 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-cyan-300/25 blur-3xl"></div>

                <form method="POST" action="{{ route('admin.users.update', $modalUser) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_user" value="{{ $modalUser->id }}">

                    <div class="relative flex items-start justify-between gap-4 rounded-[1.6rem] border border-white/60 bg-white/35 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-950/90 text-lg font-bold text-white shadow-md shadow-slate-300/50">
                                {{ $modalUser->initials() ?: strtoupper(substr($modalUser->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-sky-700 shadow-sm backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_12px_rgba(14,165,233,0.8)]"></span>
                                    Edit User
                                </div>
                                <h2 id="edit-user-title-{{ $modalUser->id }}" class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-950">{{ $modalUser->name }}</h2>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950/90 px-4 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-slate-800">
                                Save Changes
                            </button>
                            <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="relative max-h-[calc(90vh-196px)] overflow-y-auto px-2 pb-2 pt-4 sm:px-0">
                        @if($editingThisUser && $errors->any())
                            <div class="mb-5 rounded-2xl border border-rose-200/70 bg-rose-100/70 px-4 py-3 text-sm font-bold text-rose-700 shadow-sm backdrop-blur-xl">
                                Please review the highlighted fields and try again.
                            </div>
                        @endif

                        <div class="space-y-8">
                            <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40">
                                <div class="mb-5">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-slate-600 shadow-sm backdrop-blur-xl">
                                        Basic Information
                                    </div>
                                    <h3 class="mt-3 text-lg font-bold tracking-tight text-slate-950">Account details</h3>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label for="name-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Name</label>
                                        <input type="text" name="name" id="name-{{ $modalUser->id }}" required value="{{ $editingThisUser ? old('name', $modalUser->name) : $modalUser->name }}" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisUser) @error('name') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="email-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Email</label>
                                        <input type="email" name="email" id="email-{{ $modalUser->id }}" required value="{{ $editingThisUser ? old('email', $modalUser->email) : $modalUser->email }}" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisUser) @error('email') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="role-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Role</label>
                                        <select name="role" id="role-{{ $modalUser->id }}" required data-user-role-select data-runner-role="{{ $runnerRole }}" data-medical-target="medical-conditions-group-{{ $modalUser->id }}" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                            @foreach (\App\Models\User::manageableRoles() as $role)
                                                <option value="{{ $role }}" @selected(($editingThisUser ? old('role', $modalUser->normalizedRole()) : $modalUser->normalizedRole()) === $role)>
                                                    {{ $roleLabels[$role] ?? str($role)->replace('_', ' ')->title() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($editingThisUser) @error('role') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="phone-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Phone</label>
                                        <input type="text" name="phone" id="phone-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('phone', $modalUser->phone) : $modalUser->phone }}" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisUser) @error('phone') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="gender-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Gender</label>
                                        <select name="gender" id="gender-{{ $modalUser->id }}" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                            @php($selectedGender = $editingThisUser ? old('gender', $modalUser->gender) : $modalUser->gender)
                                            <option value="">Select gender</option>
                                            <option value="male" @selected($selectedGender === 'male')>Male</option>
                                            <option value="female" @selected($selectedGender === 'female')>Female</option>
                                            <option value="other" @selected($selectedGender === 'other')>Other</option>
                                        </select>
                                        @if($editingThisUser) @error('gender') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div>
                                        <label for="birthdate-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Birthdate</label>
                                        <input type="date" name="birthdate" id="birthdate-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('birthdate', $modalUser->birthdate?->format('Y-m-d')) : $modalUser->birthdate?->format('Y-m-d') }}" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisUser) @error('birthdate') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-[1.6rem] border border-white/60 bg-white/35 p-5 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl ring-1 ring-white/40">
                                <div class="mb-5">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-slate-600 shadow-sm backdrop-blur-xl">
                                        Additional Information
                                    </div>
                                    <h3 class="mt-3 text-lg font-bold tracking-tight text-slate-950">Profile and safety details</h3>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="address-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Address</label>
                                        <textarea name="address" id="address-{{ $modalUser->id }}" rows="3" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ $editingThisUser ? old('address', $modalUser->address) : $modalUser->address }}</textarea>
                                        @if($editingThisUser) @error('address') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div id="medical-conditions-group-{{ $modalUser->id }}" class="md:col-span-2">
                                        <label for="medical_conditions-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Medical Conditions</label>
                                        <textarea name="medical_conditions" id="medical_conditions-{{ $modalUser->id }}" rows="3" placeholder="Any medical conditions or allergies organizers should know about" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ $editingThisUser ? old('medical_conditions', $modalUser->medical_conditions) : $modalUser->medical_conditions }}</textarea>
                                        @if($editingThisUser) @error('medical_conditions') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="emergency_contact_name-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Emergency Contact Name</label>
                                        <input type="text" name="emergency_contact_name" id="emergency_contact_name-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('emergency_contact_name', $modalUser->emergency_contact_name) : $modalUser->emergency_contact_name }}" placeholder="Emergency contact full name" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisUser) @error('emergency_contact_name') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="emergency_contact_number-{{ $modalUser->id }}" class="mb-2 block text-sm font-semibold text-slate-800">Emergency Contact Number</label>
                                        <input type="text" name="emergency_contact_number" id="emergency_contact_number-{{ $modalUser->id }}" value="{{ $editingThisUser ? old('emergency_contact_number', $modalUser->emergency_contact_number) : $modalUser->emergency_contact_number }}" placeholder="Emergency contact phone number" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                        @if($editingThisUser) @error('emergency_contact_number') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        @if($modalUser->id !== auth()->id())
            <div id="delete-user-{{ $modalUser->id }}" class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="delete-user-title-{{ $modalUser->id }}">
                <button type="button" data-close-user-modal class="absolute inset-0 bg-slate-950/35 backdrop-blur-md" aria-label="Close dialog"></button>

                <div class="relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/60 bg-[#eaf2f9]/85 p-4 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                    <div class="pointer-events-none absolute -top-20 left-8 h-48 w-48 rounded-full bg-rose-300/30 blur-3xl"></div>
                    <div class="pointer-events-none absolute bottom-0 right-0 h-52 w-52 rounded-full bg-sky-300/25 blur-3xl"></div>

                    <div class="relative rounded-[1.6rem] border border-white/60 bg-white/35 p-6 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-rose-200/70 bg-rose-100/70 text-rose-700 shadow-sm backdrop-blur-xl">
                                    <i class="fas fa-trash-alt text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/45 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.22em] text-rose-700 shadow-sm backdrop-blur-xl">
                                        <span class="h-2 w-2 rounded-full bg-rose-500 shadow-[0_0_12px_rgba(244,63,94,0.75)]"></span>
                                        Delete User
                                    </div>
                                    <h2 id="delete-user-title-{{ $modalUser->id }}" class="mt-3 text-2xl font-bold tracking-tight text-slate-950">Delete {{ $modalUser->name }}?</h2>
                                </div>
                            </div>

                            <button type="button" data-close-user-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-slate-500 shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-slate-800" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="mt-5 rounded-2xl border border-white/60 bg-white/40 p-4 shadow-sm backdrop-blur-xl">
                            <div class="flex items-center gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950/90 text-sm font-bold text-white shadow-md shadow-slate-300/50">
                                    {{ strtoupper(substr($modalUser->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-950">{{ $modalUser->name }}</p>
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

                            <button type="button" data-close-user-modal class="inline-flex h-11 items-center justify-center rounded-xl border border-white/60 bg-white/45 px-5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition hover:-translate-y-0.5 hover:bg-white/70">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-rose-200/70 bg-rose-600 px-5 text-sm font-semibold text-white shadow-lg shadow-rose-200/50 transition hover:-translate-y-0.5 hover:bg-rose-700">
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
            const closeModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            };

            const openModal = (modal) => {
                if (!modal) {
                    return;
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            document.querySelectorAll('[data-open-user-modal]').forEach((button) => {
                button.addEventListener('click', () => openModal(document.getElementById(button.dataset.openUserModal)));
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

                const textarea = target.querySelector('textarea');
                const toggleMedicalConditions = () => {
                    const isRunner = select.value === select.dataset.runnerRole;

                    target.classList.toggle('hidden', !isRunner);

                    if (!isRunner && textarea) {
                        textarea.value = '';
                    }
                };

                select.addEventListener('change', toggleMedicalConditions);
                toggleMedicalConditions();
            });

            if (document.querySelector('[role="dialog"].flex')) {
                document.body.classList.add('overflow-hidden');
            }
        })();
    </script>
@endif
@endsection
