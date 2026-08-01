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
        'runners' => 'All Runners',
        'participants' => 'Event Participants',
    ];

    if ($canSendAdminScopedNotifications) {
        $typeOptions['emergency'] = 'Emergency';
        $audienceOptions['admins'] = 'Admin Users';
    }

    $creatingNotification = old('_creating_notification') === '1' && $errors->any();
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Communications</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Push Notifications</h1>
            <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Create, schedule, and send mobile push alerts for runners, participants, and admin teams.</p>
        </div>

        <button type="button" data-open-notification-modal="create-notification" class="inline-flex items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#232b39]">
            <i class="fas fa-plus mr-2 text-xs"></i>
            Create Notification
        </button>
    </div>

    <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/35 shadow-[0_18px_55px_rgba(15,23,42,0.08)] backdrop-blur-2xl">
        <div class="overflow-x-auto">
            <table class="min-w-full border-separate border-spacing-y-2 px-3 py-2">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                        <th class="px-4 py-3">Notification</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Audience</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Scheduled</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-[#202733]">
                    @forelse($notifications as $notification)
                        @php
                            $canManageNotification = $canSendAdminScopedNotifications
                                || ($notification->type !== 'emergency' && $notification->target_audience !== 'admins');

                            $typeClass = [
                                'payment' => 'border-sky-200 bg-sky-50 text-sky-700',
                                'reminder' => 'border-amber-200 bg-amber-50 text-amber-800',
                                'announcement' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'community' => 'border-violet-200 bg-violet-50 text-violet-700',
                                'emergency' => 'border-rose-200 bg-rose-50 text-rose-700',
                            ][$notification->type] ?? 'border-slate-200 bg-slate-50 text-slate-600';

                            $audienceClass = [
                                'all' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                'runners' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'participants' => 'border-sky-200 bg-sky-50 text-sky-700',
                                'admins' => 'border-slate-200 bg-slate-50 text-slate-700',
                            ][$notification->target_audience] ?? 'border-slate-200 bg-slate-50 text-slate-600';
                        @endphp
                        <tr class="align-top">
                            <td class="rounded-l-2xl border-y border-l border-white/60 bg-white/45 px-4 py-4 backdrop-blur-xl">
                                <p class="font-semibold text-[#151b26]">{{ $notification->title }}</p>
                                <p class="mt-1 max-w-md text-xs leading-5 text-[#6d7685]">{{ \Illuminate\Support\Str::limit($notification->message, 80) }}</p>
                            </td>
                            <td class="border-y border-white/60 bg-white/45 px-4 py-4 backdrop-blur-xl">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $typeClass }}">
                                    {{ ucfirst($notification->type) }}
                                </span>
                            </td>
                            <td class="border-y border-white/60 bg-white/45 px-4 py-4 backdrop-blur-xl">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $audienceClass }}">
                                    {{ $notification->target_audience === 'runners' ? 'Runners' : str($notification->target_audience)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td class="border-y border-white/60 bg-white/45 px-4 py-4 backdrop-blur-xl">
                                @if($notification->sent_at)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Sent</span>
                                @elseif($notification->scheduled_at && $notification->scheduled_at > now())
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Scheduled</span>
                                @elseif($notification->is_active)
                                    <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Ready</span>
                                @else
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Inactive</span>
                                @endif
                            </td>
                            <td class="border-y border-white/60 bg-white/45 px-4 py-4 text-sm text-[#6d7685] backdrop-blur-xl">
                                {{ $notification->scheduled_at ? $notification->scheduled_at->format('M d, Y h:i A') : 'Immediate' }}
                            </td>
                            <td class="rounded-r-2xl border-y border-r border-white/60 bg-white/45 px-4 py-4 text-right backdrop-blur-xl">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button" data-open-notification-modal="view-notification-{{ $notification->id }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#315fa8] shadow-sm backdrop-blur-xl transition hover:bg-white/70" title="View notification">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                    @if($canManageNotification)
                                        <button type="button" data-open-notification-modal="edit-notification-{{ $notification->id }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#5e6878] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#202733]" title="Edit notification">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    @endif
                                    @if($canManageNotification && !$notification->sent_at && $notification->is_active)
                                        <form method="POST" action="{{ route('admin.notifications.send-now', $notification) }}" onsubmit="return confirm('Send this notification now?')">
                                            @csrf
                                            <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm transition hover:bg-emerald-100" title="Send now">
                                                <i class="fas fa-paper-plane text-xs"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if($canManageNotification)
                                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 shadow-sm transition hover:bg-rose-100" title="Delete notification">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center">
                                <div class="mx-auto max-w-md rounded-2xl border border-dashed border-white/70 bg-white/35 px-6 py-8 shadow-sm backdrop-blur-xl">
                                    <p class="text-sm font-semibold text-[#202733]">No notifications found</p>
                                    <p class="mt-2 text-sm leading-6 text-[#6d7685]">Create a notification to send or schedule your first mobile alert.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($notifications->hasPages())
            <div class="border-t border-white/60 bg-white/30 px-6 py-4 backdrop-blur-xl">
                {{ $notifications->links() }}
            </div>
        @endif
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
            'payment' => 'border-sky-200 bg-sky-50 text-sky-700',
            'reminder' => 'border-amber-200 bg-amber-50 text-amber-800',
            'announcement' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'community' => 'border-violet-200 bg-violet-50 text-violet-700',
            'emergency' => 'border-rose-200 bg-rose-50 text-rose-700',
        ][$notification->type] ?? 'border-slate-200 bg-slate-50 text-slate-600';
        $statusClass = [
            'Sent' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'Scheduled' => 'border-amber-200 bg-amber-50 text-amber-800',
            'Ready' => 'border-sky-200 bg-sky-50 text-sky-700',
            'Inactive' => 'border-slate-200 bg-slate-50 text-slate-600',
        ][$status];
        $audienceLabel = $notification->target_audience === 'runners'
            ? 'Runners'
            : str($notification->target_audience)->replace('_', ' ')->title();
    @endphp

    <div id="view-notification-{{ $notification->id }}" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto text-left" role="dialog" aria-modal="true" aria-labelledby="view-notification-title-{{ $notification->id }}">
        <button type="button" data-close-notification-modal class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

        <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
            <div class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Notification Details</p>
                        <h2 id="view-notification-title-{{ $notification->id }}" class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#151b26]">{{ $notification->title }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $typeClass }}">{{ ucfirst($notification->type) }}</span>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $status }}</span>
                            <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $audienceLabel }}</span>
                        </div>
                    </div>
                    <button type="button" data-close-notification-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#151b26]" aria-label="Close dialog">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                    <div class="space-y-5">
                        <section class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Message</p>
                            <div class="mt-4 whitespace-pre-line rounded-2xl border border-white/60 bg-white/50 p-4 text-sm leading-7 text-[#202733]">{{ $notification->message }}</div>
                        </section>

                        <section class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Scheduled</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $notification->scheduled_at?->format('F d, Y h:i A') ?: 'Immediate' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Sent</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $notification->sent_at?->format('F d, Y h:i A') ?: 'Not sent' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Active</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $notification->is_active ? 'Yes' : 'No' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/60 bg-white/45 p-5 shadow-sm backdrop-blur-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Created</p>
                                <p class="mt-2 text-sm font-medium text-[#202733]">{{ $notification->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
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
            <button type="button" data-close-notification-modal class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

            <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
                <form method="POST" action="{{ route('admin.notifications.update', $notification) }}" class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_notification" value="{{ $notification->id }}">

                    <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Push Notification</p>
                            <h2 id="edit-notification-title-{{ $notification->id }}" class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#151b26]">{{ $notification->title }}</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6d7685]">Update the message, audience, timing, and active state.</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#151b26] px-4 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                                Save Changes
                            </button>
                            <button type="button" data-close-notification-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#151b26]" aria-label="Close dialog">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="max-h-[calc(100vh-12rem)] overflow-y-auto px-6 py-5">
                        @if($editingThisNotification && $errors->any())
                            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                                Please review the notification details and try again.
                            </div>
                        @endif

                        <div class="grid min-w-0 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="edit-notification-title-input-{{ $notification->id }}" class="mb-2 block text-sm font-medium text-[#3d4757]">Title</label>
                                <input type="text" name="title" id="edit-notification-title-input-{{ $notification->id }}" required value="{{ $editingThisNotification ? old('title', $notification->title) : $notification->title }}" class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                @if($editingThisNotification) @error('title') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <div class="md:col-span-2">
                                <label for="edit-notification-message-{{ $notification->id }}" class="mb-2 block text-sm font-medium text-[#3d4757]">Message</label>
                                <textarea name="message" id="edit-notification-message-{{ $notification->id }}" rows="5" required class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ $editingThisNotification ? old('message', $notification->message) : $notification->message }}</textarea>
                                @if($editingThisNotification) @error('message') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="edit-notification-type-{{ $notification->id }}" class="mb-2 block text-sm font-medium text-[#3d4757]">Type</label>
                                <select name="type" id="edit-notification-type-{{ $notification->id }}" required @disabled($notification->type === 'community') class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70 disabled:cursor-not-allowed disabled:opacity-70">
                                    @foreach($editTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($editingThisNotification ? old('type', $notification->type) : $notification->type) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if($editingThisNotification) @error('type') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="edit-notification-target-audience-{{ $notification->id }}" class="mb-2 block text-sm font-medium text-[#3d4757]">Target Audience</label>
                                <select name="target_audience" id="edit-notification-target-audience-{{ $notification->id }}" required @disabled($notification->type === 'community') class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70 disabled:cursor-not-allowed disabled:opacity-70">
                                    @foreach($audienceOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($editingThisNotification ? old('target_audience', $notification->target_audience) : $notification->target_audience) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if($editingThisNotification) @error('target_audience') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <div>
                                <label for="edit-notification-scheduled-at-{{ $notification->id }}" class="mb-2 block text-sm font-medium text-[#3d4757]">Schedule</label>
                                <input type="datetime-local" name="scheduled_at" id="edit-notification-scheduled-at-{{ $notification->id }}" value="{{ $editingThisNotification ? old('scheduled_at', $notification->scheduled_at?->format('Y-m-d\TH:i')) : $notification->scheduled_at?->format('Y-m-d\TH:i') }}" class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                                <p class="mt-2 text-xs text-[#6d7685]">Leave empty for an immediate notification.</p>
                                @if($editingThisNotification) @error('scheduled_at') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror @endif
                            </div>

                            <label class="flex min-h-[3.25rem] items-center gap-3 rounded-xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-[#202733] shadow-sm backdrop-blur-xl md:mt-7">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($editingThisNotification ? old('is_active', $notification->is_active) : $notification->is_active) class="h-4 w-4 rounded border-[#cfd5de] text-[#151b26] focus:ring-[#151b26]">
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
    <button type="button" data-close-notification-modal class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm" aria-label="Close dialog"></button>

    <div class="relative z-10 flex min-h-screen w-full items-start justify-center px-4 py-8 sm:px-6">
        <form method="POST" action="{{ route('admin.notifications.store') }}" class="w-full max-w-4xl min-w-0 overflow-hidden rounded-[1.5rem] border border-white/60 bg-[#eaf2f9]/85 shadow-[0_28px_90px_rgba(15,23,42,0.28)] backdrop-blur-2xl ring-1 ring-white/40">
            @csrf
            <input type="hidden" name="_creating_notification" value="1">

            <div class="flex min-w-0 items-start justify-between gap-4 border-b border-white/50 bg-white/40 px-6 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.70)] backdrop-blur-xl">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7a8495]">Push Notification</p>
                    <h2 id="create-notification-title" class="mt-2 truncate text-2xl font-semibold tracking-tight text-[#151b26]">Create Notification</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6d7685]">Send immediately or schedule a notification for a specific audience.</p>
                </div>
                <button type="button" data-close-notification-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/60 bg-white/45 text-[#6d7685] shadow-sm backdrop-blur-xl transition hover:bg-white/70 hover:text-[#151b26]" aria-label="Close dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="max-h-[calc(100vh-16rem)] overflow-y-auto px-6 py-5">
                @if($creatingNotification && $errors->any())
                    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                        Please review the notification details and try again.
                    </div>
                @endif

                <div class="grid min-w-0 gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="create-notification-title-input" class="mb-2 block text-sm font-medium text-[#3d4757]">Title</label>
                        <input type="text" name="title" id="create-notification-title-input" required value="{{ old('title') }}" class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                        @error('title')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="create-notification-message" class="mb-2 block text-sm font-medium text-[#3d4757]">Message</label>
                        <textarea name="message" id="create-notification-message" rows="5" required class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="create-notification-type" class="mb-2 block text-sm font-medium text-[#3d4757]">Type</label>
                        <select name="type" id="create-notification-type" required class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="create-notification-target-audience" class="mb-2 block text-sm font-medium text-[#3d4757]">Target Audience</label>
                        <select name="target_audience" id="create-notification-target-audience" required class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                            @foreach($audienceOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('target_audience') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('target_audience')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="create-notification-scheduled-at" class="mb-2 block text-sm font-medium text-[#3d4757]">Schedule</label>
                        <input type="datetime-local" name="scheduled_at" id="create-notification-scheduled-at" value="{{ old('scheduled_at') }}" class="w-full min-w-0 rounded-xl border border-white/60 bg-white/50 px-4 py-3 text-[#151b26] shadow-sm outline-none backdrop-blur-xl transition focus:border-sky-300 focus:ring-4 focus:ring-sky-100/70">
                        <p class="mt-2 text-xs text-[#6d7685]">Leave empty to send immediately.</p>
                        @error('scheduled_at')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex min-h-[3.25rem] items-center gap-3 rounded-xl border border-white/60 bg-white/45 px-4 py-3 text-sm font-semibold text-[#202733] shadow-sm backdrop-blur-xl md:mt-7">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="create-notification-active" value="1" @checked(old('is_active', true)) class="h-4 w-4 rounded border-[#cfd5de] text-[#151b26] focus:ring-[#151b26]">
                        Active
                    </label>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-white/50 bg-white/40 px-6 py-4 backdrop-blur-xl">
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#151b26] px-5 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-[#232b39]">
                    Create Notification
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
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
