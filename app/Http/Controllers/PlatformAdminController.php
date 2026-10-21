<?php

namespace App\Http\Controllers;

use App\Models\InboundEvent;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\MessagingChannel;
use App\Models\Order;
use App\Models\OutboundMessage;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function index(): View
    {
        $since = now()->subDays(30);
        $daily = OutboundMessage::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')->get();

        return view('platform.index', [
            'merchants' => Merchant::count(),
            'activeMerchants' => Merchant::where('status', 'active')->count(),
            'trialing' => MerchantSubscription::where('status', 'trialing')
                ->where('period_end', '>', now())->count(),
            'activeSubscriptions' => MerchantSubscription::where('status', 'active')
                ->where('period_end', '>', now())->count(),
            'expiringSoon' => MerchantSubscription::whereIn('status', ['trialing', 'active'])
                ->whereBetween('period_end', [now(), now()->addDays(7)])->count(),
            'channels' => MessagingChannel::count(),
            'connectedChannels' => MessagingChannel::where('status', 'connected')->count(),
            'inbound30d' => InboundEvent::where('created_at', '>=', $since)->count(),
            'outbound30d' => OutboundMessage::where('created_at', '>=', $since)->count(),
            'outboundFailed30d' => OutboundMessage::where('created_at', '>=', $since)
                ->whereIn('status', ['failed', 'uncertain'])->count(),
            'unresolvedOutbound' => OutboundMessage::whereIn('status', ['queued', 'sending', 'uncertain'])->count(),
            'orders30d' => Order::where('created_at', '>=', $since)->count(),
            'pendingRequests' => SubscriptionEvent::where('event', 'plan_change_requested')
                ->where('created_at', '>=', $since)->count(),
            'pendingJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'lastInbound' => InboundEvent::max('created_at'),
            'recentActivity' => PlatformAuditLog::latest('id')->limit(10)->get(),
            'daily' => $daily,
            'outboundPaused' => PlatformSetting::enabled('outbound_paused'),
            'registrationPaused' => PlatformSetting::enabled('registration_paused'),
            'subscriptionInvoices' => SubscriptionInvoice::count(),
        ]);
    }

    public function merchants(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);
        $query = Merchant::query()->with('subscription.plan')
            ->withCount(['users', 'messagingChannels', 'products', 'orders']);
        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $term = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($query) => $query->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term));
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('platform.merchants', [
            'merchants' => $query->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Merchant $merchant): View
    {
        return view('platform.merchant', [
            'merchant' => $merchant->load('subscription.plan'),
            'team' => $merchant->users()->orderBy('name')->get(),
            'events' => $merchant->subscriptionEvents()->latest('id')->limit(15)->get(),
            'invoices' => $merchant->subscriptionInvoices()->latest('id')->limit(10)->get(),
            'audit' => PlatformAuditLog::where('merchant_id', $merchant->id)->latest('id')->limit(15)->get(),
            'channelCounts' => $merchant->messagingChannels()->select('status')
                ->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'outboundStates' => OutboundMessage::where('merchant_id', $merchant->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->select('status')->selectRaw('COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
            'monthlyMessages' => $merchant->subscriptionUsages()->where('usage_month', now('UTC')->format('Y-m'))
                ->value('outbound_count') ?? 0,
            'orderCount' => $merchant->orders()->count(),
        ]);
    }

    public function setMerchantStatus(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:250'],
        ]);
        $expected = $data['status'] === 'suspended' ? 'SUSPEND' : 'REACTIVATE';
        if ($data['confirmation'] !== $expected) {
            throw ValidationException::withMessages(['confirmation' => 'Type '.$expected.' to confirm.']);
        }

        DB::transaction(function () use ($merchant, $data, $request): void {
            $locked = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status;
            if ($before === $data['status']) {
                return;
            }
            $locked->update(['status' => $data['status']]);
            PlatformAuditLog::create([
                'actor_user_id' => $request->user()->id, 'merchant_id' => $locked->id,
                'action' => $data['status'] === 'suspended' ? 'merchant_suspended' : 'merchant_reactivated',
                'target_type' => 'merchant', 'target_id' => $locked->id,
                'reason' => $data['reason'], 'changes' => ['from' => $before, 'to' => $data['status']],
            ]);
        });

        return back()->with('status', 'Merchant status updated. Existing records were preserved.');
    }

    public function audits(Request $request): View
    {
        $data = $request->validate(['action' => ['nullable', 'string', 'max:80']]);

        return view('platform.audit', [
            'logs' => PlatformAuditLog::query()
                ->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
                ->latest('id')->paginate(40)->withQueryString(),
        ]);
    }
}
