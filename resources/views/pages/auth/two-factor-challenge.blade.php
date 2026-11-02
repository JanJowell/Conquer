@php($recovery = request()->boolean('recovery') || $errors->has('recovery_code'))
<x-security-auth :title="$recovery ? __('Recovery code') : __('Two-factor verification')">
    <h1 id="security-title">{{ $recovery ? __('Use a recovery code') : __('Two-factor verification') }}</h1>
    <p class="security-description">{{ $recovery ? __('Enter one of the recovery codes you saved when you enabled two-factor authentication.') : __('Enter the 6-digit code from your authenticator app to finish signing in.') }}</p>
    @if ($errors->any())
        <div class="security-error" role="alert" id="security-error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="security-form" data-security-form>
        @csrf
        @if ($recovery)
            <div>
                <label for="recovery-code" class="security-label">{{ __('Recovery code') }}</label>
                <input id="recovery-code" name="recovery_code" type="text" required autofocus autocomplete="off" autocapitalize="none" spellcheck="false" class="security-input" placeholder="{{ __('Enter a saved recovery code') }}" aria-describedby="recovery-help{{ $errors->any() ? ' security-error' : '' }}" @if($errors->any()) aria-invalid="true" @endif>
                <p class="security-help" id="recovery-help">{{ __('Each recovery code can only be used once. Keep your remaining codes somewhere safe.') }}</p>
            </div>
        @else
            <div class="security-otp">
                <label for="authentication-code" class="security-label">{{ __('Authentication code') }}</label>
                <input id="authentication-code" class="security-input security-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="000000" aria-describedby="code-help{{ $errors->any() ? ' security-error' : '' }}" @if($errors->any()) aria-invalid="true" @endif>
                <p class="security-help" id="code-help">{{ __('You can paste all six digits. Use the current code before it expires.') }}</p>
            </div>
        @endif
        <button type="submit" class="security-primary" data-submit-label="{{ __('Verify and sign in') }}">{{ __('Verify and sign in') }}</button>
    </form>
    <div class="security-alternative">
        <a href="{{ route('two-factor.login', $recovery ? [] : ['recovery' => 1]) }}" class="security-link">{{ $recovery ? __('Use an authentication code') : __('Use a recovery code') }}</a>
    </div>
    <form method="POST" action="{{ route('two-factor.cancel') }}" class="security-back">
        @csrf
        <button type="submit" class="security-link">{{ __('Back to login') }}</button>
    </form>
</x-security-auth>
