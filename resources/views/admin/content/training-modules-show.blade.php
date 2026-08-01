@extends('admin.layouts.app')

@section('title', 'Training Module Preview')

@section('content')
@php
    $typeClasses = [
        'warmup' => 'bg-sky-100 text-sky-800',
        'safety' => 'bg-rose-100 text-rose-800',
        'guideline' => 'bg-emerald-100 text-emerald-800',
        'program' => 'bg-amber-100 text-amber-800',
    ][$module->type] ?? 'bg-slate-100 text-slate-700';

    $difficultyClasses = [
        'beginner' => 'bg-emerald-100 text-emerald-800',
        'intermediate' => 'bg-amber-100 text-amber-800',
        'advanced' => 'bg-rose-100 text-rose-800',
    ][$module->difficulty_level] ?? 'bg-slate-100 text-slate-700';
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#7a8392]">Training Content</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#111827]">{{ $module->title }}</h1>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $module->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                    {{ $module->is_published ? 'Published' : 'Draft' }}
                </span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">{{ str($module->type)->replace('_', ' ')->title() }}</span>
                <span class="inline-flex rounded-full bg-[#eef1f4] px-3 py-1 text-xs font-semibold text-[#4f5a6a]">{{ $module->interest_type ?: 'General training' }}</span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $difficultyClasses }}">{{ ucfirst($module->difficulty_level) }}</span>
                <span class="inline-flex rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-semibold text-[#315fa8]">{{ $module->duration ? $module->duration.' min' : 'Duration not set' }}</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.content.training-modules') }}" class="inline-flex items-center justify-center rounded-xl border border-[#d9dee7] bg-white px-4 py-2.5 text-sm font-medium text-[#202733] transition hover:bg-[#f8f9fb]">
                Back
            </a>
            <a href="{{ route('admin.content.training-modules.edit', $module) }}" class="inline-flex items-center justify-center rounded-xl bg-[#111827] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1f2937]">
                Edit
            </a>
        </div>
    </div>

    <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Description</p>
        <p class="mt-3 text-sm leading-7 text-[#202733]">{{ $module->description }}</p>
    </section>

    <section class="rounded-3xl border border-[#d9dee7] bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Content</p>
        <div class="mt-4 whitespace-pre-line rounded-2xl border border-[#eef1f4] bg-[#f8f9fb] p-4 text-sm leading-7 text-[#202733]">{{ $module->content }}</div>
    </section>

    <section class="grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Training Focus</p>
            <p class="mt-2 text-sm font-medium text-[#202733]">{{ $module->interest_type ?: 'General training' }}</p>
        </div>
        <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Created</p>
            <p class="mt-2 text-sm font-medium text-[#202733]">{{ $module->created_at?->format('F d, Y h:i A') }}</p>
        </div>
        <div class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8392]">Updated</p>
            <p class="mt-2 text-sm font-medium text-[#202733]">{{ $module->updated_at?->format('F d, Y h:i A') }}</p>
        </div>
    </section>
</div>
@endsection
