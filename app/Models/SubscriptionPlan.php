<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'currency', 'price_minor', 'interval_months',
        'trial_days', 'max_channels', 'max_team_members', 'max_products',
        'max_monthly_outbound', 'allow_automation', 'allow_ai_drafts', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'trial_days' => 'integer',
            'interval_months' => 'integer',
            'max_channels' => 'integer',
            'max_team_members' => 'integer',
            'max_products' => 'integer',
            'max_monthly_outbound' => 'integer',
            'allow_automation' => 'boolean',
            'allow_ai_drafts' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
