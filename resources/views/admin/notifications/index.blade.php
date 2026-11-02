@extends('admin.layouts.app')

@section('title', 'Push Notifications')

@section('content')
@php
    $user = auth()->user();
    $canSendAdminScopedNotifications = $user->hasAdminRole([
        \App\Models\User::ROLE_SUPER_ADMIN,
        \App\Models\User::ROLE_EVENT_MANAGER,
    ]);
    $typeOptions = [
        'payment' => 'Payment',
        'reminder' => 'Reminder',
    ];
    $audienceOptions = [
        'all' => 'All Users',
        'runners' => 'All Participants',
        'participants' => 'Registered Event Participants',
    ];

    if ($canSendAdminScopedNotifications) {
        $typeOptions['emergency'] = 'Emergency';
        $audienceOptions['admins'] = 'Admin Users';
    }

    $creatingNotification = old('_creating_notification') === '1' && $errors->any();
@endphp

<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1400px] space-y-6">

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Communications</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-700">Push Notifications</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-400">Create, schedule, and send mobile push alerts for all participants, registered event participants, and admin teams.</p>
            </div>

            <button type="button" data-open-notification-modal="create-notification" class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                <i class="fas fa-plus mr-2 text-xs"></i>
                Create Notification
            </button>
        </div>

        <div class="rounded-[1.75rem] bg-white shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-6 py-4">Notification</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Audience</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Scheduled</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notifications as $notification)
                            @php
                                $canManageNotification = $canSendAdminScopedNotifications
                                    || ($notification->type !== 'emergency' && $notification->target_audience !== 'admins');

                                $typeClass = [
                                    'payment' => 'bg-indigo-50 text-indigo-400',
                                    'reminder' => 'bg-amber-50 text-amber-500',
                                    'announcement' => 'bg-emerald-50 text-emerald-500',
                                    'community' => 'bg-violet-50 text-violet-400',
                                    'emergency' => 'bg-rose-50 text-rose-400',
                                ][$notification->type] ?? 'bg-slate-100 text-slate-500';

                                $audienceClass = [
                                    'all' => 'bg-indigo-50 text-indigo-400',
                                    'runners' => 'bg-emerald-50 text-emerald-500',
                                    'participants' => 'bg-sky-50 text-sky-500',
                                    'admins' => 'bg-slate-100 text-slate-500',
                                ][$notification->target_audience] ?? 'bg-slate-100 text-slate-500';
                            @endphp
                            <tr class="align-top transition hover:bg-[#f7f8fc]">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-700">{{ $notification->title }}</p>
                                    <p class="mt-1 max-w-md text-xs leading-5 text-slate-400">{{ \Illuminate\Support\Str::limit($notification->message, 80) }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClass }}">
                                        {{ ucfirst($notification->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $audienceClass }}">
                                        {{ $audienceOptions[$notification->target_audience] ?? str($notification->target_audience)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($notification->sent_at)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-500">Sent</span>
                                    @elseif($notification->scheduled_at && $notification->scheduled_at > now())
                                        <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-500">Scheduled</span>
                                    @elseif($notification->is_active)
                                        <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-500">Ready</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500">
                                    {{ $notification->scheduled_at ? $notification->scheduled_at->format('M d, Y h:i A') : 'Immediate' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-admin.icon-action label="View notification" icon="fa-eye" tone="primary" data-open-notification-modal="view-notification-{{ $notification->id }}" />
                                        @if($canManageNotification)
                                            <x-admin.icon-action label="Edit notification" icon="fa-pen" data-open-notification-modal="edit-notification-{{ $notification->id }}" />
                                        @endif
                                        @if($canManageNotification && !$notification->sent_at && $notification->is_active)
                                            <form method="POST" action="{{ route('admin.notifications.send-now', $notification) }}" onsubmit="return confirm('Send this notification now?')">
                                                @csrf
                                                <x-admin.icon-action type="submit" label="Send notification now" icon="fa-paper-plane" tone="success" />
                                            </form>
                                        @endif
                                        @if($canManageNotification)
                                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                                @csrf
                                                @method('DELETE')
                                                <x-admin.icon-action type="submit" label="Delete notification" icon="fa-trash" tone="danger" />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center">
                                    <div class="mx-auto max-w-md rounded-2xl bg-[#f7f8fc] px-6 py-8">
                                        <p class="text-sm font-semibold text-slate-700">No notifications found</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-400">Create a notification to send or schedule your first mobile alert.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($notifications->hasPages())
                <div class="border-t border-slate-50 px-6 py-4">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

@foreach($notifications as $notification)
    @php
        $canManageNotification = $canSendAdminScopedNotifications
            || ($notification->type !== 'emergency' && $notification->target_audience !== 'admins');
        $status = $notification->sent_at
            ? 'Sent'
            : ($notification->scheduled_at && $notification->scheduled_at->isFuture()
                ? 'Scheduled'
                : ($notification->is_active ? 'Ready' : 'Inactive'));
        $typeClass = [
            'payment' => 'bg-indigo-50 text-indigo-400',
            'reminder' => 'bg-amber-50 text-amber-500',
            'announcement' => 'bg-emerald-50 text-emerald-500',
            'community' => 'bg-violet-50 text-violet-400',
            'emergency' => 'bg-rose-50 text-rose-400',
        ][$notification->type] ?? 'bg-slate-100 text-slate-500';
        $statusClass = [
            'Sent' => 'bg-emerald-50 text-emerald-500',
            'Scheduled' => 'bg-amber-50 text-amber-500',
            'Ready' => 'bg-sky-50 text-sky-500',
            'Inactive' => 'bg-slate-100 text-slate-500',
        ][$status];
        $audienceLabel = $audienceOptions[$notification->target_audience]
            ?? str($notification->target_audience)->replace('_', ' ')->title();
    @endphp

    <div id="view-notification-{{ $notification->id }}" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="view-notification-title-{{ $notification->id }}">
        <button type="button" data-close-notification-modal class="fixed inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

        <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
            <div class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                <div class="flex min-w-0 items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Notification Details</p>
                        <h2 id="view-notification-title-{{ $notification->id }}" class="mt-1 truncate text-2xl font-bold text-slate-700">{{ $notification->title }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClass }}">{{ ucfirst($notification->type) }}</span>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $status }}</span>
                            <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-400">{{ $audienceLabel }}</span>
                        </div>
                    </div>
                    <button type="button" data-close-notification-modal class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                    <div class="space-y-4">
                        <section class="rounded-2xl bg-[#f7f8fc] p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Message</p>
                            <div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $notification->message }}</div>
                        </section>

                        <section class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl bg-[#f7f8fc] p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Scheduled</p>
                                <p class="mt-1.5 text-sm font-medium text-slate-600">{{ $notification->scheduled_at?->format('F d, Y h:i A') ?: 'Immediate' }}</p>
                            </div>
                            <div class="rounded-2xl bg-[#f7f8fc] p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sent</p>
                                <p class="mt-1.5 text-sm font-medium text-slate-600">{{ $notification->sent_at?->format('F d, Y h:i A') ?: 'Not sent' }}</p>
                            </div>
                            <div class="rounded-2xl bg-[#f7f8fc] p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active</p>
                                <p class="mt-1.5 text-sm font-medium text-slate-600">{{ $notification->is_active ? 'Yes' : 'No' }}</p>
                            </div>
                            <div class="rounded-2xl bg-[#f7f8fc] p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created</p>
                                <p class="mt-1.5 text-sm font-medium text-slate-600">{{ $notification->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                            </div>
                        </section>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endforeach

@foreach($notifications as $notification)
    @php
        $canManageNotification = $canSendAdminScopedNotifications
            || ($notification->type !== 'emergency' && $notification->target_audience !== 'admins');
        $editTypeOptions = $typeOptions;

        if ($notification->type === 'announcement') {
            $editTypeOptions['announcement'] = 'Announcement';
        }

        if ($notification->type === 'community') {
            $editTypeOptions['community'] = 'Community';
        }

        $editingThisNotification = old('_editing_notification') && (string) old('_editing_notification') === (string) $notification->id;
    @endphp

    @if($canManageNotification)
        <div id="edit-notification-{{ $notification->id }}" class="fixed inset-0 z-50 {{ $editingThisNotification && $errors->any() ? 'flex' : 'hidden' }} items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="edit-notification-title-{{ $notification->id }}">
            <button type="button" data-close-notification-modal class="fixed inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
                <form method="POST" action="{{ route('admin.notifications.update', $notification) }}" class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_notification" value="{{ $notification->id }}">

                    <div class="flex min-w-0 items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Push Notification</p>
                            <h2 id="edit-notification-title-{{ $notification->id }}" class="mt-1 truncate text-2xl font-bold text-slate-700">{{ $notification->title }}</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Update the message, audience, timing, and active state.</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                Save Changes
                            </button>
                            <button type="button" data-close-notification-modal class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="max-h-[calc(100vh-12rem)] overflow-y-auto px-6 py-5">
                        @if($editingThisNotification && $errors->any())
                            <div class="mb-5 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-500">
                                Please review the notification details and try again.
                            </div>
                        @endif

                        <div class="grid min-w-0 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="edit-notification-title-input-{{ $notification->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Title</label>
                                <input type="text" name="title" id="edit-notification-title-input-{{ $notification->id }}" required value="{{ $editingThisNotification ? old('title', $notification->title) : $notification->title }}" class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                @if($editingThisNotification) @error('title') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <div class="md:col-span-2">
                                <label for="edit-notification-message-{{ $notification->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Message</label>
                                <textarea name="message" id="edit-notification-message-{{ $notification->id }}" rows="5" required class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ $editingThisNotification ? old('message', $notification->message) : $notification->message }}</textarea>
                                @if($editingThisNotification) @error('message') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="edit-notification-type-{{ $notification->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Type</label>
                                <select name="type" id="edit-notification-type-{{ $notification->id }}" required @disabled($notification->type === 'community') class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-70">
                                    @foreach($editTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($editingThisNotification ? old('type', $notification->type) : $notification->type) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if($editingThisNotification) @error('type') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="edit-notification-target-audience-{{ $notification->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Target Audience</label>
                                <select name="target_audience" id="edit-notification-target-audience-{{ $notification->id }}" required @disabled($notification->type === 'community') class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-70">
                                    @foreach($audienceOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($editingThisNotification ? old('target_audience', $notification->target_audience) : $notification->target_audience) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if($editingThisNotification) @error('target_audience') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="edit-notification-scheduled-at-{{ $notification->id }}" class="mb-1.5 block text-sm font-medium text-slate-600">Schedule</label>
                                <input type="datetime-local" name="scheduled_at" id="edit-notification-scheduled-at-{{ $notification->id }}" value="{{ $editingThisNotification ? old('scheduled_at', $notification->scheduled_at?->format('Y-m-d\TH:i')) : $notification->scheduled_at?->format('Y-m-d\TH:i') }}" class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                <p class="mt-1.5 text-xs text-slate-400">Leave empty for an immediate notification.</p>
                                @if($editingThisNotification) @error('scheduled_at') <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p> @enderror @endif
                            </div>

                            <label class="flex min-h-[3rem] items-center gap-3 rounded-2xl bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-600 md:mt-7">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($editingThisNotification ? old('is_active', $notification->is_active) : $notification->is_active) class="h-4 w-4 rounded border-0 bg-slate-200 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                                Active
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach

<div id="create-notification" class="fixed inset-0 z-50 {{ $creatingNotification ? 'flex' : 'hidden' }} items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="create-notification-title">
    <button type="button" data-close-notification-modal class="fixed inset-0 bg-slate-400/30 backdrop-blur-sm" aria-label="Close dialog"></button>

    <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
        <form method="POST" action="{{ route('admin.notifications.store') }}" class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.75rem] bg-white shadow-[0_24px_70px_rgba(148,163,184,0.30)]">
            @csrf
            <input type="hidden" name="_creating_notification" value="1">

            <div class="flex min-w-0 items-start justify-between gap-4 border-b border-slate-50 px-6 py-5">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Push Notification</p>
                    <h2 id="create-notification-title" class="mt-1 truncate text-2xl font-bold text-slate-700">Create Notification</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Send immediately or schedule a notification for a specific audience.</p>
                </div>
                <button type="button" data-close-notification-modal class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200" aria-label="Close dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                @if($creatingNotification && $errors->any())
                    <div class="mb-5 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-500">
                        Please review the notification details and try again.
                    </div>
                @endif

                <div class="grid min-w-0 gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="create-notification-title-input" class="mb-1.5 block text-sm font-medium text-slate-600">Title</label>
                        <input type="text" name="title" id="create-notification-title-input" required value="{{ old('title') }}" class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        @error('title')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="create-notification-message" class="mb-1.5 block text-sm font-medium text-slate-600">Message</label>
                        <textarea name="message" id="create-notification-message" rows="5" required class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="create-notification-type" class="mb-1.5 block text-sm font-medium text-slate-600">Type</label>
                        <select name="type" id="create-notification-type" required class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="create-notification-target-audience" class="mb-1.5 block text-sm font-medium text-slate-600">Target Audience</label>
                        <select name="target_audience" id="create-notification-target-audience" required class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            @foreach($audienceOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('target_audience') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('target_audience')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="create-notification-scheduled-at" class="mb-1.5 block text-sm font-medium text-slate-600">Schedule</label>
                        <input type="datetime-local" name="scheduled_at" id="create-notification-scheduled-at" value="{{ old('scheduled_at') }}" class="w-full min-w-0 rounded-2xl border-0 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <p class="mt-1.5 text-xs text-slate-400">Leave empty to send immediately.</p>
                        @error('scheduled_at')
                            <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex min-h-[3rem] items-center gap-3 rounded-2xl bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-600 md:mt-7">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="create-notification-active" value="1" @checked(old('is_active', true)) class="h-4 w-4 rounded border-0 bg-slate-200 text-indigo-400 focus:ring-2 focus:ring-indigo-200">
                        Active
                    </label>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-slate-50 bg-white px-6 py-4">
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-indigo-400 px-5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Create Notification
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.adminOnPageReady( () => {
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

        document.querySelectorAll('[data-open-notification-modal]').forEach((button) => {
            button.addEventListener('click', () => openModal(document.getElementById(button.dataset.openNotificationModal)));
        });

        document.querySelectorAll('[data-close-notification-modal]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            document.querySelectorAll('[role="dialog"].flex').forEach(closeModal);
        });

        if (document.querySelector('[role="dialog"].flex')) {
            document.body.classList.add('overflow-hidden');
        }
    });
</script>
@endsection
