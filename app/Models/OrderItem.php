<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'merchant_id', 'order_id', 'product_id', 'product_variant_id', 'product_name',
        'variant_name', 'quantity', 'unit_price_minor', 'line_total_minor', 'options',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'quantity' => 'integer', 'unit_price_minor' => 'integer', 'line_total_minor' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
