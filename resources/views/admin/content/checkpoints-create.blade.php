@extends('admin.layouts.app')

@section('title', 'Create Checkpoint')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Course Setup</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Create Checkpoint</h1>
            <p class="mt-2 text-sm text-[#6d7685]">Add hydration, medical, route, or finish points for the selected event.</p>
        </div>

        <form method="POST" action="{{ route('admin.content.checkpoints.store') }}" class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
            @csrf
            @include('admin.content.partials.checkpoint-form', ['checkpoint' => null])
        </form>
    </div>
@endsection
