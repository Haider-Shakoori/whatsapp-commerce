<?php

namespace Tests\Feature;

use App\Models\AutomationFollowup;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantAutomationTest extends TestCase
{
    use RefreshDatabase;

    private array $incoming = [];

    private function shop(string $slug = 'sample'): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Shop', 'slug' => $slug, 'business_type' => 'retail',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Main',
            'external_account_id' => (string) Str::uuid(), 'public_id' => (string) Str::uuid(),
            'status' => 'connected', 'menu_enabled' => false,
            'credentials' => [
                'api_key' => 'rw_session_test',
                'webhook_secret' => 'local-only-secret-for-hmac-test-123',
            ],
        ]);
        $settings = $merchant->automationSetting()->create([
            'enabled' => true, 'keyword_enabled' => true, 'followups_enabled' => true, 'weekly_hours' => [],
        ]);
        config([
            'commerce.messaging_driver' => 'relaywa',
            'commerce.relaywa_base_url' => 'https://relaywa.example.test/api',
        ]);
        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/messages/text')) {
                return Http::response(['id' => (string) Str::uuid()]);
            }
            foreach ($this->incoming as $id => $data) {
                if ($request->method() === 'GET' && str_ends_with($request->url(), '/messages/'.$id)) {
                    return Http::response($data);
                }
            }

            return Http::response(['error' => 'not found'], 404);
        });

        return [$owner, $merchant, $channel, $settings];
    }

    private function say($channel, string $text): void
    {
        $id = (string) Str::uuid();
        $this->incoming[$id] = [
            'id' => $id, 'session_id' => $channel->external_account_id,
            'direction' => 'inbound', 'message_type' => 'text', 'text_body' => $text,
            'sender_phone' => '93700111222', 'chat_jid' => '93700111222@s.whatsapp.net',
        ];
        $body = json_encode([
            'id' => (string) Str::uuid(), 'type' => 'message.received',
            'sessionId' => $channel->external_account_id, 'data' => ['messageId' => $id],
        ], JSON_THROW_ON_ERROR);
        $time = (string) time();
        $sig = 'sha256='.hash_hmac('sha256', $time.'.'.$body, $channel->credentials['webhook_secret']);
        $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]),
            [], [], [], ['CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAYWA_TIMESTAMP' => $time,
                'HTTP_X_RELAYWA_SIGNATURE' => $sig], $body)->assertOk();
    }

    public function test_keyword_reply_and_assignment_and_throttle(): void
    {
        [, $merchant, $channel] = $this->shop();
        $agent = User::factory()->create();
        $merchant->users()->attach($agent->id, ['role' => 'staff']);
        $merchant->automationRules()->create([
            'keyword' => 'delivery', 'reply_text' => 'Delivery is arranged by staff.',
            'assign_to' => $agent->id, 'priority' => 1, 'enabled' => true,
        ]);
        $this->say($channel, 'Is delivery available?');
        $this->assertSame($agent->id, Conversation::firstOrFail()->assigned_to);
        $this->assertDatabaseCount('automation_replies', 1);
        $this->assertSame('Delivery is arranged by staff.', OutboundMessage::firstOrFail()->body);
        $this->say($channel, 'Delivery again?');
        $this->assertDatabaseCount('outbound_messages', 1);
    }

    public function test_stop_start_and_shopping_commands_do_not_trigger_replies(): void
    {
        [, $merchant, $channel] = $this->shop('stop');
        $merchant->automationRules()->create([
            'keyword' => 'help', 'reply_text' => 'An agent will help.', 'priority' => 1, 'enabled' => true,
        ]);
        $this->say($channel, 'STOP');
        $this->assertTrue(Customer::firstOrFail()->automation_opted_out);
        $this->say($channel, 'help');
        $this->assertDatabaseCount('outbound_messages', 0);
        $this->say($channel, 'START');
        $this->assertFalse(Customer::firstOrFail()->automation_opted_out);
        $this->say($channel, 'MENU');
        $this->say($channel, 'help');
        $this->assertDatabaseCount('outbound_messages', 1);
    }

    public function test_followups_need_consent_and_skip_if_customer_replied(): void
    {
        [$owner, $merchant, $channel] = $this->shop('follow');
        $this->say($channel, 'Follow up later please.');
        $conversation = Conversation::firstOrFail();
        $this->actingAs($owner)->post(route('merchant.inbox.followups.store', [$merchant, $conversation]), [
            'body' => 'Update.', 'delay_hours' => 1,
        ])->assertSessionHasErrors('consent_confirmed');
        $this->actingAs($owner)->post(route('merchant.inbox.followups.store', [$merchant, $conversation]), [
            'body' => 'Update.', 'delay_hours' => 1, 'consent_confirmed' => '1',
        ])->assertRedirect();
        $this->say($channel, 'I got the answer.');
        $this->travel(2)->hours();
        $this->artisan('commerce:automation:followups')->assertSuccessful();
        $this->assertSame('skipped', AutomationFollowup::firstOrFail()->status);
        $this->assertDatabaseCount('outbound_messages', 0);
        $this->travelBack();
    }

    public function test_one_time_followup_is_queued_once(): void
    {
        [$owner, $merchant, $channel] = $this->shop('due');
        $this->say($channel, 'Contact later.');
        $conversation = Conversation::firstOrFail();
        $this->actingAs($owner)->post(route('merchant.inbox.followups.store', [$merchant, $conversation]), [
            'body' => 'Requested update.', 'delay_hours' => 1, 'consent_confirmed' => '1',
        ])->assertRedirect();
        $this->travel(2)->hours();
        $this->artisan('commerce:automation:followups')->assertSuccessful();
        $this->artisan('commerce:automation:followups')->assertSuccessful();
        $this->assertDatabaseCount('outbound_messages', 1);
        $this->assertSame('queued', AutomationFollowup::firstOrFail()->status);
        $this->travelBack();
    }
}
