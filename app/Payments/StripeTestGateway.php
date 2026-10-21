<?php

namespace App\Payments;

use App\Models\MerchantPaymentSetting;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeTestGateway
{
    public function createCheckout(PaymentAttempt $attempt, MerchantPaymentSetting $settings): array
    {
        $key = $settings->stripe_test_secret;
        if (! $settings->stripe_test_enabled || ! is_string($key) || ! str_starts_with($key, 'sk_test_')) {
            throw new RuntimeException('Stripe TEST key is not configured.');
        }
        if (! in_array($attempt->currency, ['USD', 'EUR', 'GBP'], true)) {
            throw new RuntimeException('This currency is not enabled for the sandbox integration.');
        }

        $order = $attempt->order;
        $response = Http::withToken($key)
            ->asForm()->acceptJson()->connectTimeout(5)->timeout(20)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders(['Idempotency-Key' => 'commerce-attempt-'.$attempt->public_id])
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => route('payments.return', ['publicId' => $attempt->public_id], true),
                'cancel_url' => route('payments.return', ['publicId' => $attempt->public_id], true),
                'client_reference_id' => (string) $order->id,
                'metadata[merchant_id]' => (string) $attempt->merchant_id,
                'metadata[order_id]' => (string) $order->id,
                'metadata[attempt_id]' => $attempt->public_id,
                'line_items[0][price_data][currency]' => strtolower($attempt->currency),
                'line_items[0][price_data][product_data][name]' => 'Order #'.$order->id.' — '.$order->merchant->name,
                'line_items[0][price_data][unit_amount]' => (string) $attempt->amount_minor,
                'line_items[0][quantity]' => '1',
                'expires_at' => (string) now()->addHours(2)->timestamp,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Stripe test checkout creation failed (HTTP '.$response->status().').');
        }
        $sessionId = $response->json('id');
        $url = $response->json('url');

        if (! is_string($sessionId) || ! str_starts_with($sessionId, 'cs_test_')
            || ! is_string($url) || ! str_starts_with($url, 'https://checkout.stripe.com/')) {
            throw new RuntimeException('Unexpected Stripe test checkout response.');
        }

        return ['session_id' => $sessionId, 'url' => $url, 'expires_at' => now()->addHours(2)];
    }

    public function retrieveCheckout(PaymentAttempt $attempt, MerchantPaymentSetting $settings): array
    {
        $key = $settings->stripe_test_secret;
        if (! is_string($key) || ! str_starts_with($key, 'sk_test_')
            || ! is_string($attempt->provider_session_id)
            || ! str_starts_with($attempt->provider_session_id, 'cs_test_')) {
            throw new RuntimeException('Invalid Stripe test reconciliation credentials.');
        }
        $response = Http::withToken($key)->acceptJson()->connectTimeout(5)->timeout(15)
            ->withOptions(['allow_redirects' => false])
            ->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($attempt->provider_session_id));
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Cannot retrieve Stripe test checkout status.');
        }

        return $response->json();
    }
}
