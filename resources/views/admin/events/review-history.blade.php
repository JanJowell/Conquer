@extends('admin.layouts.app')

@section('title', 'Submitted Review History')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-700">Submitted review history</h1>
            <p class="mt-2 text-sm text-slate-500">Read-only records of submissions and review decisions. Unsubmitted edits are private to the Event Manager.</p>
        </div>
        <a href="{{ route('admin.events.show', $backEventId) }}" class="rounded-full bg-white px-5 py-3 text-sm font-semibold text-indigo-600">Back to event</a>
    </div>
    @foreach ($entries as $entry)
        <section class="rounded-2xl border border-slate-100 bg-white p-5">
            <h2 class="font-semibold text-slate-700">{{ str($entry['review']->action)->replace('_', ' ')->title() }} — version {{ $entry['review']->review_version }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $entry['review']->created_at->format('M j, Y g:i A') }} · {{ $entry['review']->actor?->name ?? 'Former account' }}</p>
            @if ($entry['review']->reason)
                <p class="mt-3 whitespace-pre-line rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $entry['review']->reason }}</p>
            @endif
            @if ($entry['comparison'])
                <details class="mt-4">
                    <summary class="cursor-pointer font-medium text-indigo-600">View saved submission</summary>
                    @include('admin.events.partials.revision-comparison', ['revisionComparison' => $entry['comparison'], 'historicalComparison' => true])
                </details>
            @elseif ($entry['review']->action === 'submit')
                <p class="mt-3 text-sm text-slate-500">This older submission has no saved setup snapshot. Its review metadata is preserved; editable draft data is not substituted.</p>
            @endif
        </section>
    @endforeach
</div>
@endsection
