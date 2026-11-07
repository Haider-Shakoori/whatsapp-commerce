<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('commerce:status', function () {
    $this->info('Commerce foundation ready. Messaging is disabled until COMMERCE_MESSAGING_DRIVER=relaywa is configured.');
})->purpose('Display commerce platform status');

Schedule::command('commerce:relaywa:health')->everyFiveMinutes()->withoutOverlapping();
