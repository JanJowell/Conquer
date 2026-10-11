@extends('admin.layouts.app')

@section('title', 'Notification Details')

@section('content')
@php
    $status = $notification->sent_at
        ? ['label' => 'Sent', 'classes' => 'bg-emerald-50 text-emerald-500']
        : ($notification->scheduled_at && $notification->scheduled_at->isFuture()
            ? ['label' => 'Scheduled', 'classes' => 'bg-amber-50 text-amber-500']
            : ($notification->is_active
                ? ['label' => 'Ready', 'classes' => 'bg-indigo-50 text-indigo-400']
                : ['label' => 'Inactive', 'classes' => 'bg-slate-100 text-slate-500']));

    $typeClasses = [
        'payment' => 'bg-indigo-50 text-indigo-400',
        'reminder' => 'bg-amber-50 text-amber-500',
        'announcement' => 'bg-emerald-50 text-emerald-500',
        'emergency' => 'bg-rose-50 text-rose-400',
    ][$notification->type] ?? 'bg-slate-100 text-slate-500';

    $audienceLabel = $notification->target_audience === 'runners'
        ? 'Runners'
        : str($notification->target_audience)->replace('_', ' ')->title();

    $canManageNotification = auth()->user()->hasAdminRole([
        \App\Models\User::ROLE_SUPER_ADMIN,
        \App\Models\User::ROLE_EVENT_MANAGER,
    ]) || ($notification->type !== 'emergency' && $notification->target_audience !== 'admins');
@endphp

<div class="min-h-screen bg-[#f7f8fc] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Push Notification</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-700">{{ $notification->title }}</h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">{{ $status['label'] }}</span>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">{{ ucfirst($notification->type) }}</span>
                    <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-400">{{ $audienceLabel }}</span>
                </div>
            </div>

            @if($canManageNotification)
                <a href="{{ route('admin.notifications.edit', $notification) }}" class="inline-flex items-center justify-center rounded-full bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Edit
                </a>
            @endif
        </div>

        <section class="rounded-[1.75rem] bg-white p-6 shadow-[0_8px_24px_rgba(148,163,184,0.12)]">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Message</p>
                    <div class="mt-3 whitespace-pre-line rounded-2xl bg-[#f7f8fc] p-4 text-sm leading-6 text-slate-600">{{ $notification->message }}</div>
                </div>

                <div class="space-y-3">
                    <div class="rounded-2xl bg-[#f7f8fc] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Scheduled</p>
                        <p class="mt-1.5 text-sm text-slate-600">{{ $notification->scheduled_at?->format('F d, Y h:i A') ?: 'Immediate' }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#f7f8fc] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sent At</p>
                        <p class="mt-1.5 text-sm text-slate-600">{{ $notification->sent_at?->format('F d, Y h:i A') ?: 'Not sent' }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#f7f8fc] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active</p>
                        <p class="mt-1.5 text-sm text-slate-600">{{ $notification->is_active ? 'Yes' : 'No' }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#f7f8fc] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created</p>
                        <p class="mt-1.5 text-sm text-slate-600">{{ $notification->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection