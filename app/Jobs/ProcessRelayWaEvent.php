<?php

namespace App\Jobs;

use App\Messaging\ChatCatalogMenu;
use App\Messaging\RelayWaClient;
use App\Models\InboundEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessRelayWaEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30];

    public function __construct(public readonly int $eventId) {}

    public function handle(RelayWaClient $client, ChatCatalogMenu $menu): void
    {
        $event = InboundEvent::with('channel.merchant')->find($this->eventId);
        if (! $event || $event->processed_at || ! $event->channel) {
            return;
        }

        $channel = $event->channel;
        $payload = $event->payload;
        $type = $payload['type'] ?? '';

        // Do not use webhook data as the source of the chat body. RelayWA
        // delivers the message ID, while text is stored in its messages API.
        $message = null;
        if ($type === 'message.received' && $channel->menu_enabled
            && config('commerce.messaging_driver') === 'relaywa') {
            $messageId = $payload['data']['messageId'] ?? null;
            if (is_string($messageId) && $messageId !== '') {
                $message = $client->inboundMessage($channel, $messageId);
            }
        }

        DB::transaction(function () use ($menu, $payload, $type, $message): void {
            // Lock the inbox row: no duplicate bot state mutations on job retries.
            $event = InboundEvent::with('channel.merchant')->lockForUpdate()->find($this->eventId);
            if (! $event || $event->processed_at || ! $event->channel) {
                return;
            }

            $status = match ($type) {
                'session.connected' => 'connected',
                'session.disconnected' => 'disconnected',
                'session.logged_out' => 'logged_out',
                'session.error' => 'error',
                default => null,
            };
            if ($status) {
                $event->channel->update(['status' => $status, 'status_checked_at' => now()]);
            }

            if ($type === 'message.received' && $message !== null) {
                $menu->handle($event, $event->channel, $message);
            }

            $event->update(['processed_at' => now()]);
        });
    }
}
