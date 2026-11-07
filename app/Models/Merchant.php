<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Merchant extends Model
{
    protected $fillable = ['name', 'slug', 'business_type', 'currency', 'timezone', 'status'];

    protected static function booted(): void
    {
        static::created(function (self $merchant): void {
            // Every new merchant receives one bounded evaluation. Never
            // auto-convert to a paid tier or start an external payment.
            $trial = SubscriptionPlan::where('code', 'trial')->firstOrFail();
            $sub = $merchant->subscription()->create([
                'subscription_plan_id' => $trial->id,
                'status' => 'trialing',
                'period_start' => now(),
                'period_end' => now()->addDays($trial->trial_days),
                'auto_renew' => false,
            ]);
            $merchant->subscriptionEvents()->create([
                'merchant_subscription_id' => $sub->id, 'event' => 'trial_started',
                'details' => ['plan' => 'trial', 'days' => $trial->trial_days],
            ]);
        });
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(MerchantSubscription::class);
    }

    public function subscriptionUsages(): HasMany
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    public function subscriptionEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function messagingChannels(): HasMany
    {
        return $this->hasMany(MessagingChannel::class);
    }

    public function paymentSetting(): HasOne
    {
        return $this->hasOne(MerchantPaymentSetting::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function automationSetting(): HasOne
    {
        return $this->hasOne(AutomationSetting::class);
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(AutomationRule::class);
    }

    public function automationFollowups(): HasMany
    {
        return $this->hasMany(AutomationFollowup::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(ShoppingCart::class);
    }
}
