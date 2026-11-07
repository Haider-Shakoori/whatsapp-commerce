<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['merchant_id', 'provider', 'provider_event_id', 'payment_attempt_id', 'outcome'];
}
