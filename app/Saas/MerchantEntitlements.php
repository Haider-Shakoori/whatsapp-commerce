<?php

namespace App\Saas;

use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\PlatformSetting;
use App\Models\SubscriptionUsage;
use App\Saas\Exceptions\SubscriptionLimitException;

final class MerchantEntitlements
{
    public function current(Merchant $merchant): MerchantSubscription
    {
        $subscription = $merchant->subscription()->with('plan')->first();
        if ($merchant->fresh()?->status !== 'active'
            || ! $subscription || ! $subscription->isEntitled() || ! $subscription->plan?->is_active) {
            throw new SubscriptionLimitException('Your subscription is not active. Review billing or contact support.');
        }

        return $subscription;
    }

    public function assertFeature(Merchant $merchant, string $feature): void
    {
        $plan = $this->current($merchant)->plan;
        $flag = match ($feature) {
            'automation' => 'allow_automation',
            'ai_drafts' => 'allow_ai_drafts',
            default => throw new SubscriptionLimitException('Unsupported feature.'),
        };
        if (! $plan->$flag) {
            throw new SubscriptionLimitException('Your plan does not include this feature.');
        }
    }

    /**
     * Must be invoked inside the same transaction that creates a resource.
     * Locking the subscription serializes concurrent capacity checks per merchant.
     */
    public function assertCapacity(Merchant $merchant, string $resource, int $increase = 1): void
    {
        $subscription = MerchantSubscription::where('merchant_id', $merchant->id)
            ->lockForUpdate()->first();
        if ($merchant->fresh()?->status !== 'active'
            || ! $subscription || ! $subscription->isEntitled() || ! $subscription->plan?->is_active) {
            throw new SubscriptionLimitException('Your subscription is inactive or expired.');
        }
        [$used, $limit] = match ($resource) {
            'channels' => [$merchant->messagingChannels()->count(), $subscription->plan->max_channels],
            'products' => [$merchant->products()->count(), $subscription->plan->max_products],
            'members' => [$merchant->users()->count(), $subscription->plan->max_team_members],
            default => throw new SubscriptionLimitException('Unknown resource limit.'),
        };
        if ($increase < 0 || $used + $increase > $limit) {
            throw new SubscriptionLimitException('Your plan limit for '.$resource.' has been reached.');
        }
    }

    /**
     * Count only newly queued messages; duplicate idempotency keys are not charged.
     * UTC month YYYY-MM is the metering period, not the subscription renewal date.
     */
    public function assertOutboundAllowed(Merchant $merchant): void
    {
        if (PlatformSetting::enabled('outbound_paused')) {
            throw new SubscriptionLimitException('Platform outbound messaging is temporarily paused.');
        }

        $this->current($merchant);
    }

    public function consumeOutbound(Merchant $merchant): void
    {
        $this->assertOutboundAllowed($merchant);
        $subscription = MerchantSubscription::where('merchant_id', $merchant->id)
            ->lockForUpdate()->first();
        if ($merchant->fresh()?->status !== 'active'
            || ! $subscription || ! $subscription->isEntitled() || ! $subscription->plan?->is_active) {
            throw new SubscriptionLimitException('Subscription inactive; sending is disabled.');
        }

        $month = now('UTC')->format('Y-m');
        $usage = SubscriptionUsage::firstOrCreate([
            'merchant_id' => $merchant->id, 'usage_month' => $month,
        ]);
        $usage = SubscriptionUsage::whereKey($usage->id)->lockForUpdate()->firstOrFail();
        if ($usage->outbound_count >= $subscription->plan->max_monthly_outbound) {
            throw new SubscriptionLimitException('Monthly outgoing message allowance reached.');
        }
        $usage->increment('outbound_count');
    }

    public function summary(Merchant $merchant): array
    {
        $sub = $merchant->subscription()->with('plan')->first();
        $month = now('UTC')->format('Y-m');

        return [
            'subscription' => $sub,
            'plan' => $sub?->plan,
            'month' => $month,
            'messages' => (int) ($merchant->subscriptionUsages()->where('usage_month', $month)->value('outbound_count') ?? 0),
            'channels' => $merchant->messagingChannels()->count(),
            'members' => $merchant->users()->count(),
            'products' => $merchant->products()->count(),
            'active' => $sub?->isEntitled() && $sub->plan?->is_active,
        ];
    }
}
