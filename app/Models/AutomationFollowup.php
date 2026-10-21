<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationFollowup extends Model
{
    protected $fillable = [
        'merchant_id', 'conversation_id', 'created_by', 'latest_inbound_id',
        'body', 'due_at', 'status', 'outbound_message_id',
    ];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'due_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
