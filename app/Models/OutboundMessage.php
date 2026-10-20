<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    protected $fillable = [
        'merchant_id', 'messaging_channel_id', 'recipient', 'body', 'idempotency_key',
        'status', 'provider_message_id', 'attempts', 'sent_at',
    ];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'messaging_channel_id');
    }
}
