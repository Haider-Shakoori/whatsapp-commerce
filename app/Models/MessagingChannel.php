<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagingChannel extends Model
{
    protected $fillable = [
        'merchant_id', 'provider', 'display_name', 'external_account_id',
        'public_id', 'credentials', 'status', 'status_checked_at', 'menu_enabled',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'status_checked_at' => 'datetime',
            'menu_enabled' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function menuSessions(): HasMany
    {
        return $this->hasMany(ChatMenuSession::class, 'messaging_channel_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(ShoppingCart::class, 'messaging_channel_id');
    }
}
