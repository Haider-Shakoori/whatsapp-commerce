<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('commerce:status', function () {
    $this->info('Commerce foundation ready. Messaging is disabled until COMMERCE_MESSAGING_DRIVER=relaywa is configured.');
})->purpose('Display commerce platform status');

Schedule::command('commerce:relaywa:health')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('commerce:payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('commerce:automation:followups')->everyMinute()->withoutOverlapping();

Schedule::command('commerce:subscription:expire')->daily()->withoutOverlapping();

// Stale sending records are never retried: an unknown upstream outcome requires manual reconciliation.
Schedule::command('commerce:outbox:mark-uncertain')->everyFifteenMinutes()->withoutOverlapping();
