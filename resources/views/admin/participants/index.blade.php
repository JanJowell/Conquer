@extends('admin.layouts.app')

@section('title', 'Participants')

@section('content')
    @php
        $selectedEvent = $events->firstWhere('id', (int) request('event_id'));
        $statusCards = [
            'pending' => [
                'label' => 'Needs Approval',
                'value' => $summary['pending'] ?? 0,
                'href' => route('admin.participants.index', array_filter(['event_id' => request('event_id'), 'category_id' => request('category_id'), 'status' => 'pending'])),
                'tone' => 'border-amber-200 bg-amber-50 text-amber-800',
            ],
            'approved' => [
                'label' => 'Ready For Check-in',
                'value' => $summary['approved'] ?? 0,
                'href' => route('admin.participants.index', array_filter(['event_id' => request('event_id'), 'category_id' => request('category_id'), 'status' => 'approved'])),
                'tone' => 'border-sky-200 bg-sky-50 text-sky-800',
            ],
            'checked_in' => [
                'label' => 'Awaiting Results',
                'value' => $summary['checked_in'] ?? 0,
                'href' => route('admin.participants.index', array_filter(['event_id' => request('event_id'), 'category_id' => request('category_id'), 'status' => 'checked_in'])),
                'tone' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
            ],
            'completed' => [
                'label' => 'Completed',
                'value' => $summary['completed'] ?? 0,
                'href' => route('admin.participants.index', array_filter(['event_id' => request('event_id'), 'category_id' => request('category_id'), 'status' => 'completed'])),
                'tone' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            ],
            'rejected' => [
                'label' => 'Rejected',
                'value' => $summary['rejected'] ?? 0,
                'href' => route('admin.participants.index', array_filter(['event_id' => request('event_id'), 'category_id' => request('category_id'), 'status' => 'rejected'])),
                'tone' => 'border-rose-200 bg-rose-50 text-rose-800',
            ],
        ];
        $badgeClasses = [
            'pending' => 'border-amber-200 bg-amber-50 text-amber-800',
            'approved' => 'border-sky-200 bg-sky-50 text-sky-800',
            'checked_in' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
            'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'rejected' => 'border-rose-200 bg-rose-50 text-rose-800',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Event Operations</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Participants</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#6d7685]">Approve registrations, auto-assign bib numbers, and track participant progress by event.</p>
                @if ($selectedEvent)
                    <p class="mt-3 inline-flex rounded-full border border-[#d9dee7] bg-white px-3 py-1 text-xs font-semibold text-[#3d4757]">
                        Viewing {{ $selectedEvent->title }}
                    </p>
                @endif
            </div>

            @if (request()->filled('event_id'))
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.check-in.index', ['event_id' => request('event_id')]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                        Check-in
                    </a>
                    <a href="{{ route('admin.results.index', ['event_id' => request('event_id')]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] bg-white px-4 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                        Results
                    </a>
                    <a href="{{ route('admin.participants.export', request()->only(['search', 'event_id', 'category_id', 'status'])) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#151b26] px-4 text-sm font-semibold text-white transition hover:bg-[#2a3342]">
                        Export CSV
                    </a>
                </div>
            @else
                <a href="{{ route('admin.participants.export', request()->only(['search', 'category_id', 'status'])) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#151b26] px-4 text-sm font-semibold text-white transition hover:bg-[#2a3342]">
                    Export CSV
                </a>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <a href="{{ route('admin.participants.index', array_filter(['event_id' => request('event_id'), 'category_id' => request('category_id')])) }}" class="rounded-3xl border border-[#d9dee7] bg-white p-5 shadow-sm transition hover:border-[#b8c0cc] hover:bg-[#fafbfc]">
                <p class="text-sm font-medium text-[#6d7685]">Total</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['total'] ?? 0) }}</p>
            </a>

            @foreach ($statusCards as $key => $card)
                <a href="{{ $card['href'] }}" class="rounded-3xl border bg-white p-5 shadow-sm transition hover:border-[#b8c0cc] hover:bg-[#fafbfc] {{ request('status') === $key ? $card['tone'] : 'border-[#d9dee7]' }}">
                    <p class="text-sm font-medium {{ request('status') === $key ? '' : 'text-[#6d7685]' }}">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight {{ request('status') === $key ? '' : 'text-[#151b26]' }}">{{ number_format($card['value']) }}</p>
                </a>
            @endforeach
        </div>

        @if (($summary['pending'] ?? 0) > 0 && ! request()->filled('status'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <span class="font-semibold">{{ number_format($summary['pending']) }} registrations need approval.</span>
                Filter to pending participants to approve them for check-in. Bib numbers are assigned automatically.
            </div>
        @endif

        <form method="GET" class="grid gap-3 rounded-3xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_220px_220px_180px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant, email, bib, event"
                    class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
            </div>
            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Category</label>
                <select id="category_id" name="category_id" class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <option value="">All categories</option>
                    @foreach ($categories->groupBy('event_id') as $eventCategories)
                        <optgroup label="{{ $eventCategories->first()?->event?->title ?: 'Event' }}">
                            @foreach ($eventCategories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                <select id="status" name="status" class="h-11 w-full rounded-2xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-2xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Filter</button>
                <a href="{{ route('admin.participants.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685] transition hover:bg-[#f7f8fa]">Clear</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-3xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Payment</th>
                            <th class="px-6 py-4">Compliance</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Registered</th>
                            <th class="px-6 py-4">Manage Registration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($participants as $participant)
                            @php
                                $statusClass = $badgeClasses[$participant->status] ?? 'border-slate-200 bg-slate-50 text-slate-700';
                                $emergencyContact = trim(collect([
                                    $participant->user?->emergency_contact_name,
                                    $participant->user?->emergency_contact_number,
                                ])->filter()->implode(' - '));
                                $legacyEmergencyContact = trim((string) ($participant->user?->emergency_contact ?? ''));
                                $safetyContact = $emergencyContact !== '' ? $emergencyContact : $legacyEmergencyContact;
                                $healthNotes = trim((string) ($participant->medical_conditions ?? ''));
                                $requiresMedicalCertificate = $participant->category?->requiresMedicalCertificate() ?? false;
                            @endphp

                            <tr class="align-top {{ $participant->status === 'pending' ? 'bg-amber-50/35' : '' }}">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $participant->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $participant->user?->email ?: 'No email available' }}</p>
                                    @if ($safetyContact !== '' || $healthNotes !== '')
                                        <div class="mt-3 rounded-2xl border border-rose-100 bg-rose-50/70 px-3 py-2 text-xs leading-5 text-rose-950">
                                            <p class="font-semibold text-rose-800">Safety</p>
                                            @if ($safetyContact !== '')
                                                <p class="mt-1"><span class="font-semibold">Emergency:</span> {{ $safetyContact }}</p>
                                            @endif
                                            @if ($healthNotes !== '')
                                                <p class="mt-1"><span class="font-semibold">Event health notes:</span> {{ $healthNotes }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($participant->status === 'pending')
                                        <p class="mt-2 inline-flex rounded-full border border-amber-200 bg-white px-2.5 py-1 text-xs font-semibold text-amber-800">Needs review</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $participant->event?->title ?: 'Deleted event' }}</td>
                                <td class="px-6 py-5">{{ $participant->category?->name ?: 'No category' }}</td>
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">
                                        {{ $participant->payment_currency ?? 'PHP' }} {{ number_format(($participant->payment_amount_cents ?? 0) / 100, 2) }}
                                    </p>
                                    <p class="mt-1 text-xs text-[#6d7685]">
                                        {{ str($participant->payment_status ?? 'waived')->replace('_', ' ')->title() }}
                                    </p>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="space-y-2 text-xs">
                                        <p class="inline-flex rounded-full border px-2.5 py-1 font-semibold {{ $participant->waiver_accepted || $participant->kit_waiver_signed_at ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                                            Waiver {{ $participant->waiver_accepted ? 'accepted in app' : ($participant->kit_waiver_signed_at ? 'signed onsite' : 'missing') }}
                                        </p>
                                        <p class="inline-flex rounded-full border px-2.5 py-1 font-semibold {{ $participant->first_aid_kit_confirmed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                                            First aid {{ $participant->first_aid_kit_confirmed ? 'confirmed' : 'missing' }}
                                        </p>
                                        @if ($requiresMedicalCertificate)
                                            <p>
                                                @if ($participant->medical_certificate_path)
                                                    <a href="{{ asset('storage/'.$participant->medical_certificate_path) }}" target="_blank" class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-800 transition hover:bg-emerald-100">
                                                        Medical cert submitted
                                                    </a>
                                                @else
                                                    <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 font-semibold text-rose-800">
                                                        Medical cert missing
                                                    </span>
                                                @endif
                                            </p>
                                        @else
                                            <p class="text-[#6d7685]">Medical cert not required.</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statusOptions[$participant->status] ?? str($participant->status)->replace('_', ' ')->title() }}
                                    </span>
                                    @if ($participant->status === 'approved')
                                        <p class="mt-2 text-xs text-[#6d7685]">Ready for race-day check-in.</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Bib: {{ $participant->bib_number ?: 'Auto-assigned on approval' }}</p>
                                    @elseif ($participant->status === 'checked_in')
                                        <p class="mt-2 text-xs text-[#6d7685]">Waiting for result entry.</p>
                                    @elseif ($participant->status === 'rejected' && filled($participant->rejection_reason))
                                        <p class="mt-2 text-xs leading-5 text-[#6d7685]">{{ $participant->rejection_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $participant->registered_at?->format('M d, Y h:i A') ?: $participant->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-6 py-5">
                                    @if (in_array($participant->status, ['checked_in', 'completed'], true))
                                        <div class="grid gap-3 lg:grid-cols-[180px_160px] lg:items-center">
                                            <span class="inline-flex h-10 items-center rounded-xl border border-[#d9dee7] px-3 text-sm font-medium text-[#5e6878]">
                                                {{ $statusOptions[$participant->status] ?? str($participant->status)->replace('_', ' ')->title() }}
                                            </span>
                                            <span class="inline-flex h-10 items-center rounded-xl border border-[#d9dee7] px-3 text-sm font-medium text-[#5e6878]">
                                                {{ $participant->bib_number ?: 'No bib assigned' }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs text-[#6d7685]">Status and bib are managed from Check-in or Results.</p>
                                    @else
                                        <form method="POST" action="{{ route('admin.participants.update', $participant) }}"
                                            class="grid gap-3 lg:grid-cols-2 lg:items-start"
                                            data-registration-review-form
                                            data-participant-name="{{ $participant->user?->name ?: 'Unknown participant' }}"
                                            data-event-name="{{ $participant->event?->title ?: 'Deleted event' }}"
                                            data-category-name="{{ $participant->category?->name ?: 'No category' }}"
                                            data-payment-summary="{{ $participant->payment_currency ?? 'PHP' }} {{ number_format(($participant->payment_amount_cents ?? 0) / 100, 2) }} — {{ str($participant->payment_status ?? 'waived')->replace('_', ' ')->title() }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($participant->status !== 'approved')
                                                <button type="submit" name="status" value="approved"
                                                    data-registration-action
                                                    class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100">
                                                    Approve
                                                </button>
                                            @endif
                                            @if ($participant->status !== 'rejected')
                                                <button type="submit" name="status" value="rejected"
                                                    data-registration-action
                                                    class="inline-flex h-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-800 transition hover:bg-rose-100">
                                                    Reject
                                                </button>
                                                <textarea name="rejection_reason" rows="2" placeholder="Reason required when rejected"
                                                    maxlength="1000" data-rejection-reason aria-describedby="rejection-error-{{ $participant->id }}"
                                                    class="rounded-xl border border-[#d9dee7] px-3 py-2 text-sm text-[#151b26] outline-none lg:col-span-2">{{ $participant->rejection_reason }}</textarea>
                                                <p id="rejection-error-{{ $participant->id }}" data-rejection-error class="hidden text-xs font-medium text-rose-700 lg:col-span-2">
                                                    Enter a reason before rejecting this registration.
                                                </p>
                                            @endif
                                        </form>
                                        @if ($participant->status === 'pending')
                                            <p class="mt-2 text-xs text-[#6d7685]">A bib number is assigned automatically when approving.</p>
                                        @elseif ($participant->status === 'approved')
                                            <p class="mt-2 text-xs text-[#6d7685]">Already approved. You can reject before check-in if needed.</p>
                                        @elseif ($participant->status === 'rejected')
                                            <p class="mt-2 text-xs text-[#6d7685]">The participant can re-apply from the mobile app, which will return this registration to pending review.</p>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-[#6d7685]">No participants match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $participants->links() }}
            </div>
        </div>
    </div>

    <dialog id="registration-confirmation-dialog" aria-labelledby="registration-confirmation-title" aria-describedby="registration-confirmation-description"
        class="w-[min(92vw,34rem)] rounded-3xl border border-[#d9dee7] bg-white p-0 text-[#151b26] shadow-2xl backdrop:bg-slate-950/50">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7a8495]">Confirm registration decision</p>
                    <h2 id="registration-confirmation-title" class="mt-2 text-2xl font-semibold tracking-tight">Confirm registration</h2>
                </div>
                <button type="button" data-confirmation-cancel aria-label="Close confirmation"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#d9dee7] text-lg text-[#6d7685] transition hover:bg-[#f7f8fa]">
                    &times;
                </button>
            </div>

            <p id="registration-confirmation-description" data-confirmation-description class="mt-3 text-sm leading-6 text-[#5e6878]"></p>

            <dl class="mt-5 grid gap-3 rounded-2xl border border-[#eef1f4] bg-[#fafbfc] p-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7a8495]">Participant</dt>
                    <dd data-confirmation-participant class="mt-1 font-semibold text-[#151b26]"></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7a8495]">Payment</dt>
                    <dd data-confirmation-payment class="mt-1 font-semibold text-[#151b26]"></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7a8495]">Event</dt>
                    <dd data-confirmation-event class="mt-1 font-semibold text-[#151b26]"></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7a8495]">Category</dt>
                    <dd data-confirmation-category class="mt-1 font-semibold text-[#151b26]"></dd>
                </div>
                <div data-confirmation-reason-row class="hidden sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7a8495]">Rejection reason</dt>
                    <dd data-confirmation-reason class="mt-1 whitespace-pre-wrap font-medium text-rose-800"></dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" data-confirmation-cancel
                    class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#3d4757] transition hover:bg-[#f7f8fa]">
                    Cancel
                </button>
                <button type="button" data-confirmation-submit
                    class="inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60">
                    Confirm
                </button>
            </div>
        </div>
    </dialog>

    <script>
        (() => {
            const dialog = document.querySelector('#registration-confirmation-dialog');
            const confirmButton = dialog?.querySelector('[data-confirmation-submit]');
            const title = dialog?.querySelector('#registration-confirmation-title');
            const description = dialog?.querySelector('[data-confirmation-description]');
            const participant = dialog?.querySelector('[data-confirmation-participant]');
            const eventName = dialog?.querySelector('[data-confirmation-event]');
            const category = dialog?.querySelector('[data-confirmation-category]');
            const payment = dialog?.querySelector('[data-confirmation-payment]');
            const reasonRow = dialog?.querySelector('[data-confirmation-reason-row]');
            const reasonText = dialog?.querySelector('[data-confirmation-reason]');
            let pendingForm = null;

            const clearPendingConfirmation = () => {
                pendingForm?.querySelector('[data-confirmed-status]')?.remove();
                pendingForm = null;
                if (confirmButton) confirmButton.disabled = false;
            };

            const closeConfirmation = () => {
                dialog?.close();
                clearPendingConfirmation();
            };

            document.querySelectorAll('[data-registration-review-form]').forEach((form) => {
                const rejectionReason = form.querySelector('[data-rejection-reason]');
                const rejectionError = form.querySelector('[data-rejection-error]');

                rejectionReason?.addEventListener('input', () => {
                    rejectionReason.setCustomValidity('');
                    rejectionError?.classList.add('hidden');
                });

                form.addEventListener('submit', (event) => {
                    if (form.dataset.confirmed === 'true') {
                        form.querySelectorAll('[data-registration-action]').forEach((button) => {
                            button.disabled = true;
                        });
                        return;
                    }

                    event.preventDefault();
                    const action = event.submitter?.value;
                    if (! ['approved', 'rejected'].includes(action)) return;

                    const trimmedReason = rejectionReason?.value.trim() || '';
                    if (action === 'rejected' && trimmedReason === '') {
                        rejectionReason.setCustomValidity('Enter a reason before rejecting this registration.');
                        rejectionError?.classList.remove('hidden');
                        rejectionReason.reportValidity();
                        rejectionReason.focus();
                        return;
                    }

                    rejectionReason?.setCustomValidity('');
                    rejectionError?.classList.add('hidden');
                    pendingForm = form;

                    const confirmedStatus = document.createElement('input');
                    confirmedStatus.type = 'hidden';
                    confirmedStatus.name = 'status';
                    confirmedStatus.value = action;
                    confirmedStatus.dataset.confirmedStatus = 'true';
                    form.querySelector('[data-confirmed-status]')?.remove();
                    form.appendChild(confirmedStatus);

                    const approving = action === 'approved';
                    title.textContent = approving ? 'Approve this registration?' : 'Reject this registration?';
                    description.textContent = approving
                        ? 'Approval confirms this participant for the category and assigns a bib number when needed.'
                        : 'Rejection removes any assigned bib, sends the reason to the participant, and allows them to re-apply.';
                    participant.textContent = form.dataset.participantName;
                    eventName.textContent = form.dataset.eventName;
                    category.textContent = form.dataset.categoryName;
                    payment.textContent = form.dataset.paymentSummary;
                    reasonRow.classList.toggle('hidden', approving);
                    reasonText.textContent = approving ? '' : trimmedReason;
                    confirmButton.textContent = approving ? 'Approve Registration' : 'Reject Registration';
                    confirmButton.classList.toggle('bg-emerald-600', approving);
                    confirmButton.classList.toggle('hover:bg-emerald-700', approving);
                    confirmButton.classList.toggle('bg-rose-600', ! approving);
                    confirmButton.classList.toggle('hover:bg-rose-700', ! approving);

                    if (typeof dialog.showModal === 'function') {
                        dialog.showModal();
                    } else if (window.confirm(`${title.textContent}\n\n${description.textContent}`)) {
                        form.dataset.confirmed = 'true';
                        form.requestSubmit();
                    } else {
                        clearPendingConfirmation();
                    }
                });
            });

            dialog?.querySelectorAll('[data-confirmation-cancel]').forEach((button) => {
                button.addEventListener('click', closeConfirmation);
            });

            dialog?.addEventListener('cancel', (event) => {
                event.preventDefault();
                closeConfirmation();
            });

            confirmButton?.addEventListener('click', () => {
                if (! pendingForm) return;

                const form = pendingForm;
                confirmButton.disabled = true;
                form.dataset.confirmed = 'true';
                dialog.close();
                form.requestSubmit();
                pendingForm = null;
            });
        })();
    </script>
@endsection
