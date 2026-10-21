<?php

namespace Tests\Feature;

use App\Models\InboundEvent;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\OutboundMessage;
use App\Models\PlatformAdministrator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $user->id]);

        return $user;
    }

    private function merchant(string $slug): Merchant
    {
        return Merchant::create([
            'name' => 'Store '.$slug,
            'slug' => $slug,
            'business_type' => 'retail',
            'currency' => 'AFN',
            'timezone' => 'Asia/Kabul',
            'status' => 'active',
        ]);
    }

    private function channel(Merchant $merchant): MessagingChannel
    {
        return $merchant->messagingChannels()->create([
            'provider' => 'relaywa',
            'display_name' => 'Business line',
            'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(),
            'credentials' => ['api_key' => 'SECRET_CHANNEL_API_TOKEN'],
            'status' => 'connected',
        ]);
    }

    public function test_only_platform_administrators_can_open_operations_pages(): void
    {
        $owner = User::factory()->create();
        $merchant = $this->merchant('limited');
        $merchant->users()->attach($owner->id, ['role' => 'owner']);

        foreach (['platform.channels.index', 'platform.messages.index', 'platform.health.index'] as $route) {
            auth()->logout();
            $this->get(route($route))->assertRedirect(route('login'));
            $this->actingAs($owner)->get(route($route))->assertForbidden();
        }

        $admin = $this->admin();
        foreach (['platform.channels.index', 'platform.messages.index', 'platform.health.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_admin_can_filter_channels_without_exposing_connection_credentials(): void
    {
        $admin = $this->admin();
        $first = $this->merchant('first-store');
        $other = $this->merchant('other-store');
        $this->channel($first);
        $this->channel($other);

        $this->actingAs($admin)->get(route('platform.channels.index', ['q' => 'first-store', 'status' => 'connected']))
            ->assertOk()
            ->assertSee('Store first-store')
            ->assertDontSee('Store other-store')
            ->assertDontSee('SECRET_CHANNEL_API_TOKEN')
            ->assertDontSee('external_account_id');

        $this->actingAs($admin)->get(route('platform.channels.index', ['status' => '<script>']))
            ->assertSessionHasErrors('status');
    }

    public function test_message_monitoring_never_displays_recipients_bodies_or_provider_ids(): void
    {
        $admin = $this->admin();
        $merchant = $this->merchant('redacted');
        $channel = $this->channel($merchant);

        OutboundMessage::create([
            'merchant_id' => $merchant->id,
            'messaging_channel_id' => $channel->id,
            'recipient' => '93700123456',
            'body' => 'PRIVATE CUSTOMER MESSAGE',
            'idempotency_key' => 'private-send-one',
            'provider_message_id' => 'SECRET_EXTERNAL_PROVIDER_ID',
            'status' => 'failed',
            'attempts' => 2,
        ]);
        InboundEvent::create([
            'merchant_id' => $merchant->id,
            'messaging_channel_id' => $channel->id,
            'provider_event_id' => 'confidential-event-1',
            'payload' => ['text' => 'CONFIDENTIAL WEBHOOK PAYLOAD'],
        ]);

        $this->actingAs($admin)->get(route('platform.messages.index', ['status' => 'failed', 'days' => '30']))
            ->assertOk()
            ->assertSee('Store redacted')
            ->assertSee('Failed')
            ->assertDontSee('93700123456')
            ->assertDontSee('PRIVATE CUSTOMER MESSAGE')
            ->assertDontSee('SECRET_EXTERNAL_PROVIDER_ID')
            ->assertDontSee('CONFIDENTIAL WEBHOOK PAYLOAD')
            ->assertDontSee('SECRET_CHANNEL_API_TOKEN');

        $this->actingAs($admin)->get(route('platform.messages.index', ['status' => 'bogus']))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)->get(route('platform.health.index'))
            ->assertOk()->assertSee('Unprocessed inbound')
            ->assertDontSee('CONFIDENTIAL WEBHOOK PAYLOAD');
    }

    public function test_subscriptions_support_plan_search_and_expiry_filters(): void
    {
        $admin = $this->admin();
        $near = $this->merchant('near-expiry');
        $far = $this->merchant('far-expiry');
        $near->subscription()->update(['period_end' => now()->addDays(3)]);
        $far->subscription()->update(['period_end' => now()->addDays(20)]);

        $this->actingAs($admin)->get(route('platform.subscriptions.index', [
            'plan' => 'trial', 'expiry' => '7',
        ]))->assertOk()->assertSee('Store near-expiry')->assertDontSee('Store far-expiry');

        $this->actingAs($admin)->get(route('platform.subscriptions.index', [
            'q' => 'far-expiry',
        ]))->assertOk()->assertSee('Store far-expiry')->assertDontSee('Store near-expiry');
    }
}
