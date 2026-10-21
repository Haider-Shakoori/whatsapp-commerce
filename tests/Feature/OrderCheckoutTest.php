<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\Order;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private array $inbound = [];

    private function shop(string $slug): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Demo Shop', 'slug' => $slug, 'business_type' => 'restaurant',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Primary',
            'external_account_id' => (string) Str::uuid(), 'public_id' => (string) Str::uuid(),
            'credentials' => ['api_key' => 'rw_session_local_testing',
                'webhook_secret' => 'local-only-webhook-secret-not-real'],
            'status' => 'connected', 'menu_enabled' => true,
        ]);
        config([
            'commerce.messaging_driver' => 'relaywa',
            'commerce.relaywa_base_url' => 'https://relaywa.example.test/api',
        ]);
        Http::fake(function (HttpRequest $req) {
            if ($req->method() === 'POST' && str_ends_with($req->url(), '/messages/text')) {
                return Http::response(['id' => (string) Str::uuid()]);
            }
            foreach ($this->inbound as $id => $message) {
                if ($req->method() === 'GET' && str_ends_with($req->url(), '/messages/'.$id)) {
                    return Http::response($message);
                }
            }

            return Http::response(['error' => 'not found'], 404);
        });

        return [$owner, $merchant, $channel];
    }

    private function product(Merchant $merchant, string $name = 'Burger', int $stock = 10, int $price = 25000)
    {
        return $merchant->products()->create([
            'name' => $name, 'slug' => Str::slug($name),
            'currency' => $merchant->currency, 'price_minor' => $price,
            'stock_quantity' => $stock, 'is_active' => true,
        ]);
    }

    private function signed(MessagingChannel $channel, string $messageId, string $eventId): TestResponse
    {
        $payload = json_encode([
            'id' => $eventId, 'type' => 'message.received',
            'sessionId' => $channel->external_account_id, 'data' => ['messageId' => $messageId],
        ], JSON_THROW_ON_ERROR);
        $time = (string) time();
        $sig = 'sha256='.hash_hmac('sha256', $time.'.'.$payload, $channel->credentials['webhook_secret']);

        return $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RELAYWA_TIMESTAMP' => $time, 'HTTP_X_RELAYWA_SIGNATURE' => $sig,
        ], $payload);
    }

    private function say(MessagingChannel $channel, string $message, string $phone = '93700111222', ?string $sameId = null): string
    {
        $id = $sameId ?? (string) Str::uuid();
        $this->inbound[$id] = [
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => $message,
            'sender_phone' => $phone, 'chat_jid' => $phone.'@s.whatsapp.net',
        ];
        $this->signed($channel, $id, (string) Str::uuid())->assertOk();

        return OutboundMessage::query()->where('messaging_channel_id', $channel->id)
            ->where('recipient', $phone)->latest('id')->first()?->body ?? '';
    }

    private function addToCart(MessagingChannel $channel, int $quantity = 1): void
    {
        $this->say($channel, 'MENU');
        $this->say($channel, '1');
        $this->say($channel, '1');
        $this->say($channel, 'ADD '.$quantity);
    }

    public function test_pickup_checkout_creates_unpaid_order_deducts_stock_and_empties_cart(): void
    {
        [, $merchant, $channel] = $this->shop('pickup');
        $product = $this->product($merchant, 'Burger', 7);
        $this->addToCart($channel, 2);
        $this->assertStringContainsString('Please type your name', $this->say($channel, 'CHECKOUT'));
        $this->assertStringContainsString('Choose fulfillment', $this->say($channel, 'Sediq Customer'));
        $this->assertStringContainsString('subtotal (provisional): 500.00 AFN', $this->say($channel, '1'));
        $this->assertStringContainsString('Order #', $this->say($channel, 'CONFIRM'));

        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pickup', $order->delivery_details['method']);
        $this->assertSame(50000, $order->total_minor);
        $this->assertSame('Sediq Customer', $order->customer_name);
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('orders', 1);

        $this->say($channel, 'CONFIRM');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_delivery_checkout_requires_address_and_merchant_fee_confirmation(): void
    {
        [, $merchant, $channel] = $this->shop('delivery');
        $this->product($merchant);
        $this->addToCart($channel);
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Layla Ahmad');
        $this->assertStringContainsString('delivery address', $this->say($channel, '2'));
        $this->assertStringContainsString('10–200', $this->say($channel, 'Short'));
        $this->assertStringContainsString('not configured', $this->say($channel, 'Street 10, Kabul, near park'));
        $this->assertStringContainsString('Order #', $this->say($channel, 'CONFIRM'));
        $order = Order::firstOrFail();
        $this->assertSame('delivery', $order->delivery_details['method']);
        $this->assertSame('Street 10, Kabul, near park', $order->delivery_details['address']);
        $this->assertSame(0, $order->delivery_minor);
        $this->assertSame('not_configured', $order->delivery_details['delivery_fee_status']);
    }

    public function test_cancel_preserves_cart_and_no_order(): void
    {
        [, $merchant, $channel] = $this->shop('cancel');
        $this->product($merchant);
        $this->addToCart($channel);
        $this->say($channel, 'CHECKOUT');
        $this->assertStringContainsString('Checkout cancelled', $this->say($channel, 'CANCEL'));
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changed_price_or_missing_stock_prevents_confirmation(): void
    {
        [, $merchant, $channel] = $this->shop('stale');
        $product = $this->product($merchant, 'Burger', 5);
        $this->addToCart($channel, 2);
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Customer Test');
        $this->say($channel, '1');
        $product->update(['price_minor' => 26000]);
        $this->assertStringContainsString('price changed', $this->say($channel, 'CONFIRM'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $product->update(['price_minor' => 25000, 'stock_quantity' => 1]);
        $this->assertStringContainsString('Insufficient stock', $this->say($channel, 'CONFIRM'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_cancelled_order_restocks_once_and_tracks_adjustments(): void
    {
        [$owner, $merchant, $channel] = $this->shop('restock');
        $product = $this->product($merchant, 'Burger', 10);
        $this->addToCart($channel, 3);
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Test Buyer');
        $this->say($channel, 'PICKUP');
        $this->say($channel, 'CONFIRM');
        $order = Order::firstOrFail();
        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->actingAs($owner)->patch(route('merchant.orders.status', [$merchant, $order]),
            ['status' => 'cancelled'])->assertRedirect();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', [
            'product_id' => $product->id, 'delta' => 3, 'before_quantity' => 7,
            'after_quantity' => 10, 'reason' => 'Cancelled order #'.$order->id,
        ]);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->actingAs($owner)->patch(route('merchant.orders.status', [$merchant, $order]),
            ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_order_status_transitions_and_no_cancellation_after_completion(): void
    {
        [$owner, $merchant, $channel] = $this->shop('workflow');
        $this->product($merchant);
        $this->addToCart($channel);
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Test Buyer');
        $this->say($channel, 'PICKUP');
        $this->say($channel, 'CONFIRM');
        $order = Order::firstOrFail();

        foreach (['accepted', 'preparing', 'ready', 'completed'] as $status) {
            $this->actingAs($owner)->patch(route('merchant.orders.status', [$merchant, $order]),
                ['status' => $status])->assertRedirect();
            $this->assertSame($status, $order->fresh()->status);
        }
        $this->actingAs($owner)->patch(route('merchant.orders.status', [$merchant, $order]),
            ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_other_merchants_cannot_access_orders_or_modify_status(): void
    {
        [$owner, $merchant, $channel] = $this->shop('one');
        [$foreignUser, $foreignMerchant] = $this->shop('two');
        $this->product($merchant);
        $this->addToCart($channel);
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Test Buyer');
        $this->say($channel, 'PICKUP');
        $this->say($channel, 'CONFIRM');
        $order = Order::firstOrFail();

        $this->actingAs($foreignUser)->get(route('merchant.orders.show', [$merchant, $order]))->assertForbidden();
        $this->actingAs($foreignUser)->get(route('merchant.orders.show', [$foreignMerchant, $order]))->assertNotFound();
        $this->actingAs($foreignUser)->patch(route('merchant.orders.status', [$foreignMerchant, $order]),
            ['status' => 'accepted'])->assertNotFound();
        $this->assertSame('pending', $order->fresh()->status);
        $this->actingAs($owner)->get(route('merchant.orders.show', [$merchant, $order]))->assertOk();
    }

    public function test_duplicate_confirm_event_does_not_create_second_order(): void
    {
        [, $merchant, $channel] = $this->shop('duplicate');
        $product = $this->product($merchant, 'Cake', 5);
        $this->addToCart($channel);
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Test Buyer');
        $this->say($channel, 'PICKUP');
        $id = (string) Str::uuid();
        $this->inbound[$id] = [
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => 'CONFIRM',
            'sender_phone' => '93700111222', 'chat_jid' => '93700111222@s.whatsapp.net',
        ];
        $this->signed($channel, $id, 'first-confirm-event')->assertOk();
        $this->signed($channel, $id, 'retry-confirm-event')->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_variant_and_modifier_stock_are_restored_on_order_cancellation(): void
    {
        [$owner, $merchant, $channel] = $this->shop('variants');
        $product = $this->product($merchant, 'Pizza', 10, 25000);
        $variant = $product->variants()->create([
            'merchant_id' => $merchant->id, 'name' => 'Large',
            'price_delta_minor' => 5000, 'stock_quantity' => 3, 'is_active' => true,
        ]);
        $group = $product->modifierGroups()->create([
            'merchant_id' => $merchant->id, 'name' => 'Toppings',
            'min_choices' => 1, 'max_choices' => 1,
        ]);
        $group->options()->create([
            'merchant_id' => $merchant->id, 'name' => 'Olives', 'price_minor' => 1000,
        ]);
        $this->say($channel, 'MENU');
        $this->say($channel, '1');
        $this->say($channel, '1');
        $this->say($channel, 'ADD 2');
        $this->say($channel, '1');
        $this->say($channel, '1');
        $this->say($channel, 'CHECKOUT');
        $this->say($channel, 'Pizza Buyer');
        $this->say($channel, 'PICKUP');
        $this->say($channel, 'CONFIRM');

        $order = Order::firstOrFail();
        $this->assertSame(62000, $order->total_minor);
        $this->assertSame(31000, $order->items()->firstOrFail()->unit_price_minor);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, $variant->fresh()->stock_quantity);

        $this->actingAs($owner)->patch(route('merchant.orders.status', [$merchant, $order]),
            ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
    }
}
