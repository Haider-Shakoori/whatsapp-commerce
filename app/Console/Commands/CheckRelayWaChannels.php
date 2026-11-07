<?php

namespace App\Console\Commands;

use App\Messaging\RelayWaClient;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use Illuminate\Console\Command;
use Throwable;

class CheckRelayWaChannels extends Command
{
    protected $signature = 'commerce:relaywa:health';

    protected $description = 'Check connected RelayWA sessions and flag uncertain outbound sends';

    public function handle(RelayWaClient $client): int
    {
        OutboundMessage::where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(3))
            ->update(['status' => 'uncertain']);

        MessagingChannel::where('provider', 'relaywa')->orderBy('id')->chunkById(100, function ($channels) use ($client): void {
            foreach ($channels as $channel) {
                try {
                    $session = $client->session($channel);
                    $status = $session['status'] ?? 'error';
                    $allowed = ['pending', 'need_scan', 'connecting', 'connected', 'disconnected', 'reconnecting', 'logged_out', 'expired', 'error'];

                    $channel->update([
                        'status' => in_array($status, $allowed, true) ? $status : 'error',
                        'status_checked_at' => now(),
                    ]);
                } catch (Throwable $e) {
                    $channel->update(['status' => 'error', 'status_checked_at' => now()]);
                    // Avoid logging API tokens, request details or sensitive session data.
                    $this->warn('RelayWA connection check failed for internal channel #'.$channel->id);
                }
            }
        });

        return self::SUCCESS;
    }
}
