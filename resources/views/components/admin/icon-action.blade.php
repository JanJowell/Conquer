@props([
    'label',
    'icon',
    'href' => null,
    'tone' => 'neutral',
    'type' => 'button',
])

@php
    $toneClasses = match ($tone) {
        'primary' => 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100 focus-visible:ring-indigo-300',
        'success' => 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 focus-visible:ring-emerald-300',
        'danger' => 'bg-rose-50 text-rose-600 hover:bg-rose-100 focus-visible:ring-rose-300',
        'warning' => 'bg-amber-50 text-amber-600 hover:bg-amber-100 focus-visible:ring-amber-300',
        default => 'bg-slate-100 text-slate-600 hover:bg-slate-200 focus-visible:ring-slate-300',
    };

    $classes = "admin-icon-action {$toneClasses}";
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        aria-label="{{ $label }}"
        data-tooltip="{{ $label }}"
        title="{{ $label }}"
        {{ $attributes->class($classes) }}
    >
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        aria-label="{{ $label }}"
        data-tooltip="{{ $label }}"
        title="{{ $label }}"
        {{ $attributes->class($classes) }}
    >
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
