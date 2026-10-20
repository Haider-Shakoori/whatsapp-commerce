<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRelayWaEvent;
use App\Messaging\RelayWaWebhookVerifier;
use App\Models\InboundEvent;
use App\Models\MessagingChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelayWaWebhookController extends Controller
{
    public function __invoke(Request $request, string $publicId, RelayWaWebhookVerifier $verifier): JsonResponse
    {
        $channel = MessagingChannel::query()
            ->where('provider', 'relaywa')->where('public_id', $publicId)->first();

        if (! $channel || ! $verifier->valid($request, (string) ($channel->credentials['webhook_secret'] ?? ''))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (strlen($request->getContent()) > 262144) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        $event = json_decode($request->getContent(), true);
        if (! is_array($event)
            || ! isset($event['id'], $event['type'], $event['sessionId'])
            || ! (is_string($event['id']) || is_int($event['id']))
            || strlen((string) $event['id']) > 120 || (string) $event['id'] === ''
            || ! is_string($event['type']) || strlen($event['type']) > 120 || $event['type'] === ''
            || $event['sessionId'] !== $channel->external_account_id
            || ! isset($event['data']) || ! is_array($event['data'])) {
            return response()->json(['error' => 'Invalid event'], 422);
        }

        // Some RelayWA retries have different webhook envelope IDs but reference
        // the same stored inbound WhatsApp message. Use the message ID when valid.
        $messageId = $event['type'] === 'message.received' ? ($event['data']['messageId'] ?? null) : null;
        $identity = is_string($messageId) && preg_match('/^[a-f0-9-]{36}$/iD', $messageId)
            ? 'wa-message:'.strtolower($messageId) : (string) $event['id'];

        $inbox = InboundEvent::firstOrCreate(
            [
                'messaging_channel_id' => $channel->id,
                'provider_event_id' => $identity,
            ],
            [
                'merchant_id' => $channel->merchant_id,
                'payload' => $event,
            ],
        );

        if ($inbox->wasRecentlyCreated) {
            ProcessRelayWaEvent::dispatch($inbox->id)->afterCommit();
        }

        return response()->json(['received' => true], 200);
    }
}
