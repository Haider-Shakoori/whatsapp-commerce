<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingCart extends Model
{
    protected $fillable = ['merchant_id', 'messaging_channel_id', 'customer_phone', 'currency'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'messaging_channel_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
