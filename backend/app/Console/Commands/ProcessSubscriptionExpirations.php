<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiryReminderMail;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ProcessSubscriptionExpirations extends Command
{
    protected $signature = 'subscriptions:process-expirations';

    protected $description = 'Queue subscription expiry reminders and deactivate expired businesses';

    public function handle(TenantContext $tenancy, AuditLogger $audit): int
    {
        $tenancy->activateSystem();

        try {
            foreach ([10, 5, 3] as $days) {
                Subscription::with(['business.owner', 'plan'])
                    ->where('status', 'active')
                    ->whereDate('ends_at', now()->startOfDay()->addDays($days))
                    ->each(fn (Subscription $subscription) => $this->sendReminder($subscription, $days, $audit));
            }

            Subscription::with('business')->where('status', 'active')->where('ends_at', '<=', now())
                ->each(function (Subscription $subscription) use ($audit) {
                    DB::transaction(function () use ($subscription, $audit) {
                        $subscription->update(['status' => 'expired']);
                        $subscription->business->update(['status' => 'expired', 'active' => false]);
                        $audit->log('subscription.expired', $subscription, ['status' => 'active'], [
                            'status' => 'expired', 'ends_at' => $subscription->ends_at,
                        ], $subscription->business_id);
                    });
                });
        } finally {
            $tenancy->clear();
        }

        return self::SUCCESS;
    }

    private function sendReminder(Subscription $subscription, int $days, AuditLogger $audit): void
    {
        $action = "subscription.expiry_reminder_{$days}_days";
        if (AuditLog::where('action', $action)->where('auditable_type', Subscription::class)
            ->where('auditable_id', (string) $subscription->id)->exists()) {
            return;
        }

        $owner = $subscription->business->owner;
        if ($owner?->email) {
            Mail::to($owner->email)->queue(new SubscriptionExpiryReminderMail(
                $subscription->business, $subscription, $days,
            ));
        }
        User::where('role', 'super_admin')->whereNotNull('email')->pluck('email')->unique()
            ->each(fn (string $email) => Mail::to($email)->queue(new SubscriptionExpiryReminderMail(
                $subscription->business, $subscription, $days, true,
            )));
        $audit->log($action, $subscription, [], [
            'days_remaining' => $days, 'ends_at' => $subscription->ends_at,
        ], $subscription->business_id);
    }
}
