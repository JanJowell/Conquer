@extends('admin.layouts.app')

@section('title', 'Notification Details')

@section('content')
@php
    $status = $notification->sent_at
        ? ['label' => 'Sent', 'classes' => 'bg-green-100 text-green-800']
        : ($notification->scheduled_at && $notification->scheduled_at->isFuture()
            ? ['label' => 'Scheduled', 'classes' => 'bg-yellow-100 text-yellow-800']
            : ($notification->is_active
                ? ['label' => 'Ready', 'classes' => 'bg-blue-100 text-blue-800']
                : ['label' => 'Inactive', 'classes' => 'bg-gray-100 text-gray-800']));

    $typeClasses = [
        'payment' => 'bg-blue-100 text-blue-800',
        'reminder' => 'bg-yellow-100 text-yellow-800',
        'announcement' => 'bg-green-100 text-green-800',
        'emergency' => 'bg-red-100 text-red-800',
    ][$notification->type] ?? 'bg-gray-100 text-gray-800';

    $audienceLabel = $notification->target_audience === 'runners'
        ? 'Runners'
        : str($notification->target_audience)->replace('_', ' ')->title();

    $canManageNotification = auth()->user()->hasAdminRole([
        \App\Models\User::ROLE_SUPER_ADMIN,
        \App\Models\User::ROLE_EVENT_MANAGER,
    ]) || ($notification->type !== 'emergency' && $notification->target_audience !== 'admins');
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Push Notification</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">{{ $notification->title }}</h1>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">{{ $status['label'] }}</span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">{{ ucfirst($notification->type) }}</span>
                <span class="inline-flex rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-semibold text-[#315fa8]">{{ $audienceLabel }}</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            @if($canManageNotification)
                <a href="{{ route('admin.notifications.edit', $notification) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                    Edit
                </a>
            @endif
        </div>
    </div>

    <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Message</p>
                <div class="mt-3 whitespace-pre-line rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4 text-sm leading-6 text-[#202733]">{{ $notification->message }}</div>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Scheduled</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $notification->scheduled_at?->format('F d, Y h:i A') ?: 'Immediate' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Sent At</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $notification->sent_at?->format('F d, Y h:i A') ?: 'Not sent' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Active</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $notification->is_active ? 'Yes' : 'No' }}</p>
                </div>
                <div class="rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Created</p>
                    <p class="mt-2 text-sm text-[#202733]">{{ $notification->created_at?->format('F d, Y h:i A') ?: 'N/A' }}</p>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
