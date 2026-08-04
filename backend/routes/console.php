<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:process-expirations')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('payments:expire-stale-sessions')->hourly()->withoutOverlapping();
Schedule::command('memberships:process-downgrades')->dailyAt('01:00')->withoutOverlapping();
