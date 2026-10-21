<?php

namespace App\Messaging;

use App\Jobs\SendCommerceMessage;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use InvalidArgumentException;
use RuntimeException;

final class CommerceOutbox
{
    public function __construct(private readonly ConversationInbox $inbox) {}

    public function queueText(
        Merchant $merchant,
        MessagingChannel $channel,
        string $recipient,
        string $text,
        string $idempotencyKey
    ): OutboundMessage {
        if ($channel->merchant_id !== $merchant->id || $channel->provider !== 'relaywa') {
            throw new RuntimeException('Messaging channel does not belong to the merchant.');
        }

        $recipient = ltrim(trim($recipient), '+');
        if (! preg_match('/^[1-9][0-9]{6,14}$/D', $recipient)
            || trim($text) === '' || mb_strlen($text) > 4096
            || $idempotencyKey === '' || strlen($idempotencyKey) > 120) {
            throw new InvalidArgumentException('Invalid recipient, message body or idempotency key.');
        }

        $message = OutboundMessage::firstOrCreate(
            ['merchant_id' => $merchant->id, 'idempotency_key' => $idempotencyKey],
            [
                'messaging_channel_id' => $channel->id,
                'recipient' => $recipient,
                'body' => $text,
                'status' => 'queued',
            ],
        );

        if ($message->messaging_channel_id !== $channel->id
            || $message->recipient !== $recipient || $message->body !== $text) {
            throw new RuntimeException('This idempotency key is already used for a different message.');
        }

        if ($message->wasRecentlyCreated) {
            $this->inbox->recordOutbound($message);
            SendCommerceMessage::dispatch($message->id)->afterCommit();
        }

        return $message;
    }
}
