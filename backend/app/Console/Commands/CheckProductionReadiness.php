<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'app:readiness';

    protected $description = 'Check production configuration required by realtime notifications and background jobs';

    public function handle(): int
    {
        $checks = [
            'Pusher app key' => filled(config('broadcasting.connections.pusher.key')),
            'Pusher app secret' => filled(config('broadcasting.connections.pusher.secret')),
            'Pusher app ID' => filled(config('broadcasting.connections.pusher.app_id')),
            'Broadcast driver is Pusher' => config('broadcasting.default') === 'pusher',
            'Queue is asynchronous' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'Application URL configured' => filled(config('app.url')),
            'Frontend URL configured' => filled(config('app.frontend_url')),
        ];
        foreach ($checks as $label => $passed) {
            $this->{$passed ? 'info' : 'error'}(($passed ? 'PASS' : 'FAIL')."  {$label}");
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
