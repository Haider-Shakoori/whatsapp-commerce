<?php

namespace Tests\Feature;

use App\Messaging\CommerceOutbox;
use App\Models\Merchant;
use App\Models\OutboundMessage;
use App\Models\PlatformAdministrator;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Saas\Exceptions\SubscriptionLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $slug): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Merchant '.$slug, 'slug' => $slug, 'business_type' => 'retail',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $merchant];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $user->id]);

        return $user;
    }

    private function channel(Merchant $merchant)
    {
        return $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'display_name' => 'Account',
            'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(), 'status' => 'connected',
        ]);
    }

    public function test_guests_and_regular_merchants_cannot_access_platform_admin(): void
    {
        [$owner, $merchant] = $this->shop('first');
        $this->get(route('platform.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('platform.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.merchants.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.merchants.show', $merchant))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.subscriptions.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.settings.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.audit.index'))->assertForbidden();
    }

    public function test_server_operator_can_explicitly_grant_and_revoke_admin_role(): void
    {
        $user = User::factory()->create();
        $this->artisan('commerce:platform:admin', ['email' => $user->email, 'action' => 'grant'])
            ->assertSuccessful();
        $this->assertDatabaseCount('platform_administrators', 0);
        $this->artisan('commerce:platform:admin', [
            'email' => $user->email, 'action' => 'grant', '--confirm' => true,
        ])->assertSuccessful();
        $this->assertDatabaseHas('platform_administrators', ['user_id' => $user->id]);
        $this->actingAs($user)->get(route('platform.index'))->assertOk();
        $this->artisan('commerce:platform:admin', [
            'email' => $user->email, 'action' => 'revoke', '--confirm' => true,
        ])->assertSuccessful();
        $this->actingAs($user)->get(route('platform.index'))->assertForbidden();
        $this->assertDatabaseCount('platform_audit_logs', 2);
    }

    public function test_admin_dashboard_and_search_show_aggregates_not_customer_chat_content(): void
    {
        $admin = $this->admin();
        [, $merchant] = $this->shop('market-one');
        $channel = $this->channel($merchant);
        $merchant->customers()->create(['phone' => '93700000011']);
        $message = OutboundMessage::create([
            'merchant_id' => $merchant->id, 'messaging_channel_id' => $channel->id,
            'idempotency_key' => 'secret-message', 'recipient' => '93700000011',
            'body' => 'CONFIDENTIAL INBOX MESSAGE',
            'status' => 'failed',
        ]);
        $this->actingAs($admin)->get(route('platform.index'))
            ->assertOk()->assertSee('Platform administration')
            ->assertSee('Failed / uncertain outgoing')
            ->assertDontSee('CONFIDENTIAL INBOX MESSAGE')
            ->assertDontSee('93700000011');
        $this->actingAs($admin)->get(route('platform.merchants.index', ['q' => 'market-one']))
            ->assertOk()->assertSee($merchant->name);
        $this->actingAs($admin)->get(route('platform.merchants.show', $merchant))
            ->assertOk()->assertDontSee($message->body)->assertSee('Outbound state');
    }

    public function test_merchant_suspension_is_audited_and_blocks_outgoing_without_deleting_data(): void
    {
        Queue::fake();
        $admin = $this->admin();
        [$owner, $merchant] = $this->shop('paused-merchant');
        $channel = $this->channel($merchant);
        $url = route('platform.merchants.status', $merchant);

        $this->actingAs($owner)->post($url, [
            'status' => 'suspended', 'reason' => 'fraud response review',
            'confirmation' => 'SUSPEND', 'current_password' => 'password-for-testing',
        ])->assertForbidden();
        $this->actingAs($admin)->post($url, [
            'status' => 'suspended', 'reason' => 'fraud response review',
            'confirmation' => 'SUSPEND', 'current_password' => 'incorrect',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->post($url, [
            'status' => 'suspended', 'reason' => 'fraud response review',
            'confirmation' => 'SUSPEND', 'current_password' => 'password-for-testing',
        ])->assertRedirect();
        $this->assertSame('suspended', $merchant->fresh()->status);
        $this->assertDatabaseHas('platform_audit_logs', [
            'merchant_id' => $merchant->id, 'actor_user_id' => $admin->id, 'action' => 'merchant_suspended',
        ]);
        $this->actingAs($owner)->get(route('merchant.products.index', $merchant))->assertForbidden();
        try {
            app(CommerceOutbox::class)->queueText($merchant, $channel, '93700000022', 'Hello', 'suspended-test');
            $this->fail('Suspended merchant queued an outgoing message');
        } catch (SubscriptionLimitException $e) {
            $this->assertStringContainsString('subscription', $e->getMessage());
        }
        $this->assertDatabaseCount('outbound_messages', 0);
        $this->assertDatabaseCount('merchants', 1);

        $this->actingAs($admin)->post($url, [
            'status' => 'active', 'reason' => 'review completed and approved',
            'confirmation' => 'REACTIVATE', 'current_password' => 'password-for-testing',
        ])->assertRedirect();
        $this->assertSame('active', $merchant->fresh()->status);
        $this->actingAs($owner)->get(route('merchant.products.index', $merchant))->assertOk();
    }

    public function test_global_pauses_block_registration_and_outbound_and_preserve_existing_users(): void
    {
        Queue::fake();
        $admin = $this->admin();
        [$owner, $merchant] = $this->shop('paused-all');
        $channel = $this->channel($merchant);
        $this->actingAs($admin)->put(route('platform.settings.update'), [
            'registration_paused' => '1',
            'outbound_paused' => '1',
            'reason' => 'scheduled maintenance safety testing',
            'confirmation' => 'APPLY', 'current_password' => 'password-for-testing',
        ])->assertRedirect();
        $this->assertTrue(PlatformSetting::enabled('registration_paused'));
        $this->assertTrue(PlatformSetting::enabled('outbound_paused'));
        $this->assertDatabaseCount('platform_audit_logs', 2);

        $this->get(route('register'))->assertServiceUnavailable();
        $this->post(route('register.store'))->assertServiceUnavailable();
        $this->actingAs($owner)->get(route('merchant.products.index', $merchant))->assertOk();
        $this->expectException(SubscriptionLimitException::class);
        app(CommerceOutbox::class)->queueText($merchant, $channel, '93700000022', 'Hello', 'global-pause');
    }

    public function test_only_platform_admin_can_change_global_safety_controls(): void
    {
        [$owner] = $this->shop('no-access');
        $this->actingAs($owner)->put(route('platform.settings.update'), [
            'registration_paused' => '1', 'outbound_paused' => '1',
            'reason' => 'unauthorized attempt to pause business',
            'confirmation' => 'APPLY', 'current_password' => 'password-for-testing',
        ])->assertForbidden();
        $this->assertFalse(PlatformSetting::enabled('outbound_paused'));
        $this->assertFalse(PlatformSetting::enabled('registration_paused'));
    }

    public function test_platform_subscription_grant_requires_password_confirmation_and_never_records_payment(): void
    {
        $admin = $this->admin();
        [$owner, $merchant] = $this->shop('grant-store');
        $url = route('platform.subscriptions.grant', $merchant);
        $data = [
            'plan_code' => 'growth', 'months' => 2,
            'reason' => 'approved manual evaluation extension',
            'confirmation' => 'GRANT', 'current_password' => 'password-for-testing',
        ];
        $this->actingAs($owner)->post($url, $data)->assertForbidden();
        $this->actingAs($admin)->post($url, [...$data, 'confirmation' => 'YES'])
            ->assertSessionHasErrors('confirmation');
        $this->actingAs($admin)->post($url, $data)->assertRedirect();
        $sub = $merchant->subscription->fresh();
        $this->assertSame('growth', $sub->plan->code);
        $this->assertFalse($sub->auto_renew);
        $this->assertDatabaseHas('subscription_events', [
            'merchant_id' => $merchant->id, 'event' => 'admin_entitlement_grant',
        ]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'merchant_id' => $merchant->id, 'action' => 'subscription_granted',
        ]);
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_settings_wrong_confirmation_is_rejected_without_side_effects(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('platform.settings.update'), [
            'registration_paused' => '1', 'outbound_paused' => '1',
            'reason' => 'emergency operational incident',
            'confirmation' => 'WRONG', 'current_password' => 'password-for-testing',
        ])->assertSessionHasErrors('confirmation');
        $this->assertFalse(PlatformSetting::enabled('registration_paused'));
        $this->assertDatabaseCount('platform_audit_logs', 0);
    }

    public function test_platform_admin_has_no_implicit_membership_in_merchants(): void
    {
        $admin = $this->admin();
        [, $merchant] = $this->shop('tenant');
        $this->actingAs($admin)->get(route('platform.index'))->assertOk();
        $this->actingAs($admin)->get(route('merchant.customers.index', $merchant))->assertForbidden();
        $this->actingAs($admin)->get(route('merchant.orders.index', $merchant))->assertForbidden();
        $this->assertSame(0, $admin->merchants()->count());
    }
}
