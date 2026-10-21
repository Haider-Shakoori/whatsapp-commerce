<?php

namespace App\Messaging;

use App\Jobs\SendCommerceMessage;
use App\Saas\MerchantEntitlements;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class CommerceOutbox
{
    public function __construct(private readonly ConversationInbox $inbox, private readonly MerchantEntitlements $entitlements) {}

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

        return DB::transaction(function () use ($merchant, $channel, $recipient, $text, $idempotencyKey): OutboundMessage {
            $existing = OutboundMessage::where('merchant_id', $merchant->id)
                ->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->messaging_channel_id !== $channel->id
                    || $existing->recipient !== $recipient || $existing->body !== $text) {
                    throw new RuntimeException('Idempotency key already belongs to another message.');
                }

                return $existing;
            }

            // Quota consumption, message creation and inbox record are atomic.
            $this->entitlements->consumeOutbound($merchant);
            $message = OutboundMessage::create([
                'merchant_id' => $merchant->id, 'messaging_channel_id' => $channel->id,
                'idempotency_key' => $idempotencyKey,
                'recipient' => $recipient, 'body' => $text, 'status' => 'queued',
            ]);
            $this->inbox->recordOutbound($message);
            SendCommerceMessage::dispatch($message->id)->afterCommit();

            return $message;
        });
    }
}
