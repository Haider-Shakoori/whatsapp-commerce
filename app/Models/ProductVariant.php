<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'merchant_id', 'product_id', 'name', 'sku', 'options',
        'price_delta_minor', 'stock_quantity', 'is_active',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'price_delta_minor' => 'integer', 'stock_quantity' => 'integer', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
