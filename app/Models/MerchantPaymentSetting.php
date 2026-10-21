<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPaymentSetting extends Model
{
    protected $fillable = [
        'merchant_id', 'cod_enabled', 'bank_transfer_enabled', 'bank_instructions',
        'stripe_test_enabled', 'stripe_test_secret', 'stripe_test_webhook_secret',
        'webhook_token',
    ];

    protected $hidden = ['stripe_test_secret', 'stripe_test_webhook_secret', 'bank_instructions'];

    protected function casts(): array
    {
        return [
            'cod_enabled' => 'boolean',
            'bank_transfer_enabled' => 'boolean',
            'stripe_test_enabled' => 'boolean',
            'stripe_test_secret' => 'encrypted',
            'stripe_test_webhook_secret' => 'encrypted',
            'bank_instructions' => 'encrypted',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
