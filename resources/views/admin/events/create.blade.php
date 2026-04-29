@extends('admin.layouts.app')

@section('title', 'Create Event')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Create Event</h1>
            <p class="mt-2 text-sm text-[#6d7685]">Set up a new race event with schedule, location, and registration details.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.events.store') }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf
            @include('admin.events.partials.form', ['event' => null])
        </form>
    </div>
@endsection
