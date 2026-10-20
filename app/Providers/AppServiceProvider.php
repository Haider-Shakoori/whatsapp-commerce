<?php

namespace App\Providers;

use App\Messaging\Contracts\MessagingTransport;
use App\Messaging\Drivers\DisabledMessagingTransport;
use App\Messaging\Drivers\RelayWaMessagingTransport;
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
        //
    }
}
