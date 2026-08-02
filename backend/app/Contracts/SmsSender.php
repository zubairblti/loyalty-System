<?php

namespace App\Contracts;

interface SmsSender
{
    public function send(string $recipient, string $message): void;
}
