<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password — KABUTECH</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:40px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

                {{-- Header --}}
                <tr>
                    <td align="center" style="padding-bottom:28px;">
                        <div style="font-size:22px;font-weight:700;color:#e2e8f0;letter-spacing:2px;">🍄 KABUTECH</div>
                        <div style="font-size:12px;color:#64748b;margin-top:4px;">Mushroom Monitoring System</div>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:36px 32px;">

                        <p style="margin:0 0 8px;font-size:20px;font-weight:600;color:#e2e8f0;">Password Reset Request</p>
                        <p style="margin:0 0 24px;font-size:14px;color:#94a3b8;line-height:1.6;">
                            Hi <strong style="color:#e2e8f0;">{{ $user->name }}</strong>,<br>
                            We received a request to reset the password for your KABUTECH account.
                            Click the button below to choose a new password.
                        </p>

                        {{-- CTA Button --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="padding:8px 0 28px;">
                                    <a href="{{ $resetUrl }}"
                                       style="display:inline-block;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 36px;border-radius:8px;letter-spacing:.5px;">
                                        Reset Password
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 12px;font-size:13px;color:#64748b;line-height:1.6;">
                            This link will expire in <strong style="color:#94a3b8;">{{ $expiry }} minutes</strong>.
                            If you did not request a password reset, you can safely ignore this email — your password will not change.
                        </p>

                        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin:24px 0;">

                        <p style="margin:0;font-size:12px;color:#475569;">
                            If the button above doesn't work, copy and paste this link into your browser:<br>
                            <a href="{{ $resetUrl }}" style="color:#22c55e;word-break:break-all;font-size:11px;">{{ $resetUrl }}</a>
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding-top:24px;">
                        <p style="margin:0;font-size:12px;color:#334155;">
                            © {{ date('Y') }} KABUTECH · Automated Mushroom Monitoring System
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
