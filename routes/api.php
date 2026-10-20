<?php

use App\Http\Controllers\RelayWaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/relaywa/{publicId}/webhook', RelayWaWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.relaywa.webhook');
