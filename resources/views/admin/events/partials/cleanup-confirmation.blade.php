@php
    $cleanupLabel = match ($cleanupAction) { 'delete' => 'Delete empty draft', 'archive' => 'Archive event', 'restore' => 'Restore event' };
    $cleanupRoute = match ($cleanupAction) { 'delete' => 'admin.events.cleanup.destroy', 'archive' => 'admin.events.archive', 'restore' => 'admin.events.restore' };
@endphp
<dialog id="event-cleanup-{{ $event->id }}" data-event-cleanup-dialog aria-labelledby="event-cleanup-title-{{ $event->id }}"
    class="w-full max-w-xl rounded-[1.75rem] bg-white p-6 text-slate-700 shadow-[0_24px_70px_rgba(148,163,184,0.30)] backdrop:bg-slate-400/30 backdrop:backdrop-blur-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 id="event-cleanup-title-{{ $event->id }}" class="text-2xl font-bold tracking-tight">{{ $cleanupLabel }}?</h2>
            <p class="mt-3 break-words font-semibold">{{ $event->title }}</p>
        </div>
        <button type="button" data-event-cleanup-close class="rounded-full bg-slate-50 px-3 py-2 text-sm" aria-label="Close confirmation">Close</button>
    </div>
    <p class="mt-4 text-sm leading-6 text-slate-500">
        @if ($cleanupAction === 'delete')
            This permanently deletes this empty, never-submitted draft and its unused automatic draft announcement. This cannot be undone. Events with setup, submissions, or participant history cannot be deleted.
        @elseif ($cleanupAction === 'archive')
            @if ($event->isCancelled())
            This moves the cancelled event to Archived Events. It stays Cancelled and hidden from mobile Browse and Search. Participant activity, payments, results, certificates and checkpoint history are preserved. Restoring it does not reactivate it.
            @else
            This hides the completed event from the default management list and mobile Browse and Search. It does not delete the event. Participant activity, payments, results, certificates, and checkpoint history remain available. You can restore it from Archived Events.
            @endif
        @else
            @if ($event->isCancelled())
            This returns the event to the management list only. It stays Cancelled: registration, payment and race operations remain closed, and mobile Browse and Search remain hidden. Automatic archiving becomes eligible again 30 days after restoration. Reactivation requires a separate eligible request and Admin approval.
            @else
            This returns the event to the default management list and makes it eligible for mobile Browse and Search again. Its completed status, approval, participant records, and history will not change. When automatic archiving is enabled, this completed event becomes eligible again 30 days after restoration. Restoring an older event also brings it into this rule.
            @endif
        @endif
    </p>
    <form method="POST" action="{{ route($cleanupRoute, $event) }}" data-event-cleanup-form class="mt-5 space-y-5">
        @csrf
        @if ($cleanupAction === 'delete')
            @method('DELETE')
        @endif
        <input type="hidden" name="cleanup_fingerprint" value="{{ $cleanup->fingerprint($event) }}">
        @if ($cleanupAction === 'delete')
            <div>
                <label for="cleanup-title-{{ $event->id }}" class="block text-sm font-medium">Type the event title to confirm</label>
                <input id="cleanup-title-{{ $event->id }}" name="confirmation_title" type="text" required maxlength="255" autocomplete="off" spellcheck="false"
                    class="mt-2 h-11 w-full rounded-2xl border border-slate-200 px-4 text-sm">
            </div>
        @endif
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="confirmation" value="1" required class="mt-1 h-4 w-4">
            <span>{{ $cleanupAction === 'delete' ? 'I understand this permanently deletes the empty draft.' : 'I understand this changes event list visibility and preserves event history.' }}</span>
        </label>
        <div class="flex flex-wrap justify-end gap-3">
            <button type="button" data-event-cleanup-close class="rounded-full bg-slate-50 px-5 py-2.5 text-sm font-semibold">Cancel</button>
            <button type="submit" data-event-cleanup-submit class="rounded-full px-5 py-2.5 text-sm font-semibold {{ $cleanupAction === 'delete' ? 'bg-rose-50 text-rose-600' : 'bg-indigo-50 text-indigo-600' }}">{{ $cleanupLabel }}</button>
        </div>
    </form>
</dialog>
