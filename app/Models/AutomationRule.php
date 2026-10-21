<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    protected $fillable = ['merchant_id', 'keyword', 'reply_text', 'assign_to', 'priority', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'priority' => 'integer'];
    }
}
