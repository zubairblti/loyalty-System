<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $recipient, string $message): void
    {
        Log::info('Development SMS', ['recipient' => $recipient, 'message' => $message]);
    }
}
