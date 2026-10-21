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
        $data = $request->validate(['status' => ['nullable', 'in:trialing,active,expired,cancelled']]);

        return view('platform.subscriptions', [
            'subscriptions' => MerchantSubscription::query()->with(['merchant', 'plan'])
                ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->orderBy('period_end')->paginate(25)->withQueryString(),
            'requests' => SubscriptionEvent::where('event', 'plan_change_requested')
                ->with('merchant')->latest('id')->limit(20)->get(),
            'plans' => SubscriptionPlan::where('is_active', true)->where('code', '!=', 'trial')
                ->orderBy('id')->get(),
        ]);
    }

    public function grant(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'in:starter,growth'],
            'months' => ['required', 'integer', 'between:1,24'],
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:250'],
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
