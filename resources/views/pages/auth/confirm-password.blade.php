<x-security-auth :title="__('Confirm password')">
    <h1 id="security-title">{{ __('Confirm your password') }}</h1>
    <p class="security-description">{{ __('For your security, enter your current password before continuing with this account change.') }}</p>
    @if (session('status'))
        <div class="security-status" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="security-error" role="alert" id="security-error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('password.confirm.store') }}" class="security-form" data-security-form>
        @csrf
        <div>
            <label for="confirm-password" class="security-label">{{ __('Current password') }}</label>
            <div class="security-password">
                <input id="confirm-password" name="password" type="password" required autofocus autocomplete="current-password" class="security-input" placeholder="{{ __('Enter your password') }}" @if($errors->has('password')) aria-invalid="true" aria-describedby="security-error" @endif>
                <button type="button" class="security-reveal" data-password-toggle="confirm-password" aria-controls="confirm-password" aria-label="Show password" aria-pressed="false" hidden>Show</button>
            </div>
        </div>
        <button type="submit" class="security-primary" data-test="confirm-password-button" data-submit-label="{{ __('Confirm and continue') }}">{{ __('Confirm and continue') }}</button>
    </form>
    <div class="security-back"><a href="{{ route('profile.edit') }}" class="security-link">{{ __('Back to profile') }}</a></div>
</x-security-auth>
