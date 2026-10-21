<?php

namespace Tests\Feature;

use App\Messaging\CommerceOutbox;
use App\Models\Merchant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Saas\Exceptions\SubscriptionLimitException;
use App\Saas\MerchantEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaasBillingTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $slug = 'demo'): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => 'Sample Merchant', 'slug' => $slug, 'business_type' => 'retail',
            'currency' => 'AFN', 'timezone' => 'Asia/Kabul', 'status' => 'active',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);

        return [$owner, $merchant];
    }

    public function test_new_merchants_receive_one_bounded_trial_without_billing(): void
    {
        [$owner, $merchant] = $this->shop();
        $subscription = $merchant->subscription()->with('plan')->firstOrFail();

        $this->assertSame('trial', $subscription->plan->code);
        $this->assertSame('trialing', $subscription->status);
        $this->assertTrue($subscription->isEntitled());
        $this->assertSame(14, (int) $subscription->period_start->diffInDays($subscription->period_end));
        $this->assertFalse($subscription->auto_renew);
        $this->assertDatabaseCount('subscription_invoices', 0);
        $this->assertDatabaseHas('subscription_events', [
            'merchant_id' => $merchant->id, 'event' => 'trial_started',
        ]);
        $this->actingAs($owner)->get(route('merchant.subscription.index', $merchant))
            ->assertOk()->assertSee('Evaluation')->assertSee('Automatic renewal: disabled');
    }

    public function test_catalog_limit_is_enforced_during_product_creation(): void
    {
        [$owner, $merchant] = $this->shop();
        $merchant->subscription->plan->update(['max_products' => 1]);
        $url = route('merchant.products.store', $merchant);
        $this->actingAs($owner)->post($url, [
            'name' => 'First Product', 'price_minor' => 10000,
        ])->assertRedirect();
        $this->actingAs($owner)->post($url, [
            'name' => 'Second Product', 'price_minor' => 10000,
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_channel_member_and_feature_limits_are_tenant_scoped(): void
    {
        [, $merchant] = $this->shop('limits');
        [, $other] = $this->shop('other');
        $service = app(MerchantEntitlements::class);
        $merchant->subscription->plan->update(['max_channels' => 1, 'max_team_members' => 1]);
        $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(), 'status' => 'connected',
        ]);
        $this->expectException(SubscriptionLimitException::class);
        DB::transaction(fn () => $service->assertCapacity($merchant, 'channels'));
        $this->assertSame(0, $other->messagingChannels()->count());
    }

    public function test_outbound_usage_counts_new_idempotency_keys_only(): void
    {
        Queue::fake();
        [, $merchant] = $this->shop('meter');
        $merchant->subscription->plan->update(['max_monthly_outbound' => 1]);
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(), 'status' => 'connected',
        ]);
        $outbox = app(CommerceOutbox::class);
        $one = $outbox->queueText($merchant, $channel, '93700111222', 'Hello', 'quota-id-1');
        $same = $outbox->queueText($merchant, $channel, '93700111222', 'Hello', 'quota-id-1');
        $this->assertSame($one->id, $same->id);
        $this->assertSame(1, $merchant->subscriptionUsages()->firstOrFail()->outbound_count);
        try {
            $outbox->queueText($merchant, $channel, '93700111222', 'Another message', 'quota-id-2');
            $this->fail('Exceeded quota was not rejected.');
        } catch (SubscriptionLimitException $e) {
            $this->assertStringContainsString('allowance', $e->getMessage());
        }
        $this->assertDatabaseCount('outbound_messages', 1);
        $this->assertSame(1, $merchant->subscriptionUsages()->firstOrFail()->outbound_count);
    }

    public function test_expired_subscriptions_block_sends_but_allow_data_access(): void
    {
        Queue::fake();
        [$owner, $merchant] = $this->shop('expired');
        $channel = $merchant->messagingChannels()->create([
            'provider' => 'relaywa', 'external_account_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(), 'status' => 'connected',
        ]);
        $merchant->subscription->update(['period_end' => now()->subMinute()]);
        $this->assertFalse($merchant->subscription->fresh()->isEntitled());
        $this->actingAs($owner)->get(route('merchant.subscription.index', $merchant))
            ->assertOk()->assertSee('Inactive / expired');

        $this->expectException(SubscriptionLimitException::class);
        app(CommerceOutbox::class)->queueText($merchant, $channel, '93700111222', 'Blocked', 'expired-key');
    }

    public function test_plan_request_requires_manager_and_does_not_activate_billing(): void
    {
        [$owner, $merchant] = $this->shop('requesting');
        $staff = User::factory()->create();
        $merchant->users()->attach($staff->id, ['role' => 'staff']);
        $target = route('merchant.subscription.request', $merchant);

        $this->actingAs($staff)->post($target, ['plan_code' => 'growth'])->assertForbidden();
        $this->actingAs($owner)->post($target, ['plan_code' => 'growth'])->assertRedirect();
        $this->actingAs($owner)->post($target, ['plan_code' => 'growth'])
            ->assertSessionHasErrors('plan_code');
        $this->assertSame('trial', $merchant->subscription->fresh()->plan->code);
        $this->assertSame(1, $merchant->subscriptionEvents()->where('event', 'plan_change_requested')->count());
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_operator_grants_extension_without_creating_a_paid_invoice(): void
    {
        [, $merchant] = $this->shop('operator');
        $this->artisan('commerce:subscription:grant', [
            'merchant' => $merchant->slug, 'plan' => 'growth', '--months' => 2,
        ])->assertSuccessful();
        $this->assertSame('trial', $merchant->subscription->fresh()->plan->code);

        $this->artisan('commerce:subscription:grant', [
            'merchant' => $merchant->slug, 'plan' => 'growth',
            '--months' => 2, '--confirm' => true,
        ])->assertSuccessful();
        $sub = $merchant->subscription->fresh();
        $this->assertSame('growth', $sub->plan->code);
        $this->assertSame('active', $sub->status);
        $this->assertFalse($sub->auto_renew);
        $this->assertDatabaseHas('subscription_events', [
            'merchant_id' => $merchant->id, 'event' => 'operator_grant',
        ]);
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_expiry_scheduler_marks_status_once_without_charging(): void
    {
        [, $merchant] = $this->shop('scheduler');
        $merchant->subscription->update(['period_end' => now()->subHour()]);
        $this->artisan('commerce:subscription:expire')->assertSuccessful();
        $this->artisan('commerce:subscription:expire')->assertSuccessful();
        $this->assertSame('expired', $merchant->subscription->fresh()->status);
        $this->assertSame(1, $merchant->subscriptionEvents()->where('event', 'expired')->count());
        $this->assertDatabaseCount('subscription_invoices', 0);
    }

    public function test_starter_excludes_automation_and_ai_drafting(): void
    {
        [, $merchant] = $this->shop('features');
        $starter = SubscriptionPlan::where('code', 'starter')->firstOrFail();
        $merchant->subscription->update([
            'subscription_plan_id' => $starter->id,
            'status' => 'active', 'period_end' => now()->addMonth(),
        ]);
        $service = app(MerchantEntitlements::class);
        foreach (['automation', 'ai_drafts'] as $feature) {
            try {
                $service->assertFeature($merchant, $feature);
                $this->fail('Expected feature to be disabled.');
            } catch (SubscriptionLimitException $e) {
                $this->assertStringContainsString('does not include', $e->getMessage());
            }
        }
    }
}
