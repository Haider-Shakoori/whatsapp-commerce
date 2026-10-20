<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ConversationalCartTest extends TestCase
{
    use RefreshDatabase;

    private array $messages = [];

    private function shop(string $slug = 'cart-shop'): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Test Store', 'slug' => $slug, 'business_type' => 'restaurant',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Primary',
            'external_account_id' => (string) Str::uuid(), 'public_id' => (string) Str::uuid(),
            'credentials' => ['api_key' => 'rw_session_test_only',
                'webhook_secret' => 'fake-signing-secret-sufficient-length'],
            'status' => 'connected', 'menu_enabled' => true,
        ]);
        config(['commerce.messaging_driver' => 'relaywa', 'commerce.relaywa_base_url' => 'https://relaywa.example.test/api']);
        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/messages/text')) {
                return Http::response(['id' => (string) Str::uuid()]);
            }
            foreach ($this->messages as $id => $message) {
                if ($request->method() === 'GET' && str_ends_with($request->url(), '/messages/'.$id)) {
                    return Http::response($message);
                }
            }

            return Http::response(['error' => 'missing'], 404);
        });

        return [$merchant, $channel];
    }

    private function product(Merchant $merchant, string $name, int $price = 25000, int $stock = 10)
    {
        return $merchant->products()->create([
            'name' => $name, 'slug' => Str::slug($name), 'price_minor' => $price,
            'currency' => $merchant->currency, 'stock_quantity' => $stock, 'is_active' => true,
        ]);
    }

    private function signed(MessagingChannel $channel, array $payload): TestResponse
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $stamp = (string) time();
        $sig = 'sha256='.hash_hmac('sha256', $stamp.'.'.$json, $channel->credentials['webhook_secret']);

        return $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]),
            [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAYWA_TIMESTAMP' => $stamp,
                'HTTP_X_RELAYWA_SIGNATURE' => $sig,
            ], $json);
    }

    private function say(MessagingChannel $channel, string $text, string $phone = '93700111222'): string
    {
        $id = (string) Str::uuid();
        $this->messages[$id] = [
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => $text,
            'sender_phone' => $phone, 'chat_jid' => $phone.'@s.whatsapp.net',
        ];
        $this->signed($channel, [
            'id' => (string) Str::uuid(), 'type' => 'message.received',
            'sessionId' => $channel->external_account_id, 'data' => ['messageId' => $id],
        ])->assertOk();

        return OutboundMessage::query()
            ->where('messaging_channel_id', $channel->id)
            ->where('recipient', $phone)->latest('id')->first()?->body ?? '';
    }

    private function open(MessagingChannel $channel): void
    {
        $this->say($channel, 'MENU');
        $this->say($channel, '1');
        $this->say($channel, '1');
    }

    public function test_simple_cart_add_quantity_remove_and_confirmed_clear(): void
    {
        [$merchant, $channel] = $this->shop();
        $this->product($merchant, 'Burger');
        $this->open($channel);
        $this->assertStringContainsString('added to cart', $this->say($channel, 'ADD 2'));
        $this->assertStringContainsString('Subtotal: 500.00 AFN', $this->say($channel, 'CART'));
        $this->assertStringContainsString('Subtotal: 750.00 AFN', $this->say($channel, 'QTY 1 3'));
        $this->assertStringContainsString('empty', $this->say($channel, 'REMOVE 1'));
        $this->assertDatabaseCount('cart_items', 0);

        $this->open($channel);
        $this->say($channel, 'ADD');
        $this->say($channel, 'CLEAR');
        $this->assertDatabaseCount('cart_items', 1);
        $this->say($channel, 'CLEAR YES');
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_variants_and_required_toppings_have_correct_minor_unit_prices(): void
    {
        [$merchant, $channel] = $this->shop('pizza');
        $product = $this->product($merchant, 'Pizza', 45000);
        $product->variants()->create(['merchant_id' => $merchant->id,
            'name' => 'Small', 'price_delta_minor' => 0, 'stock_quantity' => 10, 'is_active' => true]);
        $large = $product->variants()->create(['merchant_id' => $merchant->id,
            'name' => 'Large', 'price_delta_minor' => 10000, 'stock_quantity' => 10, 'is_active' => true]);
        $group = $product->modifierGroups()->create(['merchant_id' => $merchant->id,
            'name' => 'Toppings', 'min_choices' => 1, 'max_choices' => 2]);
        $group->options()->create(['merchant_id' => $merchant->id, 'name' => 'Cheese', 'price_minor' => 2000]);
        $group->options()->create(['merchant_id' => $merchant->id, 'name' => 'Olives', 'price_minor' => 1000]);

        $this->open($channel);
        $this->assertStringContainsString('Choose a variant', $this->say($channel, 'ADD 2'));
        $this->assertStringContainsString('Toppings', $this->say($channel, '2'));
        $this->assertStringContainsString('between 1 and 2', $this->say($channel, '0'));
        $this->assertStringContainsString('added to cart', $this->say($channel, '1,2'));
        $line = CartItem::firstOrFail();
        $this->assertSame($large->id, $line->product_variant_id);
        $this->assertSame(58000, $line->unit_price_minor);
        $this->assertSame(2, $line->quantity);
        $this->assertStringContainsString('Subtotal: 1,160.00 AFN', $this->say($channel, 'CART'));
    }

    public function test_reordered_options_merge_into_one_cart_line(): void
    {
        [$merchant, $channel] = $this->shop('merge');
        $p = $this->product($merchant, 'Salad');
        $g = $p->modifierGroups()->create(['merchant_id' => $merchant->id, 'name' => 'Extras',
            'min_choices' => 0, 'max_choices' => 2]);
        $g->options()->create(['merchant_id' => $merchant->id, 'name' => 'A', 'price_minor' => 100]);
        $g->options()->create(['merchant_id' => $merchant->id, 'name' => 'B', 'price_minor' => 200]);

        $this->open($channel);
        $this->say($channel, 'ADD');
        $this->say($channel, '1,2');
        $this->say($channel, 'ADD 2');
        $this->say($channel, '2,1');
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(3, CartItem::firstOrFail()->quantity);
    }

    public function test_stock_is_checked_across_variants_and_quantity_updates(): void
    {
        [$merchant, $channel] = $this->shop('stock');
        $p = $this->product($merchant, 'Shirt', 10000, 5);
        foreach (['Red', 'Blue'] as $color) {
            $p->variants()->create(['merchant_id' => $merchant->id, 'name' => $color,
                'price_delta_minor' => 0, 'stock_quantity' => 2, 'is_active' => true]);
        }
        $this->open($channel);
        $this->say($channel, 'ADD 2');
        $this->say($channel, '1');
        $this->say($channel, 'ADD 2');
        $this->assertStringContainsString('insufficient stock', $this->say($channel, '1'));
        $this->say($channel, 'ADD 4');
        $this->assertStringContainsString('Not enough available stock', $this->say($channel, '2'));
        $this->assertStringContainsString('insufficient stock', $this->say($channel, 'QTY 1 4'));
        $this->assertSame(2, CartItem::sum('quantity'));
    }

    public function test_stop_preserves_cart_and_checkout_does_not_create_orders(): void
    {
        [$merchant, $channel] = $this->shop('stop');
        $this->product($merchant, 'Juice');
        $this->open($channel);
        $this->say($channel, 'ADD');
        $this->say($channel, 'STOP');
        $count = OutboundMessage::count();
        $this->say($channel, 'CLEAR YES');
        $this->assertSame($count, OutboundMessage::count());
        $this->assertDatabaseCount('cart_items', 1);
        $this->say($channel, 'START');
        $this->assertStringContainsString('not available', $this->say($channel, 'CHECKOUT'));
        $this->assertStringContainsString('Subtotal: 250.00 AFN', $this->say($channel, 'CART'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_relaywa_message_with_different_envelope_does_not_add_twice(): void
    {
        [$merchant, $channel] = $this->shop('duplicate');
        $this->product($merchant, 'Cake');
        $this->open($channel);
        $messageId = (string) Str::uuid();
        $this->messages[$messageId] = [
            'id' => $messageId, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => 'ADD',
            'sender_phone' => '93700111222', 'chat_jid' => '93700111222@s.whatsapp.net',
        ];
        foreach ([1, 2] as $n) {
            $this->signed($channel, [
                'id' => 'different-'.$n, 'type' => 'message.received',
                'sessionId' => $channel->external_account_id, 'data' => ['messageId' => $messageId],
            ])->assertOk();
        }
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(1, CartItem::firstOrFail()->quantity);
        $this->assertDatabaseCount('outbound_messages', 4);
    }

    public function test_cart_is_separated_across_merchants_and_customers(): void
    {
        [$first, $channelOne] = $this->shop('one');
        [$second, $channelTwo] = $this->shop('two');
        $this->product($first, 'Private Burger');
        $this->product($second, 'Private Pizza');
        $this->open($channelOne);
        $this->say($channelOne, 'ADD');
        $this->open($channelTwo);
        $this->say($channelTwo, 'ADD');
        $this->assertStringContainsString('Private Burger', $this->say($channelOne, 'CART'));
        $this->assertStringNotContainsString('Private Pizza', $this->say($channelOne, 'CART'));
        $this->assertStringContainsString('Private Pizza', $this->say($channelTwo, 'CART'));
        $this->say($channelOne, 'MENU', '93700999888');
        $this->assertStringContainsString('empty', $this->say($channelOne, 'CART', '93700999888'));
        $this->assertDatabaseCount('shopping_carts', 2);
        $this->assertDatabaseCount('orders', 0);
    }
}
