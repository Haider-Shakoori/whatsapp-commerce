<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantMerchantSubscription extends Command
{
    protected $signature = 'commerce:subscription:grant
        {merchant : Exact merchant slug}
        {plan : Subscription plan code}
        {--months=1 : Number of months, 1 to 24}
        {--confirm : Explicit platform operator approval}';

    protected $description = 'Manually grant a subscription entitlement (does NOT record or collect a payment)';

    public function handle(): int
    {
        $merchant = Merchant::where('slug', $this->argument('merchant'))->first();
        $plan = SubscriptionPlan::where('code', $this->argument('plan'))
            ->where('is_active', true)->first();
        $months = filter_var($this->option('months'), FILTER_VALIDATE_INT);
        if (! $merchant || ! $plan || $plan->code === 'trial' || $months === false
            || $months < 1 || $months > 24) {
            $this->error('Merchant, paid-tier plan or duration is invalid.');

            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->warn('Dry run only. Re-run with --confirm after validating your grant authorization.');
            $this->line("Would grant {$merchant->slug}: {$plan->code} for {$months} months. NO payment is taken.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($merchant, $plan, $months): void {
            $sub = MerchantSubscription::where('merchant_id', $merchant->id)
                ->lockForUpdate()->firstOrFail();
            $extending = $sub->subscription_plan_id === $plan->id && $sub->isEntitled();
            $start = $extending ? $sub->period_start->copy() : now();
            $end = $extending ? $sub->period_end->copy() : now();
            $end->addMonthsNoOverflow($months);
            $sub->update([
                'subscription_plan_id' => $plan->id, 'status' => 'active',
                'period_start' => $start, 'period_end' => $end, 'auto_renew' => false,
                'provider' => null, 'provider_subscription_id' => null,
                'cancelled_at' => null,
            ]);
            $merchant->subscriptionEvents()->create([
                'merchant_subscription_id' => $sub->id,
                'event' => 'operator_grant',
                'details' => ['plan' => $plan->code, 'months' => $months, 'source' => 'artisan'],
            ]);
        });

        $this->info('Subscription entitlement granted; billing remains uncollected.');

        return self::SUCCESS;
    }
}
