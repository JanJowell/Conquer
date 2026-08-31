<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify E-Certificate</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #eef6fb; color: #102039; font-family: Arial, sans-serif; }
        .card { width: min(620px, calc(100% - 40px)); box-sizing: border-box; padding: 36px; border: 1px solid #d7e5ef; border-radius: 24px; background: white; box-shadow: 0 20px 60px rgba(16,32,57,.12); }
        .status { display: inline-block; padding: 7px 12px; border-radius: 999px; font-weight: 700; background: {{ $certificate->isValid() ? '#dcfce7' : '#fee2e2' }}; color: {{ $certificate->isValid() ? '#166534' : '#991b1b' }}; }
        dl { display: grid; grid-template-columns: 150px 1fr; gap: 12px; margin: 28px 0; }
        dt { color: #667085; } dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        a { display: inline-block; padding: 12px 18px; border-radius: 12px; background: #102039; color: white; text-decoration: none; font-weight: 700; }
        @media (max-width: 520px) { .card { padding: 24px; } dl { grid-template-columns: 1fr; gap: 5px; } dd { margin-bottom: 10px; } }
    </style>
</head>
<body>
<main class="card">
    <span class="status">{{ $certificate->isValid() ? 'Valid E-Certificate' : 'Revoked E-Certificate' }}</span>
    <h1>Certificate Verification</h1>
    <p>This page verifies an official event completion record issued by {{ config('app.name', 'RACETECH') }}.</p>
    <dl>
        <dt>Certificate No.</dt><dd>{{ $certificate->certificate_number }}</dd>
        <dt>Participant</dt><dd>{{ $certificate->participant_name }}</dd>
        <dt>Event</dt><dd>{{ $certificate->event_title }}</dd>
        <dt>Category</dt><dd>{{ $certificate->category_name }}</dd>
        <dt>Finish time</dt><dd>{{ $certificate->finish_time ?: 'Official finisher' }}</dd>
        <dt>Issued</dt><dd>{{ optional($certificate->issued_at)->format('F j, Y') }}</dd>
        @if ($certificate->revoked_at)
            <dt>Revoked</dt><dd>{{ $certificate->revoked_at->format('F j, Y') }}</dd>
            <dt>Reason</dt><dd>{{ $certificate->revocation_reason ?: 'Revoked by the organizer.' }}</dd>
        @endif
    </dl>
    @if ($certificate->isValid())
        <a href="{{ route('certificates.download', $certificate->verification_token) }}">Download E-Certificate</a>
    @endif
</main>
</body>
</html>
