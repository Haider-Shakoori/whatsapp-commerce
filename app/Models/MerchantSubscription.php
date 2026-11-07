<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantSubscription extends Model
{
    protected $fillable = [
        'merchant_id', 'subscription_plan_id', 'status', 'period_start',
        'period_end', 'auto_renew', 'provider', 'provider_subscription_id', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime', 'period_end' => 'datetime',
            'cancelled_at' => 'datetime', 'auto_renew' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isEntitled(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true)
            && $this->period_start?->lte(now())
            && $this->period_end?->gt(now());
    }
}
