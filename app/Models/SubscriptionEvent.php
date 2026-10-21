<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    protected $fillable = ['merchant_id', 'merchant_subscription_id', 'event', 'details'];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
