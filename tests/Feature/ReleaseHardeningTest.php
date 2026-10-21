<?php

namespace Tests\Feature;

use App\Messaging\CommerceOutbox;
use App\Messaging\RelayWaClient;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ReleaseHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function channel(string $slug): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'business_type' => 'retail',
            'currency' => 'AFN',
            'timezone' => 'Asia/Kabul',
            'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa',
            'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(),
            'status' => 'connected',
            'credentials' => ['api_key' => 'rw_session_testing_only'],
        ]);

        return [$merchant, $channel];
    }

    public function test_release_preflight_checks_integrated_schema_without_exposing_secrets(): void
    {
        $result = Artisan::call('commerce:release:check');
        $output = Artisan::output();

        $this->assertSame(0, $result);
        $this->assertStringContainsString('outbound_messages', $output);
        $this->assertStringContainsString('Preflight checks passed', $output);
        $this->assertStringNotContainsString('APP_KEY=', $output);
        $this->assertStringNotContainsString('api_key', $output);
    }

    public function test_production_preflight_rejects_development_configuration(): void
    {
        $result = Artisan::call('commerce:release:check', ['--production' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $result);
        $this->assertStringContainsString('Do not deploy', $output);
        $this->assertStringNotContainsString('base64:AAAAAAAA', $output);
    }

    public function test_stale_send_is_marked_uncertain_once_and_never_requeued(): void
    {
        Queue::fake();
        [$merchant, $channel] = $this->channel('stalled');
        $message = OutboundMessage::create([
            'merchant_id' => $merchant->id,
            'messaging_channel_id' => $channel->id,
            'recipient' => '93700000001',
            'body' => 'One message with unknown transport result.',
            'status' => 'sending',
            'idempotency_key' => 'stale-transport-key',
            'attempts' => 1,
        ]);
        DB::table('outbound_messages')->where('id', $message->id)
            ->update(['updated_at' => now()->subMinutes(20)]);

        $this->artisan('commerce:outbox:mark-uncertain', ['--minutes' => 15])->assertSuccessful();
        $this->artisan('commerce:outbox:mark-uncertain', ['--minutes' => 15])->assertSuccessful();

        $this->assertSame('uncertain', $message->fresh()->status);
        $this->assertSame(1, $message->fresh()->attempts);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'stale_outbox_marked_uncertain']);
        $this->assertSame(1, DB::table('platform_audit_logs')
            ->where('action', 'stale_outbox_marked_uncertain')->count());
        Queue::assertNothingPushed();
    }

    public function test_recent_sending_and_other_statuses_are_never_reclassified(): void
    {
        [$merchant, $channel] = $this->channel('recent');
        $first = OutboundMessage::create([
            'merchant_id' => $merchant->id,
            'messaging_channel_id' => $channel->id,
            'recipient' => '93700000002',
            'body' => 'Recently claimed by a worker',
            'status' => 'sending',
            'idempotency_key' => 'still-in-flight',
        ]);
        $second = OutboundMessage::create([
            'merchant_id' => $merchant->id,
            'messaging_channel_id' => $channel->id,
            'recipient' => '93700000003',
            'body' => 'Already accepted',
            'status' => 'accepted',
            'idempotency_key' => 'already-accepted',
        ]);
        DB::table('outbound_messages')->whereKey($second->id)
            ->update(['updated_at' => now()->subMinutes(120)]);

        $this->artisan('commerce:outbox:mark-uncertain')->assertSuccessful();
        $this->assertSame('sending', $first->fresh()->status);
        $this->assertSame('accepted', $second->fresh()->status);
        $this->assertDatabaseCount('platform_audit_logs', 0);
    }

    public function test_unknown_session_id_cannot_be_injected_into_relaywa_request_path(): void
    {
        [, $channel] = $this->channel('path-safety');
        config([
            'commerce.relaywa_base_url' => 'https://relaywa.example.test/api',
            'commerce.relaywa_allowed_host' => 'relaywa.example.test',
        ]);
        Http::fake();
        $channel->external_account_id = '../admin/secret?x=1';

        try {
            app(RelayWaClient::class)->session($channel);
            $this->fail('Malformed session ID should not be accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('session identifier', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_relaywa_host_allowlist_blocks_unapproved_https_origin(): void
    {
        [, $channel] = $this->channel('egress-safety');
        config([
            'commerce.relaywa_base_url' => 'https://unapproved.example.test/api',
            'commerce.relaywa_allowed_host' => 'relaywa.example.test',
        ]);
        Http::fake();

        try {
            app(RelayWaClient::class)->session($channel);
            $this->fail('Unapproved provider destination should be denied.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('allowlist', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_same_idempotency_key_does_not_consume_additional_monthly_quota(): void
    {
        Queue::fake();
        [$merchant, $channel] = $this->channel('dedupe');
        $merchant->subscription->plan->update(['max_monthly_outbound' => 1]);
        $outbox = app(CommerceOutbox::class);
        $one = $outbox->queueText($merchant, $channel, '93700000004', 'Good morning', 'unique-business-key');
        $same = $outbox->queueText($merchant, $channel, '93700000004', 'Good morning', 'unique-business-key');

        $this->assertSame($one->id, $same->id);
        $this->assertSame(1, $merchant->subscriptionUsages()->firstOrFail()->outbound_count);
        $this->assertDatabaseCount('outbound_messages', 1);
    }
}
