@extends('admin.layouts.app')

@section('title', 'Edit Training Module')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Training Content</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">Edit Training Module</h1>
        <p class="mt-2 text-sm text-[#6d7685]">Update the lesson content, settings, and publication status for this module.</p>
    </div>

    <a href="{{ route('admin.content.training-modules') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
        Back to modules
    </a>
</div>

<div class="rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
    <form method="POST" action="{{ route('admin.content.training-modules.update', $module) }}">
        @csrf
        @method('PUT')

        <div class="space-y-8 p-6">
            @include('admin.content.partials.training-module-form', ['module' => $module])
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-[#eef1f4] bg-[#fbfcfd] px-6 py-4">
            <a href="{{ route('admin.content.training-modules') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
