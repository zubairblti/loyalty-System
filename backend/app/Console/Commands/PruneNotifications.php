<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=90}';

    protected $description = 'Delete CRM notifications older than the retention period';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $deleted = DB::table('notifications')->where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$deleted} notifications older than {$days} days.");

        return self::SUCCESS;
    }
}
