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
use App\Models\SubscriptionPlan;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,30,90'],
        ]);
        $days = (int) ($filters['days'] ?? 30);
        $startDay = CarbonImmutable::now('UTC')->startOfDay()->subDays($days - 1);
        $since = $startDay;

        // Every date in the selected period appears even if there is no activity.
        // Count merchant signups and commerce events separately; these are NOT platform revenue.
        $seriesDefinitions = [
            'merchants' => Merchant::query(),
            'orders' => Order::query(),
            'inbound' => InboundEvent::query(),
            'outbound' => OutboundMessage::query(),
        ];
        $series = [];
        foreach ($seriesDefinitions as $name => $query) {
            $series[$name] = $query->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->pluck('total', 'day');
        }
        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDay->addDays($i)->format('Y-m-d');
            $trend[] = [
                'day' => $date,
                'merchants' => (int) ($series['merchants'][$date] ?? 0),
                'orders' => (int) ($series['orders'][$date] ?? 0),
                'inbound' => (int) ($series['inbound'][$date] ?? 0),
                'outbound' => (int) ($series['outbound'][$date] ?? 0),
            ];
        }
        $trendMax = max(1, ...array_map(fn (array $row): int => max($row['orders'], $row['inbound'], $row['outbound']), $trend));
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
            'selectedDays' => $days,
            'trend' => $trend,
            'trendMax' => $trendMax,
            'newMerchantsInPeriod' => $series['merchants']->sum(),
            'recentMerchants' => Merchant::query()->with('subscription.plan')
                ->withCount(['orders', 'messagingChannels'])
                ->latest('id')->limit(6)->get(),
        ]);
    }

    public function merchants(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended'],
            'plan' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', 'in:newest,oldest,name,orders'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
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
        if ($plan = ($filters['plan'] ?? null)) {
            if ($plan === 'none') {
                $query->whereDoesntHave('subscription');
            } else {
                // Only an existing plan code is accepted (including non-default custom plans).
                if (! SubscriptionPlan::where('code', $plan)->exists()) {
                    throw ValidationException::withMessages(['plan' => 'Unknown subscription plan.']);
                }
                $query->whereHas('subscription.plan', fn ($query) => $query->where('code', $plan));
            }
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'orders' => $query->orderByDesc('orders_count')->latest('id'),
            default => $query->latest('id'),
        };

        return view('platform.merchants', [
            'merchants' => $query->paginate(25)->withQueryString(),
            'plans' => SubscriptionPlan::orderBy('name')->get(['code', 'name']),
            'merchantTotal' => Merchant::count(),
            'merchantActive' => Merchant::where('status', 'active')->count(),
            'merchantSuspended' => Merchant::where('status', 'suspended')->count(),
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
            'productCount' => $merchant->products()->count(),
            'customerCount' => $merchant->customers()->count(),
            'conversationCount' => $merchant->conversations()->count(),
            'inbound30d' => InboundEvent::where('merchant_id', $merchant->id)
                ->where('created_at', '>=', now()->subDays(30))->count(),
            'lastOrder' => $merchant->orders()->max('created_at'),
            'lastInbound' => InboundEvent::where('merchant_id', $merchant->id)->max('created_at'),
        ]);
    }

    public function setMerchantStatus(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:250'],
            'current_password' => ['required', 'current_password'],
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
