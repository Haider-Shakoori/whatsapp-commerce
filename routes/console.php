<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('commerce:status', function () {
    $this->info('Commerce foundation ready; outbound WhatsApp messaging is disabled.');
})->purpose('Display commerce foundation status');
