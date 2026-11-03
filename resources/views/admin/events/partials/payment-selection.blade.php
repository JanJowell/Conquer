@php
    $eventCopies = $event ? $event->paymentMethods : collect();
    $selectedChoices = session()->hasOldInput('payment_selection_submitted')
        ? old('payment_choices', [])
        : $eventCopies->filter(fn ($copy) => $copy->is_enabled && ! $event->payment_setup_needs_review)
            ->map(fn ($copy) => \App\Services\EventPaymentSelection::eventToken($copy))->all();
    $selectedChoices = is_array($selectedChoices) ? $selectedChoices : [];
    $paymentChoices = \App\Services\EventPaymentSelection::formChoices(
        $eventCopies, $savedPaymentOptions ?? collect(), $selectedChoices
    );
@endphp
<section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5" aria-label="Event Payment Options" data-event-payment-selection>
    <input type="hidden" name="payment_selection_submitted" value="1">
    <h2 class="text-lg font-semibold text-slate-800">Event Payment Options</h2>
    @error('payment_selection_submitted')<p role="alert" class="text-sm text-rose-600">{{ $message }}</p>@enderror
    @if($errors->has('payment_choices') || $errors->has('payment_choices.*'))
        <p role="alert" class="text-sm text-rose-600">{{ $errors->first('payment_choices') ?: $errors->first('payment_choices.*') }}</p>
    @endif
    <fieldset class="space-y-3">
        <legend class="sr-only">Payment options</legend>
        @foreach($paymentChoices as $paymentChoice)
            @include('admin.events.partials.payment-choice', $paymentChoice)
        @endforeach
        @if($paymentChoices->isEmpty())
            <p class="text-sm text-slate-500">No payment options yet. Add one on the Payments page.</p>
        @endif
    </fieldset>
    <p id="event-payment-selection-help" class="text-xs text-slate-500">Required for paid events. Select one account per provider.</p>
    <p class="sr-only" role="status" aria-live="polite" data-payment-selection-status></p>
    <a href="{{ route('admin.payment-options.index') }}" target="_blank" rel="noopener noreferrer" class="inline-block text-sm font-medium text-indigo-700 underline underline-offset-4">Manage payment options<span class="sr-only"> (opens in a new tab)</span></a>
</section>
@once
    <script src="{{ asset('js/event-payment-selection.js') }}" defer></script>
@endonce
