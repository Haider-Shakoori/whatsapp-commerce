<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerNote;
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

class TeamInboxTest extends TestCase
{
    use RefreshDatabase;

    private array $messages = [];

    private function setupMerchant(string $slug = 'cafe'): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => $slug, 'slug' => $slug, 'business_type' => 'restaurant',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Shop WhatsApp',
            'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(),
            'credentials' => [
                'api_key' => 'rw_session_test_only',
                'webhook_secret' => 'webhook-signature-secret-for-test',
            ],
            'status' => 'connected', 'menu_enabled' => false,
        ]);
        config(['commerce.messaging_driver' => 'relaywa',
            'commerce.relaywa_base_url' => 'https://relaywa.example.test/api']);

        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/messages/text')) {
                return Http::response(['id' => (string) Str::uuid(), 'status' => 'sent']);
            }
            foreach ($this->messages as $id => $message) {
                if ($request->method() === 'GET' && str_ends_with($request->url(), '/messages/'.$id)) {
                    return Http::response($message);
                }
            }

            return Http::response(['error' => 'not found'], 404);
        });

        return [$owner, $merchant, $channel];
    }

    private function signed(MessagingChannel $channel, string $messageId, string $eventId): TestResponse
    {
        $body = json_encode([
            'id' => $eventId, 'type' => 'message.received',
            'sessionId' => $channel->external_account_id,
            'data' => ['messageId' => $messageId],
        ], JSON_THROW_ON_ERROR);
        $stamp = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $stamp.'.'.$body, $channel->credentials['webhook_secret']);

        return $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]),
            [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAYWA_TIMESTAMP' => $stamp,
                'HTTP_X_RELAYWA_SIGNATURE' => $signature,
            ], $body);
    }

    private function inbound(MessagingChannel $channel, string $body, ?string $id = null, array $changes = []): string
    {
        $id ??= (string) Str::uuid();
        $this->messages[$id] = array_replace([
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text',
            'text_body' => $body, 'sender_phone' => '93700111222',
            'chat_jid' => '93700111222@s.whatsapp.net',
        ], $changes);
        $this->signed($channel, $id, (string) Str::uuid())->assertOk();

        return $id;
    }

    public function test_inbox_records_messages_even_when_chat_menu_is_disabled(): void
    {
        [$owner, $merchant, $channel] = $this->setupMerchant();
        $this->inbound($channel, 'Can I order a meal?');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_messages', 1);
        $conversation = Conversation::firstOrFail();
        $this->assertSame($merchant->id, $conversation->merchant_id);
        $this->assertSame(1, $conversation->unread_count);
        $message = $conversation->messages()->firstOrFail();
        $this->assertSame('Can I order a meal?', $message->body);
        $this->assertStringNotContainsString('Can I order a meal?', $message->getRawOriginal('body'));
        $this->assertDatabaseCount('outbound_messages', 0);
        $this->actingAs($owner)->get(route('merchant.inbox.index', $merchant))->assertOk()
            ->assertSee('93700111222');
        $this->actingAs($owner)->get(route('merchant.inbox.show', [$merchant, $conversation]))
            ->assertOk()->assertSee('Can I order a meal?');
    }

    public function test_duplicate_provider_message_ids_do_not_double_increment_unread(): void
    {
        [, , $channel] = $this->setupMerchant('repeat');
        $id = $this->inbound($channel, 'Hello');
        $this->signed($channel, $id, (string) Str::uuid())->assertOk();
        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseCount('inbound_events', 2);
        $this->assertSame(1, Conversation::firstOrFail()->unread_count);
    }

    public function test_inbound_filter_excludes_groups_and_outbound_messages(): void
    {
        [, , $channel] = $this->setupMerchant('filtered');
        $this->inbound($channel, 'Group', changes: ['chat_jid' => '1234@g.us']);
        $this->inbound($channel, 'Outbound echo', changes: ['direction' => 'outbound']);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_staff_reply_and_bot_reply_are_recorded_once(): void
    {
        [$owner, $merchant, $channel] = $this->setupMerchant('replies');
        $channel->update(['menu_enabled' => true]);
        $this->inbound($channel, 'MENU');
        $conversation = Conversation::firstOrFail();
        $this->assertDatabaseCount('conversation_messages', 2);
        $this->assertDatabaseCount('outbound_messages', 1);
        $this->assertSame(1, $conversation->fresh()->unread_count);

        $this->actingAs($owner)->post(route('merchant.inbox.reply', [$merchant, $conversation]), [
            'body' => 'Happy to help!',
        ])->assertRedirect();
        $this->assertDatabaseCount('conversation_messages', 3);
        $this->assertDatabaseCount('outbound_messages', 2);
        $out = $conversation->messages()->where('direction', 'outbound')->latest('id')->firstOrFail();
        $this->assertSame('Happy to help!', $out->body);
        $this->assertNotNull($out->outbound_message_id);
        $this->assertSame('accepted', OutboundMessage::latest('id')->firstOrFail()->status);
    }

    public function test_mark_read_and_manager_assignment_and_close(): void
    {
        [$owner, $merchant, $channel] = $this->setupMerchant('assign');
        $staff = User::factory()->create();
        $merchant->users()->attach($staff->id, ['role' => 'staff']);
        $this->inbound($channel, 'Hello staff');
        $conversation = Conversation::firstOrFail();

        $this->actingAs($staff)->post(route('merchant.inbox.assign', [$merchant, $conversation]), [
            'assigned_to' => $staff->id,
        ])->assertForbidden();
        $this->actingAs($owner)->post(route('merchant.inbox.assign', [$merchant, $conversation]), [
            'assigned_to' => $staff->id,
        ])->assertRedirect();
        $this->assertSame($staff->id, $conversation->fresh()->assigned_to);
        $this->actingAs($staff)->post(route('merchant.inbox.read', [$merchant, $conversation]))
            ->assertRedirect();
        $this->assertSame(0, $conversation->fresh()->unread_count);
        $this->actingAs($owner)->post(route('merchant.inbox.status', [$merchant, $conversation]), [
            'status' => 'closed',
        ])->assertRedirect();
        $this->assertSame('closed', $conversation->fresh()->status);
    }

    public function test_foreign_staff_cannot_be_assigned_and_other_staff_cannot_reply_to_assigned_thread(): void
    {
        [$owner, $merchant, $channel] = $this->setupMerchant('assignment');
        $staff = User::factory()->create();
        $otherStaff = User::factory()->create();
        $foreign = User::factory()->create();
        $merchant->users()->attach($staff->id, ['role' => 'staff']);
        $merchant->users()->attach($otherStaff->id, ['role' => 'staff']);
        $this->inbound($channel, 'Can you help?');
        $conversation = Conversation::firstOrFail();

        $this->actingAs($owner)->post(route('merchant.inbox.assign', [$merchant, $conversation]), [
            'assigned_to' => $foreign->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->actingAs($owner)->post(route('merchant.inbox.assign', [$merchant, $conversation]), [
            'assigned_to' => $staff->id,
        ])->assertRedirect();

        $this->actingAs($otherStaff)->post(route('merchant.inbox.reply', [$merchant, $conversation]), [
            'body' => 'Trying to access another assignment',
        ])->assertForbidden();
        $this->actingAs($staff)->post(route('merchant.inbox.reply', [$merchant, $conversation]), [
            'body' => 'This is the assigned agent',
        ])->assertRedirect();
        $this->assertDatabaseCount('outbound_messages', 1);
    }

    public function test_customer_profile_notes_and_orders_stay_within_tenant(): void
    {
        [$owner, $merchant, $channel] = $this->setupMerchant('clients');
        [$otherOwner, $other] = $this->setupMerchant('another-client');
        $this->inbound($channel, 'Customer note');
        $customer = Customer::firstOrFail();
        $merchant->orders()->create([
            'checkout_key' => (string) Str::uuid(),
            'customer_phone' => $customer->phone,
            'customer_name' => 'Client', 'status' => 'pending',
            'payment_status' => 'unpaid', 'currency' => 'AFN',
            'subtotal_minor' => 12000, 'delivery_minor' => 0, 'total_minor' => 12000,
        ]);

        $this->actingAs($owner)->put(route('merchant.customers.update', [$merchant, $customer]), [
            'name' => 'Client One',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('merchant.customers.notes', [$merchant, $customer]), [
            'body' => 'Allergic to peanuts - private staff note.',
        ])->assertRedirect();
        $note = CustomerNote::firstOrFail();
        $this->assertStringNotContainsString('Allergic', $note->getRawOriginal('body'));
        $this->assertDatabaseCount('outbound_messages', 0);
        $this->actingAs($owner)->get(route('merchant.customers.show', [$merchant, $customer]))
            ->assertOk()->assertSee('Client One')->assertSee('Order #1')->assertSee('Allergic to peanuts');

        $this->actingAs($otherOwner)->get(route('merchant.customers.show', [$other, $customer]))
            ->assertNotFound();
        $this->actingAs($otherOwner)->post(route('merchant.customers.notes', [$other, $customer]), [
            'body' => 'Unauthorized',
        ])->assertNotFound();
        $this->assertDatabaseCount('customer_notes', 1);
    }

    public function test_disabled_messaging_prevents_staff_send(): void
    {
        [$owner, $merchant, $channel] = $this->setupMerchant('off');
        $this->inbound($channel, 'Hello');
        $conversation = Conversation::firstOrFail();
        config(['commerce.messaging_driver' => 'disabled']);
        $this->actingAs($owner)->post(route('merchant.inbox.reply', [$merchant, $conversation]), [
            'body' => 'Should never queue',
        ])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('outbound_messages', 0);
    }

    public function test_cross_merchant_conversation_access_is_forbidden(): void
    {
        [, $merchant, $channel] = $this->setupMerchant('primary');
        [$foreignOwner, $foreign] = $this->setupMerchant('foreign');
        $this->inbound($channel, 'Secret message');
        $conversation = Conversation::firstOrFail();
        $this->actingAs($foreignOwner)->get(route('merchant.inbox.index', $merchant))
            ->assertForbidden();
        $this->actingAs($foreignOwner)->get(route('merchant.inbox.show', [$foreign, $conversation]))
            ->assertNotFound();
        $this->actingAs($foreignOwner)->post(route('merchant.inbox.read', [$foreign, $conversation]))
            ->assertNotFound();
        $this->actingAs($foreignOwner)->post(route('merchant.inbox.reply', [$foreign, $conversation]), [
            'body' => 'Foreign reply',
        ])->assertNotFound();
        $this->assertSame(1, $conversation->fresh()->unread_count);
    }
}
