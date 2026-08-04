<?php

namespace App\Console\Commands;

use App\Models\CustomerMembership;
use App\Models\LoyaltySetting;
use App\Services\LoyaltyService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class ProcessMembershipDowngrades extends Command
{
    protected $signature = 'memberships:process-downgrades';

    protected $description = 'Apply membership downgrades whose recovery grace period has expired';

    public function handle(TenantContext $tenancy, LoyaltyService $loyalty): int
    {
        $tenancy->activateSystem();

        try {
            CustomerMembership::with('customer')->whereNull('ended_at')
                ->whereNotNull('grace_expires_at')->where('grace_expires_at', '<=', now())
                ->orderBy('id')->chunkById(100, function ($memberships) use ($tenancy, $loyalty) {
                    foreach ($memberships as $membership) {
                        $tenancy->activate($membership->business_id);
                        $settings = LoyaltySetting::where('business_id', $membership->business_id)->first();
                        if (! $settings?->loyalty_enabled || ! $settings->memberships_enabled) {
                            $tenancy->activateSystem();
                            continue;
                        }
                        $loyalty->membership($membership->customer, $loyalty->balance($membership->customer_id));
                        $tenancy->activateSystem();
                    }
                });
        } finally {
            $tenancy->clear();
        }

        return self::SUCCESS;
    }
}
