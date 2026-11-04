@extends('admin.layouts.app')

@section('title', 'Create Event')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Create Event</h1>
            <p class="mt-2 text-sm text-[#6d7685]">Complete the event and submit it for Admin review, or save a private draft to finish later. Only Admin approval publishes it.</p>
        </div>

        <form id="event-setup-form" data-event-setup-form method="POST" action="{{ route('admin.events.store') }}" enctype="multipart/form-data" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf
            @include('admin.events.partials.form', ['event' => null])
        </form>
    </div>
@endsection
