<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioSmsSender implements SmsSender
{
    public function send(string $recipient, string $message): void
    {
        $sid = config('services.sms.twilio.account_sid');
        $token = config('services.sms.twilio.auth_token');
        $from = config('services.sms.twilio.from');
        $messagingServiceSid = config('services.sms.twilio.messaging_service_sid');

        if (! $sid || ! $token || (! $from && ! $messagingServiceSid)) {
            throw new RuntimeException('Twilio SMS credentials are incomplete.');
        }

        $payload = ['To' => $recipient, 'Body' => $message];
        $payload[$messagingServiceSid ? 'MessagingServiceSid' : 'From'] = $messagingServiceSid ?: $from;

        Http::asForm()->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload)
            ->throw();
    }
}
