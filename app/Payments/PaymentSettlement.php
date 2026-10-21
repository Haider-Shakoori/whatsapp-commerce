<?php

namespace App\Payments;

use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

final class PaymentSettlement
{
    /**
     * Trusted Stripe test event or a server-side reconciliation response.
     * Never infer paid from a browser return, customer message or transaction reference alone.
     */
    public function applyStripeCheckout(PaymentAttempt $target, array $session, string $eventType): string
    {
        return DB::transaction(function () use ($target, $session, $eventType): string {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($target->id);
            $order = $attempt->merchant->orders()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();

            if ($attempt->method !== 'stripe_test' || ($session['id'] ?? '') !== $attempt->provider_session_id
                || ($session['metadata']['attempt_id'] ?? '') !== $attempt->public_id
                || (string) ($session['metadata']['order_id'] ?? '') !== (string) $order->id
                || (string) ($session['metadata']['merchant_id'] ?? '') !== (string) $attempt->merchant_id
                || (string) ($session['client_reference_id'] ?? '') !== (string) $order->id
                || ($session['livemode'] ?? null) !== false
                || strtolower((string) ($session['currency'] ?? '')) !== strtolower($attempt->currency)
                || (int) ($session['amount_total'] ?? -1) !== $attempt->amount_minor
                || $attempt->currency !== $order->currency
                || $attempt->amount_minor !== $order->total_minor) {
                $attempt->update(['status' => 'review', 'review_note' => 'Mismatch in Stripe checkout identity, currency or amount.']);
                return 'review';
            }

            if (($session['payment_status'] ?? '') === 'paid' && in_array($eventType, [
                'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'reconcile',
            ], true)) {
                if ($order->status === 'cancelled') {
                    $attempt->update(['status' => 'review', 'review_note' => 'Paid after cancellation; refund requires manual review.']);
                    return 'review';
                }
                if ($order->payment_status === 'paid') {
                    // Replayed payment callbacks must not credit an order twice.
                    return $attempt->status === 'paid' ? 'duplicate' : 'review';
                }
                $attempt->update([
                    'status' => 'paid',
                    'provider_payment_id' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null,
                    'verified_at' => now(),
                ]);
                $order->update(['payment_status' => 'paid']);
                return 'paid';
            }

            if (($session['status'] ?? '') === 'expired' && $attempt->status !== 'paid') {
                $attempt->update(['status' => 'expired']);
                return 'expired';
            }
            if ($eventType === 'checkout.session.async_payment_failed' && $attempt->status !== 'paid') {
                $attempt->update(['status' => 'failed']);
                return 'failed';
            }

            return 'pending';
        });
    }
}
