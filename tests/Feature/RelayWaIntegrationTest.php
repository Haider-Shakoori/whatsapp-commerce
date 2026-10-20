<?php

namespace Tests\Feature;

use App\Jobs\SendCommerceMessage;
use App\Messaging\CommerceOutbox;
use App\Models\InboundEvent;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RelayWaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $slug): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'business_type' => 'restaurant',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $merchant];
    }

    private function channel(Merchant $merchant): MessagingChannel
    {
        return $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Primary',
            'external_account_id' => (string) Str::uuid(), 'public_id' => (string) Str::uuid(),
            'credentials' => ['api_key' => 'rw_session_testing_not_real', 'webhook_secret' => 'a-testing-secret-at-least-20-characters'],
            'status' => 'connected',
        ]);
    }

    private function signedWebhook(MessagingChannel $channel, array $event, bool $correct = true): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $secret = $correct ? $channel->credentials['webhook_secret'] : 'incorrect-signing-secret-not-real';
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]),
            [], [], [],
            ['CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAYWA_TIMESTAMP' => $timestamp,
                'HTTP_X_RELAYWA_SIGNATURE' => $signature], $body);
    }

    public function test_merchant_verifies_session_and_encrypts_credentials(): void
    {
        [$owner, $merchant] = $this->owner('cafe');
        $sessionId = (string) Str::uuid();
        config(['commerce.relaywa_base_url' => 'https://relaywa.example.test/api']);
        Http::fake([
            "https://relaywa.example.test/api/v1/sessions/{$sessionId}" => Http::response(['id' => $sessionId, 'status' => 'connected']),
        ]);

        $this->actingAs($owner)->post(route('merchant.channels.store', $merchant), [
            'display_name' => 'Cafe number', 'session_id' => $sessionId,
            'api_key' => 'rw_session_test_credential',
            'webhook_secret' => 'webhook-secret-copy-from-relaywa-123',
        ])->assertRedirect(route('merchant.channels.index', $merchant));

        $channel = MessagingChannel::firstOrFail();
        $this->assertSame('connected', $channel->status);
        $this->assertSame($merchant->id, $channel->merchant_id);
        $this->assertStringNotContainsString('rw_session_test_credential', $channel->getRawOriginal('credentials'));
        $this->assertStringNotContainsString('webhook-secret-copy', $channel->getRawOriginal('credentials'));
        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer rw_session_test_credential'));
    }

    public function test_merchant_cannot_reuse_session_or_delete_another_merchants_channel(): void
    {
        [$firstUser, $first] = $this->owner('first');
        [$secondUser, $second] = $this->owner('second');
        $channel = $this->channel($first);

        $this->actingAs($secondUser)->post(route('merchant.channels.store', $second), [
            'display_name' => 'Hijacked', 'session_id' => $channel->external_account_id,
            'api_key' => 'rw_session_invalid', 'webhook_secret' => 'secret-at-least-twenty-characters',
        ])->assertSessionHasErrors('session_id');

        $this->actingAs($secondUser)->delete(route('merchant.channels.destroy', [$second, $channel]))->assertNotFound();
        $this->actingAs($firstUser)->get(route('merchant.channels.index', $second))->assertForbidden();
        $this->assertDatabaseCount('messaging_channels', 1);
    }

    public function test_webhook_signature_session_binding_and_deduplication(): void
    {
        [, $merchant] = $this->owner('signed');
        $channel = $this->channel($merchant);
        $event = ['id' => '120035', 'type' => 'message.received', 'sessionId' => $channel->external_account_id,
            'createdAt' => now()->toIso8601String(), 'data' => ['senderPhone' => '93700000000']];

        $this->signedWebhook($channel, $event, false)->assertUnauthorized();
        $this->signedWebhook($channel, [...$event, 'sessionId' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('inbound_events', 0);

        $this->signedWebhook($channel, $event)->assertOk()->assertJson(['received' => true]);
        $this->signedWebhook($channel, $event)->assertOk();
        $this->assertDatabaseCount('inbound_events', 1);
        $inbox = InboundEvent::firstOrFail();
        $this->assertSame($merchant->id, $inbox->merchant_id);
        $this->assertNotNull($inbox->processed_at);
        $this->assertStringNotContainsString('93700000000', $inbox->getRawOriginal('payload'));
    }

    public function test_expired_signature_is_rejected(): void
    {
        [, $merchant] = $this->owner('replay');
        $channel = $this->channel($merchant);
        $body = json_encode(['id' => '10', 'type' => 'message.received', 'sessionId' => $channel->external_account_id, 'data' => []]);
        $stamp = (string) (time() - 900);
        $sig = 'sha256='.hash_hmac('sha256', $stamp.'.'.$body, $channel->credentials['webhook_secret']);

        $this->call('POST', route('api.relaywa.webhook', ['publicId' => $channel->public_id]), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RELAYWA_TIMESTAMP' => $stamp,
                'HTTP_X_RELAYWA_SIGNATURE' => $sig], $body)->assertUnauthorized();
        $this->assertDatabaseCount('inbound_events', 0);
    }

    public function test_outbox_idempotency_and_encrypted_message_body(): void
    {
        [, $merchant] = $this->owner('outbox');
        $channel = $this->channel($merchant);
        config(['commerce.messaging_driver' => 'relaywa', 'commerce.relaywa_base_url' => 'https://relaywa.example.test/api']);
        Http::fake([
            "https://relaywa.example.test/api/v1/sessions/{$channel->external_account_id}/messages/text" => Http::response(['id' => 'relaywa-msg-123', 'status' => 'sent']),
        ]);

        $outbox = app(CommerceOutbox::class);
        $first = $outbox->queueText($merchant, $channel, '+93700000000', 'Your order is ready', 'order-8-ready');
        $second = $outbox->queueText($merchant, $channel, '+93700000000', 'Your order is ready', 'order-8-ready');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('outbound_messages', 1);
        $this->assertDatabaseHas('outbound_messages', [
            'id' => $first->id, 'status' => 'accepted', 'provider_message_id' => 'relaywa-msg-123',
        ]);
        $this->assertStringNotContainsString('Your order is ready', OutboundMessage::firstOrFail()->getRawOriginal('body'));
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request->data()['clientMessageId'] === 'order-8-ready');

        $this->expectException(RuntimeException::class);
        $outbox->queueText($merchant, $channel, '+93700000000', 'different message', 'order-8-ready');
    }

    public function test_default_driver_never_sends(): void
    {
        [, $merchant] = $this->owner('disabled');
        $channel = $this->channel($merchant);
        config(['commerce.messaging_driver' => 'disabled']);
        Http::fake();

        $message = app(CommerceOutbox::class)->queueText($merchant, $channel, '+93700000000', 'Hello', 'test-disabled');
        $this->assertDatabaseHas('outbound_messages', ['id' => $message->id, 'status' => 'failed']);
        Http::assertNothingSent();
    }

    public function test_server_failure_is_uncertain_and_never_automatically_replayed(): void
    {
        [, $merchant] = $this->owner('timeout');
        $channel = $this->channel($merchant);
        config(['commerce.messaging_driver' => 'relaywa', 'commerce.relaywa_base_url' => 'https://relaywa.example.test/api']);
        Http::fake([
            "https://relaywa.example.test/api/v1/sessions/{$channel->external_account_id}/messages/text" => Http::response([], 502),
        ]);

        $message = app(CommerceOutbox::class)->queueText($merchant, $channel, '+93700000000', 'Hello', 'test-uncertain');
        $this->assertDatabaseHas('outbound_messages', ['id' => $message->id, 'status' => 'uncertain', 'attempts' => 1]);
        (new SendCommerceMessage($message->id))->handle(app(\App\Messaging\Contracts\MessagingTransport::class));
        Http::assertSentCount(1);
    }

    public function test_unverified_api_key_is_not_stored(): void
    {
        [$owner, $merchant] = $this->owner('invalid');
        config(['commerce.relaywa_base_url' => 'https://relaywa.example.test/api']);
        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

        $this->actingAs($owner)->post(route('merchant.channels.store', $merchant), [
            'display_name' => 'Bad', 'session_id' => (string) Str::uuid(),
            'api_key' => 'rw_session_invalid', 'webhook_secret' => 'secret-at-least-twenty-characters',
        ])->assertSessionHasErrors('api_key');

        $this->assertDatabaseCount('messaging_channels', 0);
    }
}
