@extends('admin.layouts.app')

@section('title', 'Edit Event')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Edit Event</h1>
            <p class="mt-2 text-sm text-[#6d7685]">Update event details, timing, registration window, and publishing status.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.events.update', $event) }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('admin.events.partials.form', ['event' => $event])
        </form>
    </div>
@endsection
