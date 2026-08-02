<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ str_contains($purpose, 'registration') ? 'Verify your account' : 'Reset your password' }}</title>
</head>
<body style="margin:0;background:#edf2ef;font-family:Arial,Helvetica,sans-serif;color:#17211d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf2ef;padding:32px 14px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #d9e2dd;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:#173b2f;padding:24px 30px;">
                        <table role="presentation" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width:40px;height:40px;text-align:center;background:#efb73e;color:#17211d;border-radius:6px;font-size:20px;font-weight:800;">L</td>
                                <td style="padding-left:12px;color:#ffffff;font-size:18px;font-weight:700;">LoyaltyOS</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:42px 34px 34px;text-align:center;">
                        <div style="width:50px;height:50px;line-height:50px;margin:0 auto 20px;background:#e8f3ee;color:#176b4b;border-radius:50%;font-size:23px;font-weight:800;">@</div>
                        <p style="margin:0 0 8px;color:#98761f;font-size:10px;font-weight:800;letter-spacing:1px;">SECURE VERIFICATION</p>
                        <h1 style="margin:0 0 12px;font-size:26px;line-height:1.25;color:#17211d;">
                            {{ str_contains($purpose, 'registration') ? 'Verify your email' : (str_contains($purpose, 'phone') ? 'Verify your mobile number' : 'Reset your password') }}
                        </h1>
                        <p style="margin:0 auto 26px;max-width:430px;color:#68756f;font-size:14px;line-height:1.6;">
                            {{ str_contains($purpose, 'registration')
                                ? (str_starts_with($purpose, 'customer_')
                                    ? 'Use this code to finish creating your customer rewards account.'
                                    : 'Use this code to finish creating your business workspace.')
                                : (str_contains($purpose, 'phone')
                                    ? 'Use this code to confirm your new mobile number.'
                                    : 'Use this code to continue resetting your LoyaltyOS password.') }}
                        </p>
                        <div style="display:inline-block;padding:16px 28px;border:1px solid #d5ded9;border-radius:6px;background:#f8faf9;color:#173b2f;font-family:Consolas,Monaco,monospace;font-size:30px;font-weight:800;letter-spacing:9px;">
                            {{ $code }}
                        </div>
                        <p style="margin:18px 0 0;color:#a14c4c;font-size:12px;font-weight:700;">This code expires in 2 minutes.</p>
                        <p style="margin:28px auto 0;max-width:430px;color:#829089;font-size:11px;line-height:1.55;">
                            If you did not request this code, you can safely ignore this email. Never share this code with anyone.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 30px;background:#f5f7f6;border-top:1px solid #e2e8e5;text-align:center;color:#85918b;font-size:10px;">
                        LoyaltyOS · Secure loyalty operations for modern businesses
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
