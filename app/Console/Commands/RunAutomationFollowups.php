<?php

namespace App\Console\Commands;

use App\Automation\BusinessHours;
use App\Messaging\CommerceOutbox;
use App\Models\AutomationFollowup;
use App\Models\AutomationSetting;
use App\Models\ConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunAutomationFollowups extends Command
{
    protected $signature = 'commerce:automation:followups';

    protected $description = 'Send only consent-confirmed, merchant-scheduled one-time follow-ups';

    public function handle(CommerceOutbox $outbox, BusinessHours $hours): int
    {
        AutomationFollowup::where('status', 'scheduled')
            ->where('due_at', '<=', now())->orderBy('id')->chunkById(100, function ($followups) use ($outbox, $hours): void {
                foreach ($followups as $followup) {
                    try {
                        DB::transaction(function () use ($followup, $outbox, $hours): void {
                            $job = AutomationFollowup::whereKey($followup->id)->lockForUpdate()->first();
                            if (! $job || $job->status !== 'scheduled') {
                                return;
                            }

                            $conversation = $job->conversation()->with(['customer', 'channel.merchant'])->first();
                            $channel = $conversation?->channel;
                            $merchant = $channel?->merchant;
                            $setting = $merchant ? AutomationSetting::where('merchant_id', $merchant->id)->first() : null;
                            $latestInbound = ConversationMessage::where('conversation_id', $job->conversation_id)
                                ->where('direction', 'inbound')->max('id');

                            if (! $merchant || $merchant->id !== $job->merchant_id
                                || ! $setting?->enabled || ! $setting->followups_enabled
                                || config('commerce.messaging_driver') !== 'relaywa'
                                || $channel->status !== 'connected'
                                || $conversation->status !== 'open'
                                || $conversation->customer->automation_opted_out
                                || (int) $latestInbound !== (int) $job->latest_inbound_id
                                || ! $hours->isOpen($merchant, $setting)) {
                                $job->update(['status' => 'skipped']);

                                return;
                            }

                            $outbound = $outbox->queueText(
                                $merchant, $channel, $conversation->customer->phone, $job->body,
                                'followup-'.$job->id
                            );
                            $job->update(['status' => 'queued', 'outbound_message_id' => $outbound->id]);
                        });
                    } catch (Throwable $e) {
                        // Keep it scheduled for a bounded operational retry;
                        // no message can be blindly repeated because the outbox
                        // idempotency key is stable per follow-up ID.
                        $this->warn('Follow-up #'.$followup->id.' failed; inspect queue health.');
                    }
                }
            });

        return self::SUCCESS;
    }
}
