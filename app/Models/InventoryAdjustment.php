<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $fillable = ['merchant_id', 'product_id', 'user_id', 'delta', 'before_quantity', 'after_quantity', 'reason'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
