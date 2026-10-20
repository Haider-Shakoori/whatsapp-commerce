<?php

namespace App\Jobs;

use App\Messaging\Contracts\MessagingTransport;
use App\Messaging\Exceptions\DeliveryUncertain;
use App\Models\OutboundMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendCommerceMessage implements ShouldQueue
{
    use Queueable;

    // No blind replay after network timeouts, crashes or uncertain worker state.
    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly int $outboundMessageId) {}

    public function handle(MessagingTransport $transport): void
    {
        $claimed = DB::transaction(function (): ?OutboundMessage {
            $message = OutboundMessage::query()->lockForUpdate()->find($this->outboundMessageId);
            if (! $message || $message->status !== 'queued') {
                return null;
            }
            $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1]);

            return $message;
        });

        if (! $claimed) {
            return;
        }

        $channel = $claimed->channel;
        if (! $channel || $channel->merchant_id !== $claimed->merchant_id) {
            $claimed->update(['status' => 'failed']);

            return;
        }

        if (config('commerce.messaging_driver') !== 'relaywa' || $channel->status !== 'connected') {
            // Treat unavailable transport as a local failure, not a possibly delivered send.
            $claimed->update(['status' => 'failed']);

            return;
        }

        try {
            $providerId = $transport->sendText(
                $channel,
                $claimed->recipient,
                $claimed->body,
                $claimed->idempotency_key
            );

            $claimed->update([
                'provider_message_id' => $providerId,
                'status' => 'accepted',
                'sent_at' => now(),
            ]);
        } catch (DeliveryUncertain $e) {
            $claimed->update(['status' => 'uncertain']);
        } catch (Throwable $e) {
            $claimed->update(['status' => 'failed']);
            // Deliberately do not expose secret-bearing transport exception strings to logs.
        }
    }
}
