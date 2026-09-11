<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
{{-- Inline styles only: email clients strip <style> blocks unpredictably. --}}
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">

                <tr>
                    <td style="padding:20px 24px;border-bottom:1px solid #e2e8f0;">
                        <span style="font-size:15px;font-weight:700;color:#2670c9;">{{ config('app.name') }}</span>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 16px;font-size:15px;">
                            Hi {{ explode(' ', $recipient->name)[0] }},
                        </p>

                        @if ($isDigest)
                            <p style="margin:0 0 20px;font-size:14px;color:#475569;">
                                Here is what happened while you were away.
                            </p>
                        @endif

                        @foreach ($items as $item)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="margin-bottom:12px;border:1px solid #e2e8f0;border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0 0 4px;font-size:14px;font-weight:600;">
                                            {{ $item->title }}
                                            @if (($item->event_count ?? 1) > 1)
                                                <span style="color:#2670c9;font-weight:700;">&times;{{ $item->event_count }}</span>
                                            @endif
                                        </p>

                                        @if ($item->body)
                                            <p style="margin:0 0 8px;font-size:13px;color:#475569;line-height:1.5;">
                                                {{ $item->body }}
                                            </p>
                                        @endif

                                        @if ($item->link)
                                            <a href="{{ $appUrl . $item->link }}"
                                               style="display:inline-block;font-size:13px;font-weight:600;color:#2670c9;text-decoration:none;">
                                                Open &rarr;
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @endforeach

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px;">
                            <tr>
                                <td style="background-color:#2670c9;border-radius:8px;">
                                    <a href="{{ $appUrl }}/notifications"
                                       style="display:inline-block;padding:10px 18px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">
                                        View all notifications
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 24px;border-top:1px solid #e2e8f0;">
                        <p style="margin:0;font-size:12px;color:#64748b;line-height:1.5;">
                            You are receiving this because of your notification settings.
                            <a href="{{ $appUrl }}/settings/notifications" style="color:#2670c9;text-decoration:none;">
                                Change what you receive
                            </a>.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
