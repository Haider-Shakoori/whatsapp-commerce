<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\MessagingTransport;
use App\Models\MessagingChannel;
use LogicException;

final class DisabledMessagingTransport implements MessagingTransport
{
    public function sendText(
        MessagingChannel $channel,
        string $recipient,
        string $text,
        ?string $idempotencyKey = null
    ): string {
        throw new LogicException('Outbound messaging is disabled until a provider adapter has been configured and tested.');
    }
}
