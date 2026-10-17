RACETECH

{{ $heading }}

{{ $greeting ?? 'Hello,' }}

@foreach (($intro ?? []) as $paragraph)
{{ $paragraph }}

@endforeach
@isset($code)
YOUR SECURE CODE: {{ $code }}

@endisset
@if (! empty($details))
@foreach ($details as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

@endif
@isset($actionUrl)
{{ $actionLabel ?? 'Continue' }}: {{ $actionUrl }}

@endisset
@isset($securityNotice)
SECURITY REMINDER: {{ $securityNotice }}

@endisset
@isset($closing)
{{ $closing }}

@endisset
This automated message was sent by Racetech. Please do not share its secure links or codes.
Copyright {{ now()->year }} Racetech. All rights reserved.
