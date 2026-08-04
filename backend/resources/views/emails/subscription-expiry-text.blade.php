LoyaltyOS subscription reminder

{{ $forAdmin ? $business->name.' has' : 'Your business has' }} {{ $daysRemaining }} days remaining on the {{ $subscription->plan->name }} plan.

Expiry date: {{ $subscription->ends_at->format('d M Y') }}
Billing cycle: {{ ucfirst($subscription->billing_cycle) }}
Amount paid: PKR {{ number_format((float) $subscription->amount_paid, 2) }}

The workspace will be automatically deactivated when the subscription expires unless a new payment is activated.
