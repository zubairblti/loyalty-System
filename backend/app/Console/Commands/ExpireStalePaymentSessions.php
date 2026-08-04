<?php

namespace App\Console\Commands;

use App\Models\PaymentSubmission;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class ExpireStalePaymentSessions extends Command
{
    protected $signature = 'payments:expire-stale-sessions';

    protected $description = 'Mark stale Safepay checkout sessions as abandoned';

    public function handle(TenantContext $tenancy): int
    {
        $tenancy->activateSystem();

        try {
            PaymentSubmission::where('status', 'initiated')->where('created_at', '<', now()->subHour())
                ->update(['status' => 'abandoned']);
            PaymentSubmission::where('status', 'processing')->where('updated_at', '<', now()->subDay())
                ->update(['status' => 'abandoned']);
        } finally {
            $tenancy->clear();
        }

        return self::SUCCESS;
    }
}
