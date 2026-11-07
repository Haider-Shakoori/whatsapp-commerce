<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'merchant_id', 'shopping_cart_id', 'product_id', 'product_variant_id', 'selection_key',
        'product_name', 'variant_name', 'selected_options', 'quantity', 'unit_price_minor',
    ];

    protected function casts(): array
    {
        return ['selected_options' => 'array', 'quantity' => 'integer', 'unit_price_minor' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'shopping_cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
