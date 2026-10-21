<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\SubscriptionPlan;
use App\Saas\MerchantEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Merchant $merchant, MerchantEntitlements $entitlements): View
    {
        return view('merchant.subscriptions.index', [
            'merchant' => $merchant,
            'summary' => $entitlements->summary($merchant),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('id')->get(),
            'invoices' => $merchant->subscriptionInvoices()->latest('id')->limit(25)->get(),
            'events' => $merchant->subscriptionEvents()->latest('id')->limit(20)->get(),
            'canManage' => request()->user()->merchants()->whereKey($merchant->id)
                ->wherePivotIn('role', ['owner', 'manager'])->exists(),
        ]);
    }

    public function requestPlan(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string', Rule::exists('subscription_plans', 'code')->where('is_active', true)],
        ]);

        $plan = SubscriptionPlan::where('code', $data['plan_code'])->firstOrFail();
        if ($plan->code === 'trial') {
            return back()->withErrors(['plan_code' => 'Trial periods cannot be restarted.']);
        }
        if ($merchant->subscriptionEvents()->where('event', 'plan_change_requested')
            ->where('created_at', '>=', now()->subDay())->exists()) {
            return back()->withErrors(['plan_code' => 'You already requested a plan change in the last 24 hours.']);
        }

        $merchant->subscriptionEvents()->create([
            'merchant_subscription_id' => $merchant->subscription?->id,
            'event' => 'plan_change_requested',
            'details' => ['plan' => $plan->code, 'user_id' => $request->user()->id],
        ]);

        return back()->with('status', 'Your plan request was recorded. No plan change or payment was made.');
    }
}
