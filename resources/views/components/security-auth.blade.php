@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Racetech</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/security-auth.css') }}">
    <script src="{{ asset('js/security-auth.js') }}" defer></script>
</head>
<body class="security-auth">
    <header class="security-header">
        <a href="{{ route('home') }}" class="security-brand" aria-label="Racetech home">
            <span class="security-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 21V4m0 0c5-5 9 5 14 0v10c-5 5-9-5-14 0"/><path d="M9 3v5m5-2v5m-5 0v4m5-1v3"/></svg></span>
            <span>Racetech<small>Admin operations access</small></span>
        </a>
        <span class="security-header-note">Account security</span>
    </header>
    <main class="security-main">
        <section class="security-card" aria-labelledby="security-title">
            <div class="security-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg></div>
            <p class="security-eyebrow">Protecting your account</p>
            {{ $slot }}
        </section>
        <p class="security-footer">Racetech · Admin access</p>
    </main>
</body>
</html>
