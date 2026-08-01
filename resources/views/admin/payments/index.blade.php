@extends('admin.layouts.app')

@section('title', 'Payments')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#7a8495]">Finance</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Payments</h1>
                <p class="mt-2 max-w-3xl text-sm text-[#6d7685]">Track and verify paid, unpaid, waived, failed, and pending registration payments across events.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Payment Records</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['total']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Unpaid/Pending</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['unpaid']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Submitted Proof</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['submitted']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Paid</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['paid']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Waived</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">{{ number_format($summary['waived']) }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9dee7] bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-[#6d7685]">Collected</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-[#151b26]">PHP {{ number_format($summary['collected_cents'] / 100, 2) }}</p>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-2xl border border-[#d9dee7] bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_180px_180px_auto]">
            <div>
                <label for="search" class="mb-2 block text-sm font-medium text-[#3d4757]">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" type="text" placeholder="Participant, email, event, reference"
                    class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
            </div>
            <div>
                <label for="event_id" class="mb-2 block text-sm font-medium text-[#3d4757]">Event</label>
                <select id="event_id" name="event_id" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-medium text-[#3d4757]">Status</label>
                <select id="status" name="status" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All statuses</option>
                    @foreach ($paymentStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="provider" class="mb-2 block text-sm font-medium text-[#3d4757]">Provider</label>
                <select id="provider" name="provider" class="h-11 w-full rounded-xl border border-[#d9dee7] px-4 text-sm text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                    <option value="">All providers</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider }}" @selected(request('provider') === $provider)>{{ str($provider)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-11 rounded-xl border border-[#d9dee7] px-5 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">Filter</button>
                <a href="{{ route('admin.payments.export', request()->query()) }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                    Export
                </a>
                <a href="{{ route('admin.payments.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9dee7] px-4 text-sm font-semibold text-[#6d7685] transition hover:bg-[#f7f8fa]">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-[#d9dee7] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#eef1f4]">
                    <thead class="bg-[#fafbfc]">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#7a8495]">
                            <th class="px-6 py-4">Participant</th>
                            <th class="px-6 py-4">Event / Category</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Latest Payment</th>
                            <th class="px-6 py-4">Registered</th>
                            <th class="px-6 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f4] text-sm text-[#202733]">
                        @forelse ($registrations as $registration)
                            @php
                                $payment = $registration->latestPayment;
                                $status = $registration->payment_status ?? 'waived';
                                $lockedRegistration = in_array($registration->status, ['checked_in', 'completed'], true);
                                $paymentSource = $payment ? data_get($payment->payload, 'source') : null;
                                $webhookEventType = $payment ? data_get($payment->payload, 'webhook_event_type') : null;
                                $statusClass = [
                                    'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'submitted' => 'border-blue-200 bg-blue-50 text-blue-700',
                                    'pending' => 'border-amber-200 bg-amber-50 text-amber-800',
                                    'unpaid' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'expired' => 'border-slate-300 bg-slate-100 text-slate-700',
                                    'refunded' => 'border-sky-200 bg-sky-50 text-sky-700',
                                    'cancelled' => 'border-slate-300 bg-slate-100 text-slate-700',
                                    'waived' => 'border-slate-200 bg-slate-50 text-slate-600',
                                ][$status] ?? 'border-slate-200 bg-slate-50 text-slate-600';
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $registration->user?->name ?: 'Unknown participant' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->user?->email ?: 'No email available' }}</p>
                                    <p class="mt-2 text-xs text-[#6d7685]">Bib {{ $registration->bib_number ?: 'not assigned' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">Registration {{ str($registration->status)->replace('_', ' ')->title() }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p>{{ $registration->event?->title ?: 'Deleted event' }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->category?->name ?: 'No category' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="font-semibold text-[#151b26]">{{ $registration->payment_currency ?? 'PHP' }} {{ number_format(($registration->payment_amount_cents ?? 0) / 100, 2) }}</p>
                                    <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->payment_required ? 'Required' : 'Not required' }}</p>
                                    @if ($registration->category?->payment_provider)
                                        <p class="mt-1 text-xs text-[#6d7685]">{{ $registration->category->payment_provider_label }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $paymentStatuses[$status] ?? str($status)->replace('_', ' ')->title() }}
                                    </span>
                                    @if ($registration->paid_at)
                                        <p class="mt-2 text-xs text-[#6d7685]">Paid {{ $registration->paid_at->format('M d, Y h:i A') }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">
                                    @if ($payment)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-[#151b26]">{{ str($payment->provider)->replace('_', ' ')->title() }}</p>
                                            @if ($payment->provider === 'paymongo')
                                                <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700">Gateway</span>
                                            @elseif (str_starts_with($payment->provider, 'manual'))
                                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Manual</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 break-all text-xs text-[#6d7685]">{{ $payment->provider_reference ?: 'No reference yet' }}</p>
                                        <p class="mt-1 text-xs text-[#6d7685]">Latest status: {{ str($payment->status)->replace('_', ' ')->title() }}</p>
                                        @if ($paymentSource)
                                            <p class="mt-1 text-xs text-[#6d7685]">Source: {{ str($paymentSource)->replace('_', ' ')->title() }}</p>
                                        @endif
                                        @if ($webhookEventType)
                                            <p class="mt-1 text-xs text-[#6d7685]">Webhook: {{ $webhookEventType }}</p>
                                        @endif
                                        @if ($payment->submitted_at)
                                            <p class="mt-1 text-xs text-[#6d7685]">Submitted {{ $payment->submitted_at->format('M d, Y h:i A') }}</p>
                                        @endif
                                        @if ($payment->checkout_url)
                                            <a href="{{ $payment->checkout_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center rounded-lg border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-50">
                                                Open Checkout
                                            </a>
                                        @endif
                                        @if ($payment->proof_path)
                                            <a href="{{ asset('storage/'.$payment->proof_path) }}" target="_blank" class="mt-3 inline-flex items-center rounded-lg border border-[#d9dee7] px-3 py-2 text-xs font-semibold text-[#151b26] transition hover:bg-[#f7f8fa]">
                                                View Proof
                                            </a>
                                        @endif
                                        @if (data_get($payment->payload, 'notes'))
                                            <p class="mt-1 max-w-xs text-xs text-[#6d7685]">{{ data_get($payment->payload, 'notes') }}</p>
                                        @endif
                                    @else
                                        <p class="text-xs text-[#6d7685]">No payment attempt yet.</p>
                                    @endif

                                    @if ($registration->payments->isNotEmpty())
                                        <details class="mt-3">
                                            <summary class="cursor-pointer text-xs font-semibold text-[#47566a]">History</summary>
                                            <div class="mt-2 space-y-2">
                                                @foreach ($registration->payments->take(5) as $historyPayment)
                                                    <div class="border-l-2 border-[#d9dee7] pl-3 text-xs text-[#6d7685]">
                                                        <p class="font-semibold text-[#3d4757]">
                                                            {{ str($historyPayment->status)->replace('_', ' ')->title() }}
                                                            - {{ $historyPayment->created_at?->format('M d, Y h:i A') }}
                                                        </p>
                                                        <p>{{ str($historyPayment->provider)->replace('_', ' ')->title() }} - {{ $historyPayment->provider_reference ?: 'No reference' }}</p>
                                                        @if (data_get($historyPayment->payload, 'source'))
                                                            <p>Source: {{ str(data_get($historyPayment->payload, 'source'))->replace('_', ' ')->title() }}</p>
                                                        @endif
                                                        @if (data_get($historyPayment->payload, 'webhook_event_type'))
                                                            <p>Webhook: {{ data_get($historyPayment->payload, 'webhook_event_type') }}</p>
                                                        @endif
                                                        @if ($historyPayment->checkout_url)
                                                            <p><a href="{{ $historyPayment->checkout_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-700">Open checkout</a></p>
                                                        @endif
                                                        @if ($historyPayment->proof_path)
                                                            <p><a href="{{ asset('storage/'.$historyPayment->proof_path) }}" target="_blank" class="font-semibold text-[#151b26]">View proof</a></p>
                                                        @endif
                                                        @if (data_get($historyPayment->payload, 'notes'))
                                                            <p>{{ data_get($historyPayment->payload, 'notes') }}</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-6 py-5">{{ $registration->registered_at?->format('M d, Y h:i A') ?: $registration->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-6 py-5">
                                    <form method="POST" action="{{ route('admin.payments.update', $registration) }}" class="w-72 space-y-3" onsubmit="return confirm('Update this registration payment status?');">
                                        @csrf
                                        @method('PATCH')

                                        <div class="grid grid-cols-2 gap-2">
                                            <input name="provider" value="manual" type="text" placeholder="Provider"
                                                class="h-10 rounded-lg border border-[#d9dee7] px-3 text-xs text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                            <input name="provider_reference" type="text" placeholder="Reference no."
                                                class="h-10 rounded-lg border border-[#d9dee7] px-3 text-xs text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]">
                                        </div>

                                        <textarea name="notes" rows="2" placeholder="Notes"
                                            class="w-full rounded-lg border border-[#d9dee7] px-3 py-2 text-xs text-[#151b26] outline-none transition focus:border-[#aeb7c3] focus:ring-2 focus:ring-[#eef1f5]"></textarea>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="submit" name="action" value="paid"
                                                class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                Mark Paid
                                            </button>
                                            @unless ($lockedRegistration)
                                                <button type="submit" name="action" value="waived"
                                                    class="rounded-lg border border-[#d9dee7] px-3 py-2 text-xs font-semibold text-[#3d4757] transition hover:bg-[#f7f8fa]">
                                                    Waive
                                                </button>
                                                <button type="submit" name="action" value="failed"
                                                    class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">
                                                    Failed
                                                </button>
                                                <button type="submit" name="action" value="pending"
                                                    class="rounded-lg border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">
                                                    Pending
                                                </button>
                                            @endunless
                                            @if ($status === 'paid')
                                                <button type="submit" name="action" value="refunded"
                                                    class="rounded-lg border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-50">
                                                    Refunded
                                                </button>
                                            @endif
                                            @if (! $lockedRegistration && ! in_array($registration->status, ['approved'], true) && $status !== 'paid')
                                                <button type="submit" name="action" value="cancelled"
                                                    class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                                    Cancel
                                                </button>
                                            @endif
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-[#6d7685]">No payment-related registrations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-[#eef1f4] px-6 py-4">
                {{ $registrations->links() }}
            </div>
        </div>
    </div>
@endsection
