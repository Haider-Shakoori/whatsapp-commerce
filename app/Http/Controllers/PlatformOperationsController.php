<?php

namespace App\Http\Controllers;

use App\Models\InboundEvent;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformOperationsController extends Controller
{
    /**
     * Recorded channel states only: this page intentionally never calls the provider,
     * decrypts credentials or implies the account is connected right now.
     */
    public function channels(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:20', 'regex:/^[a-z_]+$/'],
        ]);

        $query = MessagingChannel::query()->with('merchant:id,name,slug');
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('merchant', fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term));
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('platform.channels', [
            'channels' => $query->latest('id')->paginate(25)->withQueryString(),
            'statusCounts' => MessagingChannel::query()->select('status')
                ->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'staleChecks' => MessagingChannel::where(function ($q) {
                $q->whereNull('status_checked_at')
                    ->orWhere('status_checked_at', '<', now()->subMinutes(30));
            })->count(),
        ]);
    }

    /**
     * Safe diagnostics deliberately omit recipient, body, provider IDs, payload and
     * credentials. A failed send must not be retried automatically from this UI.
     */
    public function messages(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['queued', 'sending', 'sent', 'failed', 'uncertain'])],
            'days' => ['nullable', Rule::in(['7', '30', '90'])],
        ]);
        $days = (int) ($filters['days'] ?? 30);
        $since = now('UTC')->subDays($days);

        $query = OutboundMessage::query()
            ->select(['id', 'merchant_id', 'messaging_channel_id', 'status', 'attempts', 'created_at', 'updated_at', 'sent_at'])
            ->with(['channel:id,merchant_id,display_name', 'merchant:id,name,slug'])
            ->where('created_at', '>=', $since);
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->whereIn('merchant_id', Merchant::query()
                ->select('id')->where('name', 'like', $term)->orWhere('slug', 'like', $term));
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Explicitly restrict the selected fields to operational metadata only.
        // Do not eager-load customer/conversation data on the platform console.
        $statusCounts = OutboundMessage::query()->where('created_at', '>=', $since)
            ->select('status')->selectRaw('COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return view('platform.messages', [
            'messages' => $query->latest('id')->paginate(30)->withQueryString(),
            'statusCounts' => $statusCounts,
            'selectedDays' => $days,
            'unprocessedInbound' => InboundEvent::whereNull('processed_at')->count(),
        ]);
    }

    /**
     * DB-backed health signals, not a claim about provider/API uptime.
     * Never return failed-job exceptions or serialized queue payloads.
     */
    public function health(): View
    {
        $now = now();
        $queueCutoff = $now->copy()->subMinutes(15)->timestamp;

        return view('platform.health', [
            'pendingJobs' => DB::table('jobs')->count(),
            'staleQueuedJobs' => DB::table('jobs')->where('created_at', '<', $queueCutoff)->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'unprocessedInbound' => InboundEvent::whereNull('processed_at')->count(),
            'oldInbound' => InboundEvent::whereNull('processed_at')
                ->where('created_at', '<', $now->copy()->subMinutes(15))->count(),
            'uncertainOutbound' => OutboundMessage::where('status', 'uncertain')->count(),
            'failedOutbound' => OutboundMessage::where('status', 'failed')->count(),
            'stuckSending' => OutboundMessage::where('status', 'sending')
                ->where('updated_at', '<', $now->copy()->subMinutes(15))->count(),
            'channelsNotChecked' => MessagingChannel::whereNull('status_checked_at')
                ->orWhere('status_checked_at', '<', $now->copy()->subMinutes(30))->count(),
            'lastInbound' => InboundEvent::max('created_at'),
            'lastOutbound' => OutboundMessage::max('created_at'),
            'lastChannelCheck' => MessagingChannel::max('status_checked_at'),
            'registrationPaused' => PlatformSetting::enabled('registration_paused'),
            'outboundPaused' => PlatformSetting::enabled('outbound_paused'),
            'expiringSoon' => MerchantSubscription::whereIn('status', ['trialing', 'active'])
                ->whereBetween('period_end', [$now, $now->copy()->addDays(7)])->count(),
        ]);
    }
}
