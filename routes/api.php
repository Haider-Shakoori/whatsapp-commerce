<?php

use App\Http\Controllers\RelayWaWebhookController;
use App\Http\Controllers\StripeTestWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/relaywa/{publicId}/webhook', RelayWaWebhookController::class)
    ->middleware('throttle:relaywa-inbound')
    ->name('api.relaywa.webhook');

Route::post('/payments/stripe-test/{token}/webhook', StripeTestWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.stripe-test.webhook');
