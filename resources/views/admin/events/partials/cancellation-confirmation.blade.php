<dialog id="event-cancel-{{ $event->id }}" data-event-cleanup-dialog aria-labelledby="event-cancel-title-{{ $event->id }}"
    class="w-full max-w-xl rounded-[1.75rem] bg-white p-6 text-slate-700 shadow-xl backdrop:bg-slate-400/30">
    <h2 id="event-cancel-title-{{ $event->id }}" class="text-2xl font-bold">Cancel event?</h2>
    <p class="mt-3 break-words font-semibold">{{ $event->title }}</p>
    <p class="mt-4 text-sm leading-6 text-slate-500">This cannot be undone here. Cancellation frees the Event Manager's schedule and stops new registrations, participant payments, check-ins, category starts and scans. Existing payments, results and checkpoint history are preserved. No automatic refund is issued.</p>
    <form method="POST" action="{{ route('admin.events.cancel', $event) }}" data-event-cleanup-form class="mt-5 space-y-5">
        @csrf
        <input type="hidden" name="cancellation_fingerprint" value="{{ $cleanup->fingerprint($event) }}">
        <div>
            <label for="cancel-reason-{{ $event->id }}" class="block text-sm font-medium">Reason <span class="text-rose-600">*</span></label>
            <textarea id="cancel-reason-{{ $event->id }}" name="cancellation_reason" required maxlength="1000" rows="3"
                class="mt-2 w-full rounded-2xl border border-slate-200 p-3 text-sm" aria-describedby="cancel-reason-help-{{ $event->id }}"></textarea>
            <p id="cancel-reason-help-{{ $event->id }}" class="mt-1 text-xs text-slate-500">This reason will be visible to participants. Do not include private information.</p>
        </div>
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="confirmation" value="1" required class="mt-1 h-4 w-4">
            <span>I understand this cancels the event, preserves its records and does not refund payments.</span>
        </label>
        <div class="flex flex-wrap justify-end gap-3">
            <button type="button" data-event-cleanup-close class="rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold">Keep event</button>
            <button type="submit" data-event-cleanup-submit class="rounded-full bg-rose-50 px-5 py-2.5 text-sm font-semibold text-rose-600">Cancel event</button>
        </div>
    </form>
</dialog>
