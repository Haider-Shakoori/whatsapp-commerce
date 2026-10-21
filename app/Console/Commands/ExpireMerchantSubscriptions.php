<?php

namespace App\Console\Commands;

use App\Models\MerchantSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireMerchantSubscriptions extends Command
{
    protected $signature = 'commerce:subscription:expire';

    protected $description = 'Reconcile expired trial and subscription statuses; never auto-charge or auto-renew';

    public function handle(): int
    {
        MerchantSubscription::whereIn('status', ['active', 'trialing'])
            ->where('period_end', '<=', now())->orderBy('id')->chunkById(100, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription): void {
                        $sub = MerchantSubscription::whereKey($subscription->id)->lockForUpdate()->first();
                        if (! $sub || ! in_array($sub->status, ['active', 'trialing'], true)
                            || $sub->period_end->gt(now())) {
                            return;
                        }
                        $sub->update(['status' => 'expired']);
                        $sub->merchant->subscriptionEvents()->create([
                            'merchant_subscription_id' => $sub->id,
                            'event' => 'expired',
                            'details' => ['previous_status' => $subscription->status],
                        ]);
                    });
                }
            });

        return self::SUCCESS;
    }
}
