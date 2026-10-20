<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'merchant_id', 'messaging_channel_id', 'checkout_key', 'customer_name',
        'customer_phone', 'status', 'payment_status', 'currency', 'subtotal_minor',
        'delivery_minor', 'total_minor', 'delivery_details', 'notes',
    ];

    protected function casts(): array
    {
        return ['delivery_details' => 'array', 'subtotal_minor' => 'integer', 'delivery_minor' => 'integer', 'total_minor' => 'integer'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
