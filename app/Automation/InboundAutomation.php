<?php

namespace App\Automation;

use App\Messaging\CommerceOutbox;
use App\Models\AutomationReply;
use App\Models\AutomationSetting;
use App\Models\ChatMenuSession;
use App\Models\Conversation;
use App\Models\InboundEvent;
use App\Models\MessagingChannel;

final class InboundAutomation
{
    public function __construct(
        private readonly BusinessHours $hours,
        private readonly CommerceOutbox $outbox,
    ) {}

    /** Called from the signed inbound webhook worker AFTER the inbox stores the message. */
    public function handle(InboundEvent $event, MessagingChannel $channel, array $message): void
    {
        if ($event->merchant_id !== $channel->merchant_id
            || config('commerce.messaging_driver') !== 'relaywa'
            || ($message['direction'] ?? null) !== 'inbound'
            || ($message['message_type'] ?? null) !== 'text') {
            return;
        }

        $phone = $message['sender_phone'] ?? null;
        $text = $message['text_body'] ?? null;
        $messageId = $message['id'] ?? null;
        if (! is_string($phone) || ! preg_match('/^[1-9][0-9]{6,14}$/D', $phone)
            || ($message['chat_jid'] ?? null) !== $phone.'@s.whatsapp.net'
            || ! is_string($text) || trim($text) === '' || mb_strlen($text) > 1000
            || ! is_string($messageId) || ! preg_match('/^[a-f0-9-]{36}$/iD', $messageId)) {
            return;
        }

        $customer = $channel->merchant->customers()->where('phone', $phone)->first();
        if (! $customer) {
            return;
        }
        $conversation = Conversation::query()->where('merchant_id', $channel->merchant_id)
            ->where('messaging_channel_id', $channel->id)->where('customer_id', $customer->id)
            ->lockForUpdate()->first();
        if (! $conversation) {
            return;
        }

        $command = mb_strtoupper(trim($text));
        if ($command === 'STOP') {
            $customer->update(['automation_opted_out' => true]);

            return;
        }
        if ($command === 'START') {
            $customer->update(['automation_opted_out' => false]);

            return;
        }
        if ($customer->automation_opted_out || $conversation->status === 'closed') {
            return;
        }

        $settings = AutomationSetting::where('merchant_id', $channel->merchant_id)->first();
        if (! $settings?->enabled) {
            return;
        }

        // Suppress automatic replies to shopping/cart/check-out commands and
        // active catalog sessions. Automation must not conflict with cart orders.
        if (preg_match('/^(MENU|HI|HELLO|CART|BACK|NEXT|PREV|ADD(?:\s+\d+)?|CHECKOUT|CONFIRM|CANCEL|CLEAR|QTY\b.*|REMOVE\b.*|\d+)$/iD', $command)) {
            return;
        }
        if ($channel->menu_enabled && ChatMenuSession::where('messaging_channel_id', $channel->id)
            ->where('customer_phone', $phone)->where('opted_out', false)->exists()) {
            return;
        }

        $matched = null;
        if ($settings->keyword_enabled) {
            $rules = $channel->merchant->automationRules()->where('enabled', true)
                ->orderBy('priority')->orderBy('id')->get();
            $lower = mb_strtolower($text);
            foreach ($rules as $rule) {
                if (str_contains($lower, mb_strtolower($rule->keyword))) {
                    $matched = $rule;
                    break;
                }
            }
        }

        // Internal routing is allowed only to current merchant members and only
        // on unassigned conversations. A rule never steals another agent's thread.
        if ($matched?->assign_to && $conversation->assigned_to === null
            && $channel->merchant->users()->whereKey($matched->assign_to)->exists()) {
            $conversation->update(['assigned_to' => $matched->assign_to]);
        }

        if ($channel->status !== 'connected'
            || ($conversation->last_automation_at && $conversation->last_automation_at->gt(now()->subMinutes(30)))) {
            return;
        }

        $reply = null;
        $kind = null;
        if ($settings->after_hours_enabled && ! $this->hours->isOpen($channel->merchant, $settings)) {
            $reply = $settings->after_hours_message;
            $kind = 'after_hours';
        } elseif ($matched && $settings->keyword_enabled) {
            $reply = $matched->reply_text;
            $kind = 'keyword';
        }
        if (! is_string($reply) || trim($reply) === '') {
            return;
        }

        $record = AutomationReply::firstOrCreate(
            ['conversation_id' => $conversation->id, 'source_message_id' => $messageId],
            ['merchant_id' => $channel->merchant_id, 'rule_type' => $kind],
        );
        if (! $record->wasRecentlyCreated) {
            return;
        }

        $outbound = $this->outbox->queueText(
            $channel->merchant, $channel, $phone, $reply,
            'automation-'.$record->id
        );
        $record->update(['outbound_message_id' => $outbound->id]);
        $conversation->update(['last_automation_at' => now()]);
    }
}
