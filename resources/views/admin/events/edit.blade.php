@extends('admin.layouts.app')

@section('title', 'Edit Event')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Edit Event</h1>
                <p class="mt-2 text-sm text-[#6d7685]">Update event details, timing, and registration window. The system detects the lifecycle status automatically.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.events.show', $event) }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    View Details
                </a>
                <a href="{{ route('admin.events.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-5 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    All Events
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if (($event->registrations_count ?? 0) > 0 || ($event->race_results_count ?? 0) > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                This event already has {{ number_format($event->registrations_count ?? 0) }} registrations and {{ number_format($event->race_results_count ?? 0) }} results. Be careful when changing schedule or registration deadline.
            </div>
        @endif

        <section class="grid gap-3 md:grid-cols-3">
            <a href="{{ route('admin.categories.index', ['event_id' => $event->id]) }}" class="rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                Categories
                <span class="mt-1 block text-xs font-medium text-[#6d7685]">{{ number_format($event->categories_count ?? 0) }} configured</span>
            </a>
            <a href="{{ route('admin.participants.index', ['event_id' => $event->id]) }}" class="rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                Participants
                <span class="mt-1 block text-xs font-medium text-[#6d7685]">{{ number_format($event->registrations_count ?? 0) }} registrations</span>
            </a>
            <a href="{{ route('admin.results.index', ['event_id' => $event->id]) }}" class="rounded-2xl border border-[#d9dee7] bg-white px-4 py-3 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                Results
                <span class="mt-1 block text-xs font-medium text-[#6d7685]">{{ number_format($event->race_results_count ?? 0) }} published</span>
            </a>
        </section>

        <form method="POST" action="{{ route('admin.events.update', $event) }}" enctype="multipart/form-data" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('admin.events.partials.form', ['event' => $event])
        </form>
    </div>
@endsection
