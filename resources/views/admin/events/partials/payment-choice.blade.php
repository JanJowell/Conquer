@php
    $choiceId = 'event-payment-'.($isEventCopy ? 'copy-' : 'option-').$choice->id;
    $provider = \App\Models\EventPaymentMethod::providers()[$choice->provider] ?? $choice->provider;
@endphp
<div class="min-w-0 rounded-xl border border-slate-200 p-3">
    <label for="{{ $choiceId }}" class="flex cursor-pointer items-start gap-3 text-sm text-slate-700">
        <input id="{{ $choiceId }}" type="checkbox" name="payment_choices[]" value="{{ $token }}" data-payment-provider="{{ $choice->provider }}" aria-describedby="event-payment-selection-help" @checked(in_array($token, $selectedChoices, true)) class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300">
        <span class="min-w-0 break-words">
            <span class="font-medium">{{ $provider }}{{ $choice->account_name ? ' — '.$choice->account_name : '' }}</span>
            @if($isEventCopy)<span class="ml-1 text-xs text-slate-500">(Saved for this event)</span>@endif
        </span>
    </label>
    <details class="mt-2 pl-7 text-xs text-slate-500">
        <summary class="w-fit cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">View account details<span class="sr-only"> for {{ $provider }}{{ $choice->account_name ? ' — '.$choice->account_name : '' }}</span></summary>
        <div class="mt-2 space-y-1 break-words">
            @unless($isEventCopy)<p>{{ $choice->label }} · {{ $choice->is_shared ? 'Shared option' : 'My option' }}</p>@endunless
            @if($choice->account_number)<p>Account number: {{ $choice->account_number }}</p>@endif
            @if($choice->instructions)<p class="whitespace-pre-line">{{ $choice->instructions }}</p>@endif
            @if($isEventCopy)
                <p>{{ $choice->is_enabled ? 'Saved details stay unchanged unless you select a different account for this provider.' : 'Currently disabled. Select to enable this saved copy.' }}</p>
            @endif
        </div>
    </details>
</div>
