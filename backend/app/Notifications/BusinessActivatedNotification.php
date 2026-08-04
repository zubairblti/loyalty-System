<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\PaymentSubmission;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Business $business,
        public Subscription $subscription,
        public PaymentSubmission $payment,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your LoyaltyOS workspace is active')
            ->greeting("Hello {$notifiable->name},")
            ->line("Payment for {$this->subscription->plan->name} has been approved.")
            ->line('Amount: PKR '.number_format((float) $this->payment->amount, 2))
            ->line('Subscription expiry: '.$this->subscription->ends_at->format('d M Y'))
            ->action('Open workspace', config('app.frontend_url'))
            ->line('Your business workspace is now active. Complete the business profile to continue.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'business_activated',
            'business_id' => $this->business->id,
            'title' => 'Workspace activated',
            'message' => "{$this->subscription->plan->name} is active until {$this->subscription->ends_at->format('d M Y')}.",
            'action_url' => '/',
            'subscription_id' => $this->subscription->id,
            'payment_id' => $this->payment->id,
        ];
    }
}
