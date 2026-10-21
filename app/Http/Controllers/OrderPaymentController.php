<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Payments\PaymentSettlement;
use App\Payments\StripeTestGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderPaymentController extends Controller
{
    public function createMethod(Request $request, Merchant $merchant, Order $order): RedirectResponse
    {
        $data = $request->validate(['method' => ['required', 'in:cod,bank_transfer']]);
        $settings = $merchant->paymentSetting;
        if (! $settings || ! ($data['method'] === 'cod'
            ? $settings->cod_enabled : $settings->bank_transfer_enabled)) {
            throw ValidationException::withMessages(['method' => 'This method is not enabled for your business.']);
        }

        DB::transaction(function () use ($merchant, $order, $data): void {
            $order = $merchant->orders()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->canInitiate($order);
            if ($order->paymentAttempts()->where('method', 'stripe_test')->whereIn('status', ['creating', 'pending'])->exists()) {
                throw ValidationException::withMessages(['method' => 'An online payment is outstanding. Reconcile or expire it before switching methods.']);
            }
            if ($order->paymentAttempts()->where('method', $data['method'])->where('status', 'pending')->exists()) {
                return;
            }
            $order->paymentAttempts()->create([
                'merchant_id' => $merchant->id,
                'public_id' => (string) Str::uuid(),
                'method' => $data['method'],
                'status' => 'pending',
                'currency' => $order->currency,
                'amount_minor' => $order->total_minor,
            ]);
        });

        return back()->with('status', 'Payment method selected. Payment is not marked as received.');
    }

    public function confirmManual(Request $request, Merchant $merchant, Order $order, PaymentAttempt $attempt): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'min:4', 'max:120'],
            'confirmation' => ['required', 'in:RECEIVED'],
        ]);

        DB::transaction(function () use ($merchant, $order, $attempt, $data, $request): void {
            $order = $merchant->orders()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $attempt = $order->paymentAttempts()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->canInitiate($order);
            if (! in_array($attempt->method, ['cod', 'bank_transfer'], true)
                || $attempt->status !== 'pending'
                || $attempt->currency !== $order->currency
                || $attempt->amount_minor !== $order->total_minor) {
                throw ValidationException::withMessages(['confirmation' => 'Payment cannot be confirmed.']);
            }
            if ($order->paymentAttempts()->where('method', 'stripe_test')->whereIn('status', ['creating', 'pending'])->exists()) {
                throw ValidationException::withMessages(['confirmation' => 'An online payment is outstanding; reconcile it before recording cash/bank settlement.']);
            }
            $attempt->update([
                'status' => 'paid',
                'reference' => $data['reference'],
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);
            $order->update(['payment_status' => 'paid']);
        });

        return back()->with('status', 'Payment marked received by an authorized manager. Check your cash or bank records.');
    }

    public function stripeTestLink(Merchant $merchant, Order $order, StripeTestGateway $gateway): RedirectResponse
    {
        $settings = $merchant->paymentSetting;
        if (! $settings?->stripe_test_enabled || ! in_array($merchant->currency, ['USD', 'GBP', 'EUR'], true)) {
            throw ValidationException::withMessages(['payment' => 'Stripe test checkout is not enabled for this currency and merchant.']);
        }

        $attempt = DB::transaction(function () use ($merchant, $order): PaymentAttempt {
            $order = $merchant->orders()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->canInitiate($order);
            if ($order->total_minor < 1) {
                throw ValidationException::withMessages(['payment' => 'A zero-value order cannot create a payment link.']);
            }
            $existing = $order->paymentAttempts()->where('method', 'stripe_test')
                ->whereIn('status', ['creating', 'pending'])->latest('id')->first();
            if ($existing) {
                return $existing;
            }
            if ($order->paymentAttempts()->where('method', 'bank_transfer')->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['payment' => 'Resolve the bank transfer first.']);
            }

            return $order->paymentAttempts()->create([
                'merchant_id' => $merchant->id, 'public_id' => (string) Str::uuid(),
                'method' => 'stripe_test', 'status' => 'creating',
                'amount_minor' => $order->total_minor, 'currency' => $order->currency,
            ]);
        });

        if ($attempt->status !== 'creating' || $attempt->provider_session_id !== null) {
            return back()->with('status', 'An existing checkout attempt is available below.');
        }
        if ($attempt->created_at->lt(now()->subMinute())) {
            throw ValidationException::withMessages(['payment' => 'Checkout creation is uncertain. Review or reconcile before retrying.']);
        }
        try {
            $created = $gateway->createCheckout($attempt, $settings);
            $attempt->update([
                'provider_session_id' => $created['session_id'],
                'payment_url' => $created['url'],
                'expires_at' => $created['expires_at'],
                'status' => 'pending',
            ]);
        } catch (Throwable $e) {
            $attempt->update(['status' => 'review', 'review_note' => 'Test checkout creation uncertain or rejected.']);
            throw ValidationException::withMessages(['payment' => 'Could not create the test checkout. Review the attempt before trying again.']);
        }

        return back()->with('status', 'Stripe TEST checkout link created. No live payment was charged.');
    }

    public function reconcile(Merchant $merchant, Order $order, PaymentAttempt $attempt, StripeTestGateway $gateway, PaymentSettlement $settlement): RedirectResponse
    {
        $order = $merchant->orders()->whereKey($order->id)->firstOrFail();
        $attempt = $order->paymentAttempts()->whereKey($attempt->id)->firstOrFail();
        if ($attempt->method !== 'stripe_test' || ! $attempt->provider_session_id) {
            throw ValidationException::withMessages(['payment' => 'Only issued Stripe test sessions can be reconciled.']);
        }
        try {
            $response = $gateway->retrieveCheckout($attempt, $merchant->paymentSetting);
            $result = $settlement->applyStripeCheckout($attempt, $response, 'reconcile');
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['payment' => 'Stripe test status could not be retrieved. Retry later.']);
        }

        return back()->with('status', 'Reconciliation result: '.$result.'.');
    }

    private function canInitiate(Order $order): void
    {
        if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
            throw ValidationException::withMessages(['payment' => 'Paid or cancelled orders cannot initiate new payments.']);
        }
    }
}
