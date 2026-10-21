<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'public_id', 'merchant_id', 'merchant_subscription_id', 'status', 'currency',
        'amount_minor', 'external_reference', 'issued_at', 'due_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'issued_at' => 'datetime',
            'due_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }
}
