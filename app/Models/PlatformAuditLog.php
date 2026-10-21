<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'merchant_id', 'action', 'target_type',
        'target_id', 'reason', 'changes',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }
}
