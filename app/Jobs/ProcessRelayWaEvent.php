<?php

namespace App\Jobs;

use App\Models\InboundEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRelayWaEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $eventId) {}

    public function handle(): void
    {
        $event = InboundEvent::with('channel')->find($this->eventId);
        if (! $event || $event->processed_at || ! $event->channel) {
            return;
        }

        // Batch 03 safely saves inbound events. Conversational cart/order handlers
        // are deliberately deferred to subsequent batches.
        $payload = $event->payload;
        $status = match ($payload['type'] ?? '') {
            'session.connected' => 'connected',
            'session.disconnected' => 'disconnected',
            'session.logged_out' => 'logged_out',
            'session.error' => 'error',
            default => null,
        };

        if ($status) {
            $event->channel->update([
                'status' => $status,
                'status_checked_at' => now(),
            ]);
        }

        $event->update(['processed_at' => now()]);
    }
}
