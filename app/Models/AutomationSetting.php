<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationSetting extends Model
{
    protected $fillable = [
        'merchant_id', 'enabled', 'after_hours_enabled', 'keyword_enabled',
        'followups_enabled', 'ai_drafts_enabled', 'weekly_hours',
        'after_hours_message', 'ai_guidance',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'after_hours_enabled' => 'boolean',
            'keyword_enabled' => 'boolean',
            'followups_enabled' => 'boolean',
            'ai_drafts_enabled' => 'boolean',
            'weekly_hours' => 'array',
        ];
    }
}
