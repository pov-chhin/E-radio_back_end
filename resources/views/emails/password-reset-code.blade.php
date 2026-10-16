<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your E-Radio password reset code</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f6fa;">
        <tr>
            <td align="center" style="padding:36px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;">
                    <tr>
                        <td align="center" style="padding-bottom:22px;">
                            <img
                                src="{{ url('/images/e-radio-ams-logo.png') }}"
                                width="96"
                                height="96"
                                alt="E-Radio AMS"
                                style="display:block;width:96px;height:96px;border:0;border-radius:20px;"
                            >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px;background:#ffffff;border-radius:16px;box-shadow:0 8px 28px rgba(27,35,58,.08);">
                            <h1 style="margin:0 0 22px;font-size:24px;line-height:1.3;color:#151c2b;">Password reset code</h1>
                            <p style="margin:0 0 14px;font-size:16px;line-height:1.6;">Hi {{ $userName }},</p>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">Use this code to reset your E-Radio password:</p>
                            <div style="margin:0 0 22px;padding:18px;text-align:center;background:#f2f0ff;border:1px solid #ded8ff;border-radius:12px;color:#3917a8;font-size:32px;font-weight:700;letter-spacing:8px;">
                                {{ $code }}
                            </div>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#566176;">
                                This code expires in {{ $validForMinutes }} {{ $validForMinutes === 1 ? 'minute' : 'minutes' }}. If you didn't ask to reset your password, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:22px;font-size:12px;line-height:1.5;color:#8a93a5;">
                            &copy; {{ date('Y') }} E-Radio AMS. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
