<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        public Business $business,
        public Subscription $subscription,
        public int $daysRemaining,
        public bool $forAdmin = false,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->forAdmin
            ? "Subscription expiry: {$this->business->name}"
            : "Your LoyaltyOS plan expires in {$this->daysRemaining} days");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expiry',
            text: 'emails.subscription-expiry-text',
        );
    }
}
