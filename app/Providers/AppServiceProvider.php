<?php

namespace App\Providers;

use App\Messaging\Contracts\MessagingTransport;
use App\Messaging\Drivers\DisabledMessagingTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MessagingTransport::class, DisabledMessagingTransport::class);
    }

    public function boot(): void
    {
        //
    }
}
