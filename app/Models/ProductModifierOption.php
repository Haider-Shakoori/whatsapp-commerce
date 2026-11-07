<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierOption extends Model
{
    protected $fillable = ['merchant_id', 'product_modifier_group_id', 'name', 'price_minor'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifierGroup::class, 'product_modifier_group_id');
    }
}
