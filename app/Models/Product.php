<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'merchant_id', 'category_id', 'name', 'slug', 'sku', 'description',
        'price_minor', 'currency', 'stock_quantity', 'is_active',
    ];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'stock_quantity' => 'integer', 'is_active' => 'boolean'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class);
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
