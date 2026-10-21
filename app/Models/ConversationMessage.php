<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'merchant_id', 'conversation_id', 'outbound_message_id',
        'provider_message_id', 'direction', 'message_type', 'body', 'message_at',
    ];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'message_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function outbound(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class, 'outbound_message_id');
    }
}
