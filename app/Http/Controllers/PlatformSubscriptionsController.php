<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformSubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:trialing,active,expired,cancelled'],
            'plan' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'expiry' => ['nullable', 'in:7,30,expired'],
        ]);
        $query = MerchantSubscription::query()->with(['merchant', 'plan']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['plan'])) {
            $query->whereHas('plan', fn ($q) => $q->where('code', $filters['plan']));
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('merchant', fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term));
        }
        if (isset($filters['expiry'])) {
            if ($filters['expiry'] === 'expired') {
                $query->where('period_end', '<=', now());
            } else {
                $query->whereBetween('period_end', [now(), now()->addDays((int) $filters['expiry'])]);
            }
        }

        return view('platform.subscriptions', [
            'subscriptions' => $query->orderBy('period_end')->paginate(25)->withQueryString(),
            'requests' => SubscriptionEvent::where('event', 'plan_change_requested')
                ->with('merchant')->latest('id')->limit(20)->get(),
            'plans' => SubscriptionPlan::orderBy('name')->get(),
            'activeCount' => MerchantSubscription::where('status', 'active')->where('period_end', '>', now())->count(),
            'trialCount' => MerchantSubscription::where('status', 'trialing')->where('period_end', '>', now())->count(),
            'expiringCount' => MerchantSubscription::whereIn('status', ['active', 'trialing'])
                ->whereBetween('period_end', [now(), now()->addDays(7)])->count(),
            'expiredCount' => MerchantSubscription::where('period_end', '<=', now())->count(),
        ]);
    }

    public function grant(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'in:starter,growth'],
            'months' => ['required', 'integer', 'between:1,24'],
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:250'],
            'current_password' => ['required', 'current_password'],
        ]);
        if ($data['confirmation'] !== 'GRANT') {
            throw ValidationException::withMessages(['confirmation' => 'Type GRANT to confirm entitlement only.']);
        }

        $plan = SubscriptionPlan::where('code', $data['plan_code'])->where('is_active', true)->firstOrFail();
        DB::transaction(function () use ($merchant, $plan, $data, $request): void {
            $subscription = MerchantSubscription::where('merchant_id', $merchant->id)
                ->lockForUpdate()->firstOrFail();
            $previousPlan = $subscription->plan?->code;
            $extend = $subscription->subscription_plan_id === $plan->id && $subscription->isEntitled();
            $start = $extend ? $subscription->period_start->copy() : now();
            $end = $extend ? $subscription->period_end->copy() : now();
            $end->addMonthsNoOverflow((int) $data['months']);
            $subscription->update([
                'subscription_plan_id' => $plan->id, 'status' => 'active',
                'period_start' => $start, 'period_end' => $end,
                'auto_renew' => false, 'provider' => null,
                'provider_subscription_id' => null, 'cancelled_at' => null,
            ]);
            $merchant->subscriptionEvents()->create([
                'merchant_subscription_id' => $subscription->id,
                'event' => 'admin_entitlement_grant',
                'details' => [
                    'previous_plan' => $previousPlan, 'plan' => $plan->code,
                    'months' => (int) $data['months'],
                    'actor_id' => $request->user()->id, 'billing_collected' => false,
                ],
            ]);
            PlatformAuditLog::create([
                'actor_user_id' => $request->user()->id,
                'merchant_id' => $merchant->id, 'action' => 'subscription_granted',
                'target_type' => 'subscription', 'target_id' => $subscription->id,
                'reason' => $data['reason'],
                'changes' => [
                    'previous_plan' => $previousPlan, 'plan' => $plan->code,
                    'months' => (int) $data['months'], 'billing_collected' => false,
                ],
            ]);
        });

        return back()->with('status', 'Subscription entitlement changed. No payment was collected or invoice marked paid.');
    }
}
