<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4f8;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader ?? $heading }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef4f8;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #dce5ec;border-radius:20px;overflow:hidden;box-shadow:0 10px 28px rgba(15,35,55,.08);">
                    <tr>
                        <td style="background:#10243e;padding:24px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="46" valign="middle">
                                        <div style="width:38px;height:38px;line-height:38px;text-align:center;border-radius:12px;background:#38bdf8;color:#10243e;font-size:20px;font-weight:800;">R</div>
                                    </td>
                                    <td valign="middle" style="color:#ffffff;font-size:21px;font-weight:800;letter-spacing:.5px;">Racetech</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px 32px;">
                            <p style="margin:0 0 10px;color:#1685b8;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;">Account notification</p>
                            <h1 style="margin:0 0 22px;color:#172033;font-size:28px;line-height:1.25;font-weight:800;">{{ $heading }}</h1>
                            <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.65;">{{ $greeting ?? 'Hello,' }}</p>

                            @foreach (($intro ?? []) as $paragraph)
                                <p style="margin:0 0 16px;color:#526173;font-size:15px;line-height:1.7;">{{ $paragraph }}</p>
                            @endforeach

                            @isset($code)
                                <div style="margin:26px 0;padding:22px;border:1px solid #b9e5f7;border-radius:16px;background:#f0faff;text-align:center;">
                                    <p style="margin:0 0 8px;color:#577082;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;">Your secure code</p>
                                    <p style="margin:0;color:#10243e;font-family:'Courier New',monospace;font-size:34px;line-height:1.2;font-weight:800;letter-spacing:8px;">{{ $code }}</p>
                                </div>
                            @endisset

                            @if (! empty($details))
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td style="padding:12px 16px;color:#64748b;font-size:13px;{{ ! $loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}">{{ $label }}</td>
                                            <td align="right" style="padding:12px 16px;color:#172033;font-size:13px;font-weight:700;{{ ! $loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @isset($actionUrl)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0;">
                                    <tr>
                                        <td style="border-radius:12px;background:#1685b8;">
                                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">{{ $actionLabel ?? 'Continue' }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 20px;color:#64748b;font-size:12px;line-height:1.6;">If the button does not work, copy and paste this link into your browser:<br><a href="{{ $actionUrl }}" style="color:#1685b8;word-break:break-all;">{{ $actionUrl }}</a></p>
                            @endisset

                            @isset($securityNotice)
                                <div style="margin:24px 0;padding:14px 16px;border-left:4px solid #f59e0b;border-radius:8px;background:#fffbeb;color:#78520a;font-size:13px;line-height:1.6;">
                                    <strong>Security reminder:</strong> {{ $securityNotice }}
                                </div>
                            @endisset

                            @isset($closing)
                                <p style="margin:22px 0 0;color:#526173;font-size:14px;line-height:1.7;">{{ $closing }}</p>
                            @endisset
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e2e8f0;background:#f8fafc;padding:22px 32px;color:#738194;font-size:12px;line-height:1.6;text-align:center;">
                            This automated message was sent by Racetech. Please do not share its secure links or codes.<br>
                            &copy; {{ now()->year }} Racetech. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
