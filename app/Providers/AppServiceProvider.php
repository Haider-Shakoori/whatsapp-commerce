<?php

namespace App\Providers;

use App\Messaging\Contracts\MessagingTransport;
use App\Messaging\Drivers\DisabledMessagingTransport;
use App\Messaging\Drivers\RelayWaMessagingTransport;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MessagingTransport::class, function ($app): MessagingTransport {
            return config('commerce.messaging_driver') === 'relaywa'
                ? $app->make(RelayWaMessagingTransport::class)
                : $app->make(DisabledMessagingTransport::class);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('relaywa-inbound', function (Request $request): Limit {
            return Limit::perMinute(300)->by((string) $request->route('publicId').'|'.$request->ip());
        });
    }
}
