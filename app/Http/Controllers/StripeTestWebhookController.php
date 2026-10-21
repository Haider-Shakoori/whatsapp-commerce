<?php

namespace App\Http\Controllers;

use App\Models\MerchantPaymentSetting;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Payments\PaymentSettlement;
use App\Payments\StripeWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeTestWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, StripeWebhookVerifier $verifier, PaymentSettlement $settlement): JsonResponse
    {
        $settings = MerchantPaymentSetting::where('webhook_token', $token)->first();
        if (! $settings || strlen($request->getContent()) > 262144
            || ! $verifier->verify($request->getContent(), (string) $request->header('Stripe-Signature', ''),
                $settings->stripe_test_webhook_secret)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $event = json_decode($request->getContent(), true);
        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! str_starts_with($event['id'], 'evt_')
            || strlen($event['id']) > 255 || ! is_string($event['type'] ?? null)
            || ! is_array($event['data']['object'] ?? null)) {
            return response()->json(['error' => 'Invalid event'], 422);
        }

        $record = PaymentWebhookEvent::firstOrCreate([
            'merchant_id' => $settings->merchant_id, 'provider' => 'stripe_test',
            'provider_event_id' => $event['id'],
        ], ['outcome' => 'received']);

        if (! $record->wasRecentlyCreated) {
            return response()->json(['received' => true], 200);
        }

        $object = $event['data']['object'];
        if (! in_array($event['type'], [
            'checkout.session.completed', 'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed', 'checkout.session.expired',
        ], true) || ($object['object'] ?? null) !== 'checkout.session') {
            $record->update(['outcome' => 'ignored']);

            return response()->json(['received' => true], 200);
        }

        $attempt = PaymentAttempt::query()->where('merchant_id', $settings->merchant_id)
            ->where('method', 'stripe_test')->where('provider_session_id', $object['id'] ?? '')->first();

        if (! $attempt) {
            $record->update(['outcome' => 'unmatched']);

            return response()->json(['received' => true], 200);
        }

        $record->update(['payment_attempt_id' => $attempt->id]);
        $outcome = $settlement->applyStripeCheckout($attempt, $object, $event['type']);
        $record->update(['outcome' => $outcome]);

        return response()->json(['received' => true], 200);
    }
}
