@php
    $cancelledEvent = $event->isCancelled() ? $event : ($event->revisionOf?->isCancelled() ? $event->revisionOf : null);
    $reactivationDraft = $event->isReactivationDraft();
    $reactivationPolicy = app(\App\Services\EventReactivation::class);
    $reactivationReady = $reactivationDraft && $cancelledEvent && $reactivationPolicy->eligible($cancelledEvent)
        && $reactivationPolicy->matchesCancellation($event, $cancelledEvent);
    $activeReactivation = ($activeRevision ?? null)?->isReactivationDraft();
@endphp
<section class="space-y-4 rounded-3xl border border-indigo-100 bg-white p-6" aria-label="Event approval">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-700">{{ $event->reviewLabel() }}</h2>
            @if ($cancelledEvent)
                <p class="mt-2 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">Cancelled {{ $cancelledEvent->cancelled_at?->format('M j, Y g:i A') }}. {{ $cancelledEvent->cancellation_reason }} New registrations, participant payments and race operations are closed. Existing history remains available; no automatic refund was issued.</p>
            @else
                <p class="mt-2 text-sm text-slate-500">Saving a draft does not publish it. Admin approval publishes the reviewed setup immediately; registration dates and category rules still apply.</p>
            @endif
            @if ($event->revision_of_id)
                <a class="mt-2 inline-block text-sm text-indigo-600 underline" href="{{ route('admin.events.show', $event->revision_of_id) }}">View original event</a>
                <p class="mt-1 text-sm text-slate-500">{{ $reactivationDraft ? 'This is a private reactivation request. The original stays Cancelled and closed until Admin approval.' : 'This is a separate revision, not the published event. Participants continue to see the approved version.' }} Admin can review changes only while submitted for review.</p>
                @if ($reactivationDraft)
                    <p class="mt-2 text-sm text-slate-700"><strong>Reactivation reason:</strong> {{ $event->revisionRequest->cancellation_snapshot['request_reason'] ?? '' }}</p>
                    @if (! $reactivationReady && $event->review_status !== 'approved')
                        <p class="mt-2 text-sm text-amber-800">Reactivation is currently blocked. The original must be unarchived, still Cancelled with the same cancellation, and have no participant, payment, category start or race history. You may withdraw or discard this request; restoring an archive does not reactivate it.</p>
                    @endif
                @endif
            @endif
            @if ($event->review_reason)
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><strong>Changes requested:</strong> {{ $event->review_reason }}</p>
            @endif
        </div>
        @if (! $cancelledEvent || $reactivationDraft)
        <div class="flex flex-wrap gap-3">
            @if ($event->canEditSetup(auth()->user()))
                <a href="{{ route('admin.events.edit', $event) }}" class="rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700">Edit Draft</a>
                <form method="POST" action="{{ route('admin.events.review', [$event, 'submit']) }}">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <button class="rounded-full bg-indigo-500 px-4 py-3 text-sm font-semibold text-white">Submit for Review</button>
                </form>
            @elseif ($event->review_status === 'pending_review' && auth()->user()->managesAssignedEventsOnly())
                <form method="POST" action="{{ route('admin.events.review', [$event, 'withdraw']) }}">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <button class="rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700">Withdraw to Edit</button>
                </form>
            @endif
            @if ($event->revision_of_id && auth()->user()->managesAssignedEventsOnly() && in_array($event->review_status, ['draft', 'changes_requested'], true) && ($reactivationDraft || $event->canEditSetup(auth()->user())))
                <form method="POST" action="{{ route('admin.events.review', [$event, 'discard']) }}">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <button class="rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-600">{{ $reactivationDraft ? 'Discard Reactivation' : 'Discard Revision' }}</button>
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
        @endif
    </div>
    @if ($errors->any())
        <div role="alert" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif
    @if ($event->isCancelled())
        <p class="text-sm text-slate-500">Cancelled events can be archived by Admin now, or automatically after 30 days. Restoring an archive keeps the event Cancelled and starts a fresh 30-day archive window.</p>
        @if ($activeReactivation)
            <a href="{{ route('admin.events.show', $activeRevision) }}" class="inline-block rounded-full bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700">Open Reactivation Request</a>
        @elseif ($reactivationPolicy->canRequest($event, auth()->user()))
            <details class="rounded-xl border border-slate-100 p-4">
                <summary class="cursor-pointer font-semibold text-indigo-600">Request Reactivation</summary>
                <p class="mt-3 text-sm text-slate-500">Open a private draft, set a valid future schedule and submit it for Admin review. The original stays Cancelled until approved. Only events with no participant, payment, category start or race history are eligible.</p>
                <form method="POST" action="{{ route('admin.events.reactivation', $event) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                    <label for="reactivation-reason" class="block text-sm font-medium text-slate-700">Reason for reactivation <span class="text-rose-600">*</span></label>
                    <textarea id="reactivation-reason" name="reactivation_reason" maxlength="1000" required rows="3" class="w-full rounded-xl border border-slate-200 p-3 text-sm">{{ old('reactivation_reason') }}</textarea>
                    <label class="flex items-start gap-3 text-sm text-slate-600"><input type="checkbox" name="confirmation" value="1" required class="mt-1"><span>I understand this creates a private request and does not reopen the event.</span></label>
                    <button class="rounded-full bg-indigo-500 px-4 py-3 text-sm font-semibold text-white">Create Reactivation Draft</button>
                </form>
            </details>
        @else
            <p class="text-sm text-slate-500">The assigned Event Manager may request reactivation only while this event is unarchived and has no participant, payment, category start or race history. Otherwise, create a new event instead.</p>
        @endif
    @endif
    @if ((! $cancelledEvent || $reactivationDraft) && $event->review_status === 'pending_review' && auth()->user()->isSuperAdmin())
        <div class="grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-2">
            <form method="POST" action="{{ route('admin.events.review', [$event, 'approve']) }}">
                @csrf
                <input type="hidden" name="review_version" value="{{ $event->review_version }}">
                <p class="mb-3 text-sm text-slate-600">Review the event, categories, route, schedule and payment details below. {{ $reactivationDraft ? 'Approval reactivates the original event and publishes this future setup immediately. Cancellation history is retained. Eligibility is checked again on approval.' : 'Approval makes this version public immediately.' }}</p>
                @if (! $reactivationDraft || $reactivationReady)
                    <button class="rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white">{{ $reactivationDraft ? 'Approve Reactivation & Publish' : 'Approve & Publish' }}</button>
                @endif
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
            @include('admin.events.partials.submitted-setup', [
                'submittedSetup' => app(\App\Services\EventRevisionComparison::class)->submittedSetup($reviewSnapshot),
            ])
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
