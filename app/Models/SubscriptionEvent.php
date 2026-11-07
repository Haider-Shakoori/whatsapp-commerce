<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    protected $fillable = ['merchant_id', 'merchant_subscription_id', 'event', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
