<?php

namespace Tests\Feature;

use App\Models\ChatMenuSession;
use App\Models\InboundEvent;
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

class ChatCatalogMenuTest extends TestCase
{
    use RefreshDatabase;

    private array $incoming = [];

    private function setupShop(string $slug = 'shop'): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Demo Shop', 'slug' => $slug, 'business_type' => 'restaurant',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($user->id, ['role' => 'owner']);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Primary',
            'external_account_id' => (string) Str::uuid(), 'public_id' => (string) Str::uuid(),
            'credentials' => ['api_key' => 'rw_session_fake', 'webhook_secret' => 'test-webhook-secret-with-adequate-length'],
            'status' => 'connected', 'menu_enabled' => true,
        ]);

        config([
            'commerce.messaging_driver' => 'relaywa',
            'commerce.relaywa_base_url' => 'https://relaywa.example.test/api',
        ]);
        Http::fake(function (HttpRequest $req) {
            if ($req->method() === 'POST' && str_ends_with($req->url(), '/messages/text')) {
                return Http::response(['id' => (string) Str::uuid(), 'status' => 'sent']);
            }
            foreach ($this->incoming as $id => $msg) {
                if ($req->method() === 'GET' && str_ends_with($req->url(), '/messages/'.$id)) {
                    return Http::response($msg);
                }
            }

            return Http::response(['error' => 'missing'], 404);
        });

        return [$user, $merchant, $channel];
    }

    private function item(Merchant $merchant, string $name, int $price = 20000, bool $active = true, ?int $stock = 5, ?int $category = null)
    {
        return $merchant->products()->create([
            'name' => $name, 'slug' => Str::slug($name), 'price_minor' => $price,
            'currency' => $merchant->currency, 'stock_quantity' => $stock,
            'is_active' => $active, 'category_id' => $category,
        ]);
    }

    private function signed(MessagingChannel $channel, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $time = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $time.'.'.$body, $channel->credentials['webhook_secret']);

        return $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]), [], [], [],
            ['CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAYWA_TIMESTAMP' => $time, 'HTTP_X_RELAYWA_SIGNATURE' => $signature], $body);
    }

    private function say(MessagingChannel $channel, string $text, array $changes = []): void
    {
        $id = (string) Str::uuid();
        $this->incoming[$id] = array_merge([
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => $text,
            'sender_phone' => '93700111222', 'chat_jid' => '93700111222@s.whatsapp.net',
        ], $changes);

        $this->signed($channel, [
            'id' => (string) Str::uuid(), 'type' => 'message.received',
            'sessionId' => $channel->external_account_id, 'data' => ['messageId' => $id],
        ])->assertOk();
    }

    private function lastReply(): string
    {
        return OutboundMessage::latest('id')->firstOrFail()->body;
    }

    public function test_customer_browses_categories_and_sees_product_variants(): void
    {
        [, $merchant, $channel] = $this->setupShop();
        $pizza = $merchant->categories()->create(['name' => 'Pizza', 'slug' => 'pizza']);
        $this->item($merchant, 'Hidden', 9000, false, 20, $pizza->id);
        $this->item($merchant, 'Sold Out', 9000, true, 0, $pizza->id);
        $product = $this->item($merchant, 'Margherita', 45000, true, 6, $pizza->id);
        $product->variants()->create(['merchant_id' => $merchant->id,
            'name' => 'Large', 'price_delta_minor' => 10000, 'is_active' => true]);
        $group = $product->modifierGroups()->create(['merchant_id' => $merchant->id,
            'name' => 'Toppings', 'min_choices' => 0, 'max_choices' => 1]);
        $group->options()->create(['merchant_id' => $merchant->id, 'name' => 'Olives', 'price_minor' => 2500]);

        $this->say($channel, 'MENU');
        $this->assertStringContainsString('Demo Shop', $this->lastReply());
        $this->assertStringContainsString('Pizza', $this->lastReply());

        $this->say($channel, '2'); // 1 = All products, 2 = Pizza
        $this->assertStringContainsString('Margherita', $this->lastReply());
        $this->assertStringNotContainsString('Hidden', $this->lastReply());
        $this->assertStringNotContainsString('Sold Out', $this->lastReply());

        $this->say($channel, '1');
        $this->assertStringContainsString('450.00 AFN', $this->lastReply());
        $this->assertStringContainsString('Large', $this->lastReply());
        $this->assertStringContainsString('Olives', $this->lastReply());
        $this->assertStringContainsString('BACK', $this->lastReply());
        $this->say($channel, 'BACK');
        $this->assertStringContainsString('Margherita', $this->lastReply());
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pagination_is_scoped_to_the_merchant(): void
    {
        [, $merchant, $channel] = $this->setupShop('paging');
        [, $foreign] = $this->setupShop('other');
        $this->item($foreign, 'Private Foreign Item');
        for ($n = 1; $n <= 8; $n++) {
            $this->item($merchant, 'Item '.$n);
        }
        $this->say($channel, 'HI');
        $this->say($channel, '1');
        $this->assertStringContainsString('page 1/2', $this->lastReply());
        $this->assertStringNotContainsString('Item 8', $this->lastReply());
        $this->say($channel, 'NEXT');
        $this->assertStringContainsString('page 2/2', $this->lastReply());
        $this->assertStringContainsString('Item 8', $this->lastReply());
        $this->assertStringNotContainsString('Private Foreign Item', $this->lastReply());
        $this->say($channel, 'PREV');
        $this->assertStringContainsString('page 1/2', $this->lastReply());
    }

    public function test_non_menu_messages_and_group_or_outbound_events_are_ignored(): void
    {
        [, $merchant, $channel] = $this->setupShop('filters');
        $this->item($merchant, 'Burger');
        $this->say($channel, 'How are you?');
        $this->say($channel, 'MENU', ['chat_jid' => '1234567@g.us']);
        $this->say($channel, 'MENU', ['direction' => 'outbound']);
        $this->say($channel, 'MENU', ['message_type' => 'image']);
        $this->assertDatabaseCount('outbound_messages', 0);
        $this->assertDatabaseCount('chat_menu_sessions', 0);

        $channel->update(['menu_enabled' => false]);
        $this->say($channel, 'MENU');
        $this->assertDatabaseCount('outbound_messages', 0);
    }

    public function test_customer_stop_and_restart_are_respected(): void
    {
        [, $merchant, $channel] = $this->setupShop('optout');
        $this->item($merchant, 'Burger');
        $this->say($channel, 'MENU');
        $this->say($channel, 'STOP');
        $this->assertStringContainsString('Send START', $this->lastReply());
        $count = OutboundMessage::count();
        $this->say($channel, 'MENU');
        $this->assertSame($count, OutboundMessage::count());
        $this->assertTrue(ChatMenuSession::firstOrFail()->opted_out);
        $this->say($channel, 'START');
        $this->assertFalse(ChatMenuSession::firstOrFail()->opted_out);
        $this->assertGreaterThan($count, OutboundMessage::count());
    }

    public function test_duplicate_webhook_produces_one_reply(): void
    {
        [, $merchant, $channel] = $this->setupShop('dupes');
        $this->item($merchant, 'Cake');
        $id = (string) Str::uuid();
        $this->incoming[$id] = [
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => 'MENU',
            'sender_phone' => '93700111222', 'chat_jid' => '93700111222@s.whatsapp.net',
        ];
        $payload = ['id' => 'fixed-event-id-1', 'type' => 'message.received',
            'sessionId' => $channel->external_account_id, 'data' => ['messageId' => $id]];
        $this->signed($channel, $payload)->assertOk();
        $this->signed($channel, $payload)->assertOk();
        $this->assertDatabaseCount('inbound_events', 1);
        $this->assertDatabaseCount('outbound_messages', 1);
        $this->assertNotNull(InboundEvent::firstOrFail()->processed_at);
    }

    public function test_manager_must_have_webhook_secret_and_cannot_toggle_foreign_channel(): void
    {
        [$owner, $merchant, $channel] = $this->setupShop('toggle');
        [$other, $foreign] = $this->setupShop('foreign');
        $channel->update(['menu_enabled' => false, 'credentials' => ['api_key' => 'rw_session_fake']]);
        $this->actingAs($owner)->post(route('merchant.channels.menu', [$merchant, $channel]),
            ['menu_enabled' => '1'])->assertSessionHasErrors('menu_enabled');
        $this->actingAs($other)->post(route('merchant.channels.menu', [$foreign, $channel]),
            ['menu_enabled' => '1'])->assertNotFound();
        $this->assertFalse($channel->fresh()->menu_enabled);

        $channel->update(['credentials' => [
            'api_key' => 'rw_session_fake', 'webhook_secret' => 'configured-webhook-secret-123',
        ]]);
        $this->actingAs($owner)->post(route('merchant.channels.menu', [$merchant, $channel]),
            ['menu_enabled' => '1'])->assertRedirect();
        $this->assertTrue($channel->fresh()->menu_enabled);
    }
}
