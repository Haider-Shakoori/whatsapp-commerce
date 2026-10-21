<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'public_id', 'merchant_id', 'order_id', 'method', 'status',
        'currency', 'amount_minor', 'provider_session_id', 'provider_payment_id',
        'payment_url', 'reference', 'review_note', 'verified_by', 'verified_at', 'expires_at',
    ];

    protected $hidden = ['payment_url'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payment_url' => 'encrypted',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
