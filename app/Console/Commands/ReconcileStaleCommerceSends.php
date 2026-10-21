<?php

namespace App\Console\Commands;

use App\Models\OutboundMessage;
use App\Models\PlatformAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileStaleCommerceSends extends Command
{
    protected $signature = 'commerce:outbox:mark-uncertain {--minutes=15 : Minimum age of a sending record in minutes}';

    protected $description = 'Flag stuck sending records as uncertain WITHOUT re-sending WhatsApp messages';

    public function handle(): int
    {
        $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 5 || $minutes > 1440) {
            $this->error('Age threshold must be between 5 and 1440 minutes.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);
        $count = 0;
        OutboundMessage::where('status', 'sending')
            ->where('updated_at', '<=', $cutoff)->orderBy('id')
            ->chunkById(100, function ($messages) use ($cutoff, &$count): void {
                foreach ($messages as $candidate) {
                    DB::transaction(function () use ($candidate, $cutoff, &$count): void {
                        $message = OutboundMessage::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $message || $message->status !== 'sending'
                            || $message->updated_at->gt($cutoff)) {
                            return;
                        }

                        // The upstream service may have accepted the send just
                        // before the worker died. Never mark failed and retry.
                        $message->update(['status' => 'uncertain']);
                        $count++;
                    });
                }
            });

        if ($count) {
            PlatformAuditLog::create([
                'action' => 'stale_outbox_marked_uncertain',
                'target_type' => 'outbound_message',
                'reason' => 'Worker send outcome unknown; operator must reconcile before any retry',
                'changes' => ['count' => $count, 'min_age_minutes' => $minutes],
            ]);
        }

        $this->info($count.' stale send(s) marked uncertain. No messages were re-sent.');

        return self::SUCCESS;
    }
}
