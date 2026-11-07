<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMenuSession extends Model
{
    protected $fillable = [
        'merchant_id', 'messaging_channel_id', 'customer_phone',
        'screen', 'category_id', 'page', 'choice_ids', 'opted_out',
    ];

    protected function casts(): array
    {
        return ['choice_ids' => 'array', 'opted_out' => 'boolean', 'page' => 'integer'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'messaging_channel_id');
    }
}
