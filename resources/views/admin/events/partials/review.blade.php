<section class="space-y-4 rounded-3xl border border-indigo-100 bg-white p-6" aria-label="Event approval">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-700">{{ $event->reviewLabel() }}</h2>
            <p class="mt-2 text-sm text-slate-500">Saving a draft does not publish it. Admin approval publishes the reviewed setup immediately; registration dates and category rules still apply.</p>
            @if ($event->revision_of_id)
                <a class="mt-2 inline-block text-sm text-indigo-600 underline" href="{{ route('admin.events.show', $event->revision_of_id) }}">View current published event</a>
                <p class="mt-1 text-sm text-slate-500">This is a separate revision, not the published event. Participants continue to see the approved version. Admin can review changes only while submitted for review.</p>
            @endif
            @if ($event->review_reason)
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><strong>Changes requested:</strong> {{ $event->review_reason }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-3">
            @if ($event->canEditSetup(auth()->user()))
                <a href="{{ route('admin.events.edit', $event) }}" class="rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700">Edit Draft</a>
                <form method="POST" action="{{ route('admin.events.review', [$event, 'submit']) }}">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <button class="rounded-full bg-indigo-500 px-4 py-3 text-sm font-semibold text-white">Submit for Review</button>
                </form>
                @if ($event->revision_of_id)
                    <form method="POST" action="{{ route('admin.events.review', [$event, 'discard']) }}">
                        @csrf
                        <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                        <button class="rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-600">Discard Revision</button>
                    </form>
                @endif
            @elseif ($event->review_status === 'pending_review' && auth()->user()->managesAssignedEventsOnly())
                <form method="POST" action="{{ route('admin.events.review', [$event, 'withdraw']) }}">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <button class="rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700">Withdraw to Edit</button>
                </form>
            @endif
            @if ($activeRevision ?? null)
                <a href="{{ route('admin.events.show', $activeRevision) }}" class="rounded-full bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700">Open Active Revision</a>
            @elseif (auth()->user()->managesAssignedEventsOnly() && $event->isReviewPublished() && $event->effective_status === 'upcoming')
                <form method="POST" action="{{ route('admin.events.revision', $event) }}">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <button class="rounded-full bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700">Create Reviewed Revision</button>
                </form>
            @endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif
    @if ($event->review_status === 'pending_review' && auth()->user()->isSuperAdmin())
        <div class="grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-2">
            <form method="POST" action="{{ route('admin.events.review', [$event, 'approve']) }}">
                @csrf
                <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                <p class="mb-3 text-sm text-slate-600">Review the event, categories, route, schedule and payment details below. Approval makes this version public immediately.</p>
                <button class="rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white">Approve &amp; Publish</button>
            </form>
            <form method="POST" action="{{ route('admin.events.review', [$event, 'return']) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                <label for="review-reason" class="block text-sm font-medium text-slate-700">Reason / required changes</label>
                <textarea id="review-reason" name="reason" maxlength="2000" required rows="3" class="w-full rounded-xl border border-slate-200 p-3 text-sm">{{ old('reason') }}</textarea>
                <button class="rounded-full bg-amber-100 px-5 py-3 text-sm font-semibold text-amber-900">Return for Changes</button>
            </form>
        </div>
    @endif
    @if ($event->review_status === 'pending_review' && ! $event->revision_of_id)
        <details class="rounded-xl border border-slate-100 p-4">
            <summary class="cursor-pointer font-semibold text-slate-700">Complete submitted setup, routes and payment options</summary>
            @foreach ($reviewSnapshot as $section => $items)
                <h3 class="mt-4 font-medium text-slate-700">{{ str($section)->headline() }}</h3>
                <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endforeach
        </details>
    @endif
    @if ($revisionComparison ?? null)
        <details class="rounded-xl border border-slate-100 p-4">
            <summary class="cursor-pointer font-semibold text-slate-700">Compare current and proposed setup</summary>
            @include('admin.events.partials.revision-comparison')
        </details>
    @endif
    @if ($event->reviews->isNotEmpty())
        <details class="text-sm text-slate-600">
            <summary class="cursor-pointer font-medium">Review history</summary>
            <ol class="mt-3 space-y-2">
                @foreach ($event->reviews as $review)
                    <li>{{ $review->created_at->format('M j, Y g:i A') }} — {{ str($review->action)->replace('_', ' ')->title() }} by {{ $review->actor?->name ?? 'Former account' }} (version {{ $review->review_version }})@if ($review->reason): {{ $review->reason }}@endif</li>
                @endforeach
            </ol>
        </details>
    @endif
    @if (($revisionHistory ?? collect())->isNotEmpty())
        <details class="text-sm text-slate-600"><summary class="cursor-pointer font-medium">Revision history</summary>
            <ul class="mt-3 space-y-2">@foreach ($revisionHistory as $revision)
                <li><a class="text-indigo-600 underline" href="{{ route('admin.events.review-history', $revision) }}">Revision #{{ $revision->id }} — Submitted review history</a></li>
            @endforeach</ul>
        </details>
    @endif
</section>
