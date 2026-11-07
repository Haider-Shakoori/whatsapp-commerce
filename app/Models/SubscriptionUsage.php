<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionUsage extends Model
{
    protected $fillable = ['merchant_id', 'usage_month', 'outbound_count'];

    protected function casts(): array
    {
        return ['outbound_count' => 'integer'];
    }
}
