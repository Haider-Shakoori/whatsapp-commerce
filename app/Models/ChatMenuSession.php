<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMenuSession extends Model
{
    protected $fillable = [
        'merchant_id', 'messaging_channel_id', 'customer_phone',
        'screen', 'category_id', 'page', 'choice_ids', 'opted_out',
        'selected_product_id', 'pending_variant_id', 'pending_quantity',
        'pending_options', 'pending_group_index', 'pending_variant_page',
        'checkout_step', 'checkout_token', 'checkout_customer_name',
        'checkout_method', 'checkout_address',
    ];

    protected $hidden = ['checkout_address'];

    protected function casts(): array
    {
        return [
            'choice_ids' => 'array',
            'opted_out' => 'boolean',
            'page' => 'integer',
            'pending_quantity' => 'integer',
            'pending_options' => 'array',
            'pending_group_index' => 'integer',
            'pending_variant_page' => 'integer',
            'checkout_address' => 'encrypted',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingChannel::class, 'messaging_channel_id');
    }
}
