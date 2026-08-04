<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Subscription reminder</title></head>
<body style="margin:0;background:#edf2ef;font-family:Arial,sans-serif;color:#14231d">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;background:#edf2ef"><tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #dbe4df;border-radius:8px;overflow:hidden">
<tr><td style="padding:22px 28px;background:#173b2f;color:#fff;font-size:18px;font-weight:700">LoyaltyOS</td></tr>
<tr><td style="padding:30px 28px"><div style="font-size:11px;font-weight:700;color:#8a6d21">SUBSCRIPTION REMINDER</div>
<h1 style="font-size:24px;margin:9px 0 12px">{{ $daysRemaining }} days remaining</h1>
<p style="font-size:14px;line-height:1.6;color:#5f6d66">{{ $forAdmin ? $business->name.' has' : 'Your business has' }} {{ $daysRemaining }} days remaining on the <strong>{{ $subscription->plan->name }}</strong> plan.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:22px 0;background:#f3f6f4;border:1px solid #e0e7e3"><tr><td style="padding:14px;font-size:13px">Expiry date</td><td align="right" style="padding:14px;font-size:13px;font-weight:700">{{ $subscription->ends_at->format('d M Y') }}</td></tr><tr><td style="padding:0 14px 14px;font-size:13px">Billing cycle</td><td align="right" style="padding:0 14px 14px;font-size:13px">{{ ucfirst($subscription->billing_cycle) }}</td></tr><tr><td style="padding:0 14px 14px;font-size:13px">Amount paid</td><td align="right" style="padding:0 14px 14px;font-size:13px">PKR {{ number_format((float) $subscription->amount_paid, 2) }}</td></tr></table>
<p style="font-size:12px;line-height:1.6;color:#6d7973">The workspace will be automatically deactivated when the subscription expires unless a new payment is activated.</p>
</td></tr></table></td></tr></table>
</body></html>
