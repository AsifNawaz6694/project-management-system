<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Verification code</title>
</head>
<body style="margin:0;padding:32px;background:#f4f5f7;font-family:'Inter','Segoe UI',Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden;">
        <tr>
            <td style="padding:32px 32px 16px;">
                <div style="font-size:13px;letter-spacing:0.08em;color:#64748b;text-transform:uppercase;">{{ config('app.name') }}</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:600;color:#0f172a;">Verify it's you</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 32px 24px;color:#475569;font-size:14px;line-height:22px;">
                Hi {{ $user->name }}, use the code below to finish signing in. It expires in {{ $minutes }} minutes.
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:0 32px 24px;">
                <div style="display:inline-block;padding:18px 28px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;font-size:32px;letter-spacing:14px;font-weight:600;color:#0f172a;">
                    {{ $code }}
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 32px;color:#94a3b8;font-size:12px;line-height:20px;">
                If you didn't try to sign in, you can safely ignore this email — your account is still secure.
            </td>
        </tr>
    </table>
</body>
</html>
