<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEvent extends Model
{
    protected $fillable = ['merchant_id', 'messaging_channel_id', 'provider_event_id', 'payload', 'processed_at'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'processed_at' => 'datetime'];
    }
}
