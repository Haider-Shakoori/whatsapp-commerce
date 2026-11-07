<?php

namespace App\Messaging\Contracts;

use App\Models\MessagingChannel;

interface MessagingTransport
{
    /** Return the provider message ID only on a positively acknowledged send. */
    public function sendText(
        MessagingChannel $channel,
        string $recipient,
        string $text,
        ?string $idempotencyKey = null
    ): string;
}
