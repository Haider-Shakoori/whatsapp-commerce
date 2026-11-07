<?php

namespace App\Messaging\Contracts;

use App\Models\MessagingChannel;

interface MessagingTransport
{
    /** Return the provider's outbound message ID when accepted. */
    public function sendText(MessagingChannel $channel, string $recipient, string $text): string;
}
