<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\MessagingTransport;
use App\Messaging\RelayWaClient;
use App\Models\MessagingChannel;
use RuntimeException;

final class RelayWaMessagingTransport implements MessagingTransport
{
    public function __construct(private readonly RelayWaClient $client) {}

    public function sendText(
        MessagingChannel $channel,
        string $recipient,
        string $text,
        ?string $idempotencyKey = null
    ): string {
        if (! $idempotencyKey) {
            throw new RuntimeException('Outbound RelayWA messages require an idempotency key.');
        }

        return $this->client->sendText($channel, $recipient, $text, $idempotencyKey);
    }
}
