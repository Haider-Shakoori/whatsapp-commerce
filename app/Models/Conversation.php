<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'merchant_id', 'messaging_channel_id', 'customer_id', 'assigned_to',
        'status', 'unread_count', 'last_message_at', 'last_automation_at',
    ];

    protected function casts(): array
    {
        return ['unread_count' => 'integer', 'last_message_at' => 'datetime', 'last_automation_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'messaging_channel_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }
}
