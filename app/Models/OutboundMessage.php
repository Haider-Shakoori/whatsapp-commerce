<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMessage extends Model
{
    protected $fillable = [
        'merchant_id', 'messaging_channel_id', 'recipient', 'body', 'idempotency_key',
        'status', 'provider_message_id', 'attempts', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'sent_at' => 'datetime'];
    }
}
