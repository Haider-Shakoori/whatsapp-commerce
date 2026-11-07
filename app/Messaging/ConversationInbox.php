<?php

namespace App\Messaging;

use App\Models\Conversation;
use App\Models\InboundEvent;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;

final class ConversationInbox
{
    /**
     * Call inside the inbox-event transaction. Only validated direct inbound
     * messages reach this function; duplicate message IDs cannot add unread twice.
     */
    public function recordInbound(InboundEvent $event, MessagingChannel $channel, array $message): void
    {
        $phone = $message['sender_phone'] ?? null;
        $jid = $message['chat_jid'] ?? null;
        $id = $message['id'] ?? null;

        if ($channel->merchant_id !== $event->merchant_id
            || ($message['direction'] ?? null) !== 'inbound'
            || ! is_string($phone) || ! preg_match('/^[1-9][0-9]{6,14}$/D', $phone)
            || ! is_string($jid) || $jid !== $phone.'@s.whatsapp.net'
            || ! is_string($id) || ! preg_match('/^[a-f0-9-]{36}$/iD', $id)) {
            return;
        }

        $customer = $channel->merchant->customers()->firstOrCreate(['phone' => $phone]);
        $conversation = Conversation::firstOrCreate([
            'messaging_channel_id' => $channel->id,
            'customer_id' => $customer->id,
        ], [
            'merchant_id' => $channel->merchant_id, 'status' => 'open',
        ]);
        $conversation = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

        $body = $message['text_body'] ?? null;
        $type = is_string($message['message_type'] ?? null)
            ? substr($message['message_type'], 0, 30) : 'unknown';
        $record = $conversation->messages()->firstOrCreate([
            'provider_message_id' => $id,
        ], [
            'merchant_id' => $channel->merchant_id,
            'direction' => 'inbound', 'message_type' => $type,
            'body' => is_string($body) ? mb_substr($body, 0, 4096) : null,
            'message_at' => now(),
        ]);

        if ($record->wasRecentlyCreated) {
            $conversation->update([
                'unread_count' => min(4294967295, $conversation->unread_count + 1),
                'last_message_at' => now(),
                'status' => 'open',
            ]);
        }
    }

    /** The matching outbound queue row has already been created. */
    public function recordOutbound(OutboundMessage $outbound): void
    {
        $customer = $outbound->channel?->merchant?->customers()
            ->where('phone', $outbound->recipient)->first();
        if (! $customer) {
            return;
        }
        $conversation = Conversation::query()
            ->where('merchant_id', $outbound->merchant_id)
            ->where('messaging_channel_id', $outbound->messaging_channel_id)
            ->where('customer_id', $customer->id)->first();
        if (! $conversation) {
            return;
        }
        $record = $conversation->messages()->firstOrCreate([
            'outbound_message_id' => $outbound->id,
        ], [
            'merchant_id' => $outbound->merchant_id,
            'direction' => 'outbound', 'message_type' => 'text',
            'body' => $outbound->body, 'message_at' => now(),
        ]);
        if ($record->wasRecentlyCreated) {
            $conversation->update(['last_message_at' => now()]);
        }
    }
}
