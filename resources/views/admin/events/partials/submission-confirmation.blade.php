@php($isReviewRevision = filled($event?->revision_of_id))

<dialog data-event-review-confirmation aria-modal="true" aria-labelledby="event-review-confirmation-title" aria-describedby="event-review-confirmation-description"
    class="w-[min(92vw,32rem)] max-h-[90dvh] overflow-y-auto rounded-3xl border border-[#d9dee7] bg-white p-6 text-[#151b26] shadow-xl backdrop:bg-slate-950/40 backdrop:backdrop-blur-sm sm:p-8">
    <h2 id="event-review-confirmation-title" class="text-xl font-semibold tracking-tight">
        {{ $isReviewRevision ? 'Submit changes for review?' : 'Submit event for review?' }}
    </h2>
    <div id="event-review-confirmation-description" class="mt-3 space-y-3 text-sm leading-6 text-[#6d7685]">
        <p>{{ $isReviewRevision ? 'Your proposed changes will be sent to Admin for review.' : 'Your event will be sent to Admin for review.' }} Editing will be locked while it is pending unless you withdraw the submission.</p>
        <p>{{ $isReviewRevision ? 'The currently published event will stay unchanged until Admin approves these changes.' : 'The event will only be published after Admin approval.' }}</p>
    </div>
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
        <button type="button" data-event-review-cancel autofocus
            class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-5 py-3 text-sm font-semibold transition hover:bg-[#f7f8fa]">
            Keep Editing
        </button>
        <button type="button" data-event-review-confirm
            class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-[#151b26] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#232b39] disabled:cursor-wait disabled:opacity-60">
            Submit for Review
        </button>
    </div>
</dialog>
