<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantPaymentSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $slug = 'shop', string $currency = 'USD'): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Test Merchant', 'slug' => $slug, 'business_type' => 'retail',
            'currency' => $currency, 'timezone' => 'UTC', 'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);
        $settings = $merchant->paymentSetting()->create([
            'webhook_token' => (string) Str::uuid(),
            'cod_enabled' => true, 'bank_transfer_enabled' => true,
            'bank_instructions' => 'Test bank instructions - verify reference.',
            'stripe_test_enabled' => true,
            'stripe_test_secret' => 'sk_test_development_only',
            'stripe_test_webhook_secret' => 'whsec_development_only',
        ]);

        return [$owner, $merchant, $settings];
    }

    private function order(Merchant $merchant, int $amount = 25000): Order
    {
        return $merchant->orders()->create([
            'checkout_key' => (string) Str::uuid(),
            'customer_name' => 'Test Customer', 'customer_phone' => '93700000000',
            'currency' => $merchant->currency,
            'status' => 'pending', 'payment_status' => 'unpaid',
            'subtotal_minor' => $amount, 'delivery_minor' => 0, 'total_minor' => $amount,
        ]);
    }

    private function attempt(Merchant $merchant, Order $order): PaymentAttempt
    {
        return $order->paymentAttempts()->create([
            'merchant_id' => $merchant->id, 'public_id' => (string) Str::uuid(),
            'method' => 'stripe_test', 'status' => 'pending', 'currency' => $order->currency,
            'amount_minor' => $order->total_minor, 'provider_session_id' => 'cs_test_'.Str::random(16),
            'payment_url' => 'https://checkout.stripe.com/c/pay/test-session',
        ]);
    }

    private function session(PaymentAttempt $attempt, array $overrides = []): array
    {
        return array_replace([
            'id' => $attempt->provider_session_id, 'object' => 'checkout.session',
            'livemode' => false, 'status' => 'complete', 'payment_status' => 'paid',
            'client_reference_id' => (string) $attempt->order_id,
            'metadata' => [
                'merchant_id' => (string) $attempt->merchant_id,
                'order_id' => (string) $attempt->order_id,
                'attempt_id' => $attempt->public_id,
            ],
            'amount_total' => $attempt->amount_minor,
            'currency' => strtolower($attempt->currency),
            'payment_intent' => 'pi_test_1',
        ], $overrides);
    }

    private function webhook(MerchantPaymentSetting $settings, array $session, bool $valid = true, ?string $eventId = null): TestResponse
    {
        $body = json_encode([
            'id' => $eventId ?? 'evt_'.Str::random(18),
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ], JSON_THROW_ON_ERROR);
        $stamp = (string) time();
        $secret = $valid ? $settings->stripe_test_webhook_secret : 'whsec_fake_wrong';
        $signature = hash_hmac('sha256', $stamp.'.'.$body, $secret);

        return $this->call('POST', route('api.stripe-test.webhook', ['token' => $settings->webhook_token]),
            [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$stamp.',v1='.$signature,
            ], $body);
    }

    public function test_live_key_is_rejected_and_test_secrets_encrypted(): void
    {
        [$owner, $merchant, $settings] = $this->shop();
        $this->assertStringNotContainsString('sk_test_development', $settings->getRawOriginal('stripe_test_secret'));
        $this->assertStringNotContainsString('whsec_development', $settings->getRawOriginal('stripe_test_webhook_secret'));
        $this->actingAs($owner)->put(route('merchant.payments.settings.update', $merchant), [
            'cod_enabled' => '1', 'bank_transfer_enabled' => '0', 'stripe_test_enabled' => '1',
            'stripe_test_secret' => 'sk_live_FORBIDDEN',
        ])->assertSessionHasErrors('stripe_test_secret');
        $this->assertSame('sk_test_development_only', $settings->fresh()->stripe_test_secret);
    }

    public function test_manual_cash_requires_confirmation_before_order_becomes_paid(): void
    {
        [$owner, $merchant] = $this->shop();
        $order = $this->order($merchant);
        $this->actingAs($owner)->post(route('merchant.orders.payments.method', [$merchant, $order]),
            ['method' => 'cod'])->assertRedirect();
        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->actingAs($owner)->post(route('merchant.orders.payments.confirm', [$merchant, $order, $attempt]), [
            'reference' => 'RECEIPT-1234', 'confirmation' => 'RECEIVED',
        ])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame($owner->id, $attempt->fresh()->verified_by);
        $this->actingAs($owner)->patch(route('merchant.orders.status', [$merchant, $order]),
            ['status' => 'cancelled'])->assertSessionHasErrors('status');
    }

    public function test_test_checkout_session_generates_one_idempotent_link(): void
    {
        [$owner, $merchant] = $this->shop();
        $order = $this->order($merchant);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'cs_test_paidlater', 'url' => 'https://checkout.stripe.com/c/pay/test-session',
        ])]);
        $this->actingAs($owner)->post(route('merchant.orders.payments.link', [$merchant, $order]))->assertRedirect();
        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame('pending', $attempt->status);
        $this->assertSame('cs_test_paidlater', $attempt->provider_session_id);
        $this->assertStringNotContainsString('checkout.stripe.com', $attempt->getRawOriginal('payment_url'));
        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Idempotency-Key', 'commerce-attempt-'.$attempt->public_id));
        $this->actingAs($owner)->post(route('merchant.orders.payments.link', [$merchant, $order]))->assertRedirect();
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertSentCount(1);
    }

    public function test_stripe_signed_webhook_marks_paid_once(): void
    {
        [, $merchant, $settings] = $this->shop();
        $order = $this->order($merchant);
        $attempt = $this->attempt($merchant, $order);
        $event = 'evt_same_valid_event_123';
        $this->webhook($settings, $this->session($attempt), eventId: $event)->assertOk();
        $this->webhook($settings, $this->session($attempt), eventId: $event)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_bad_signature_and_wrong_amount_do_not_mark_paid(): void
    {
        [, $merchant, $settings] = $this->shop();
        $order = $this->order($merchant);
        $attempt = $this->attempt($merchant, $order);
        $this->webhook($settings, $this->session($attempt), valid: false)->assertUnauthorized();
        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->webhook($settings, $this->session($attempt, ['amount_total' => 1]))->assertOk();
        $this->assertSame('review', $attempt->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_cancelled_orders_are_flagged_for_refund_review(): void
    {
        [, $merchant, $settings] = $this->shop();
        $order = $this->order($merchant);
        $attempt = $this->attempt($merchant, $order);
        $order->update(['status' => 'cancelled']);
        $this->webhook($settings, $this->session($attempt))->assertOk();
        $this->assertSame('review', $attempt->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_server_side_reconciliation_marks_paid(): void
    {
        [$owner, $merchant] = $this->shop();
        $order = $this->order($merchant);
        $attempt = $this->attempt($merchant, $order);
        Http::fake(['api.stripe.com/*' => Http::response($this->session($attempt))]);
        $this->actingAs($owner)->post(route('merchant.orders.payments.reconcile', [$merchant, $order, $attempt]))
            ->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        Http::assertSentCount(1);
    }

    public function test_foreign_merchant_cannot_reconcile_or_confirm_payments(): void
    {
        [, $merchant] = $this->shop('one');
        [$otherOwner, $other] = $this->shop('two');
        $order = $this->order($merchant);
        $attempt = $this->attempt($merchant, $order);
        $this->actingAs($otherOwner)->post(route('merchant.orders.payments.reconcile', [$other, $order, $attempt]))
            ->assertNotFound();
        $this->actingAs($otherOwner)->post(route('merchant.orders.payments.link', [$merchant, $order]))
            ->assertForbidden();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_return_page_cannot_confirm_payment(): void
    {
        [, $merchant] = $this->shop();
        $order = $this->order($merchant);
        $attempt = $this->attempt($merchant, $order);
        $this->get(route('payments.return', $attempt->public_id))->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }
}
