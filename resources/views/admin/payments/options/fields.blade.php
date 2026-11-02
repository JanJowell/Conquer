@php
    $provider = old('provider', $option->provider);
    $wallet = in_array($provider, ['GCash', 'Maya'], true);
    $manual = in_array($provider, ['GCash', 'Maya', 'Bank'], true);
@endphp
<div data-payment-option-fields class="space-y-5">
    <div>
        <label for="option-provider" class="mb-2 block text-sm font-medium text-slate-700">Provider <span class="text-rose-600">*</span></label>
        <select id="option-provider" name="provider" required class="w-full rounded-xl border border-slate-300 px-4 py-3">
            <option value="">Select provider</option>
            @foreach(\App\Models\EventPaymentMethod::providers() as $value => $label)
                <option value="{{ $value }}" @selected($provider === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('provider')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div data-manual-account @if(! $manual) hidden @endif class="space-y-5">
        <div>
            <label for="option-account_name" class="mb-2 block text-sm font-medium text-slate-700">Account name <span class="text-rose-600">*</span></label>
            <input id="option-account_name" type="text" name="account_name" maxlength="255" @required($manual) @disabled(! $manual) value="{{ old('account_name', $option->account_name) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
            @error('account_name')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="option-account_number" class="mb-2 block text-sm font-medium text-slate-700"><span data-account-number-label>{{ $wallet ? 'Registered mobile number' : 'Account number' }}</span> <span class="text-rose-600">*</span></label>
            {{-- JS installs the 11-character cap together with its paste safeguard. Without JS, keep the full input for pattern/server validation. --}}
            <input id="option-account_number" type="text" name="account_number" inputmode="{{ $wallet ? 'numeric' : 'text' }}" maxlength="255" @if($wallet) pattern="09[0-9]{9}" title="Enter 11 digits starting with 09." @endif @required($manual) @disabled(! $manual) aria-describedby="option-number-help option-number-input-error" value="{{ old('account_number', $option->account_number) }}" placeholder="{{ $wallet ? 'e.g. 09171234567' : 'Enter the account number exactly as provided by the bank' }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
            <p id="option-number-help" class="mt-1 text-xs text-slate-500">{{ $wallet ? 'Use 11 digits starting with 09, without spaces or dashes. Format checks do not verify account ownership.' : 'Keep any leading zeros. Enter the bank name in the instructions below.' }}</p>
            <p id="option-number-input-error" role="alert" hidden class="mt-1 text-sm text-rose-600"></p>
            @error('account_number')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <p data-online-checkout @if($provider !== 'PayMongo') hidden @endif class="text-sm text-slate-500">PayMongo uses the existing online checkout configuration. No receiving account number is needed. Never enter API keys here.</p>
    <div>
        <label for="option-instructions" class="mb-2 block text-sm font-medium text-slate-700">Instructions <span data-bank-required @if($provider !== 'Bank') hidden @endif class="text-rose-600">*</span></label>
        <textarea id="option-instructions" name="instructions" rows="4" maxlength="5000" @required($provider === 'Bank') aria-describedby="option-instructions-help" placeholder="Explain how to pay and which reference or proof to submit." class="w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('instructions', $option->instructions) }}</textarea>
        <p id="option-instructions-help" data-bank-help @if($provider !== 'Bank') hidden @endif class="mt-1 text-xs text-slate-500">Include the bank name (and branch if needed), then the payment steps.</p>
        @error('instructions')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>
