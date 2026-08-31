<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20px; }
        body { margin: 0; color: #102039; font-family: DejaVu Sans, sans-serif; }
        .frame { height: 680px; border: 10px solid #102039; padding: 18px; }
        .inner { height: 640px; border: 2px solid #36a9e1; text-align: center; position: relative; padding: 24px 55px; box-sizing: border-box; }
        .brand { color: #1677a8; font-size: 18px; font-weight: bold; letter-spacing: 4px; }
        h1 { margin: 32px 0 5px; font-size: 43px; letter-spacing: 3px; }
        .subtitle { color: #617085; font-size: 17px; }
        .name { margin: 28px auto 10px; padding-bottom: 8px; width: 75%; border-bottom: 2px solid #36a9e1; font-size: 35px; font-weight: bold; }
        .event { margin: 18px 0 5px; font-size: 25px; font-weight: bold; }
        .details { font-size: 16px; line-height: 1.7; }
        .footer { position: absolute; left: 55px; right: 55px; bottom: 28px; text-align: left; }
        .verify { width: 72%; color: #617085; font-size: 10px; line-height: 1.5; }
        .qr { position: absolute; right: 0; bottom: 0; width: 105px; height: 105px; }
    </style>
</head>
<body>
<div class="frame"><div class="inner">
    <div class="brand">{{ strtoupper(config('app.name', 'RACETECH')) }}</div>
    <h1>CERTIFICATE OF COMPLETION</h1>
    <div class="subtitle">This official E-Certificate is proudly presented to</div>
    <div class="name">{{ $certificate->participant_name }}</div>
    <div class="subtitle">for successfully completing</div>
    <div class="event">{{ $certificate->event_title }}</div>
    <div class="details">
        {{ $certificate->category_name }}
        @if ($certificate->distance_km) &bull; {{ $certificate->distance_km }} km @endif
        <br>
        {{ optional($certificate->event_date)->format('F j, Y') }}
        @if ($certificate->venue) &bull; {{ $certificate->venue }} @endif
        <br>
        Official Finish Time: {{ $certificate->finish_time ?: 'Finisher' }}
    </div>
    <div class="footer">
        <div class="verify">
            Certificate No. {{ $certificate->certificate_number }}<br>
            Issued {{ optional($certificate->issued_at)->format('F j, Y') }}<br>
            Scan the QR code or visit the verification page to confirm authenticity.<br>
            {{ $verificationUrl }}
        </div>
        <img class="qr" src="{{ $qrDataUri }}" alt="Verification QR code">
    </div>
</div></div>
</body>
</html>
