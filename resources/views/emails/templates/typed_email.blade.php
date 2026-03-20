<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $subject }}</title>
    </head>
    <body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:16px;padding:32px;">
                        <tr>
                            <td>
                                <p style="margin:0 0 12px;color:#0891b2;font-size:14px;font-weight:700;">{{ config('app.name') }}</p>
                                <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;color:#0f172a;">{{ $heading }}</h1>
                                <p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#334155;">{{ $intro }}</p>

                                @if (! empty($actionUrl) && ! empty($actionLabel))
                                    <p style="margin:0 0 24px;">
                                        <a
                                            href="{{ $actionUrl }}"
                                            style="display:inline-block;padding:12px 20px;border-radius:12px;background:{{ $buttonBackground ?? '#22d3ee' }};color:{{ $buttonTextColor ?? '#082f49' }};text-decoration:none;font-weight:700;"
                                        >
                                            {{ $actionLabel }}
                                        </a>
                                    </p>

                                    <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;word-break:break-all;">
                                        {{ $actionHint ?? __('mail.common.action_hint') }}：{{ $actionUrl }}
                                    </p>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
