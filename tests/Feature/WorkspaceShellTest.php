<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\PlatformAdministrator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(string $slug): Merchant
    {
        return Merchant::create([
            'name' => ucfirst($slug).' Shop',
            'slug' => $slug,
            'business_type' => 'retail',
            'currency' => 'AFN',
            'timezone' => 'Asia/Kabul',
            'status' => 'active',
        ]);
    }

    public function test_platform_administrator_sees_the_platform_workspace_navigation(): void
    {
        $admin = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $admin->id]);
        $response = $this->actingAs($admin)->get(route('platform.index'));

        $response->assertOk()
            ->assertSee('workspace-mode')
            ->assertSee('Platform administration')
            ->assertSee('Platform settings')
            ->assertSee('Audit trail')
            ->assertSee('data-sidebar-open', false)
            ->assertSee('data-theme-toggle', false)
            ->assertDontSee('href="#ws-not-a-real-route"', false);
    }

    public function test_merchant_owner_gets_existing_commerce_links_and_no_platform_console(): void
    {
        $owner = User::factory()->create();
        $merchant = $this->merchant('owner-store');
        $merchant->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)->get(route('merchant.products.index', $merchant))
            ->assertOk()
            ->assertSee('WhatsApp channels')
            ->assertSee('Team inbox')
            ->assertSee('Subscription &amp; usage', false)
            ->assertSee(route('merchant.orders.index', $merchant), false)
            ->assertDontSee('Platform settings');
    }

    public function test_read_only_staff_cannot_see_management_actions_in_navigation(): void
    {
        $staff = User::factory()->create();
        $merchant = $this->merchant('staff-store');
        $merchant->users()->attach($staff->id, ['role' => 'agent']);

        $this->actingAs($staff)->get(route('merchant.products.index', $merchant))
            ->assertOk()
            ->assertSee('Team inbox')
            ->assertDontSee('WhatsApp channels')
            ->assertDontSee('Payment settings')
            ->assertDontSee('>Automation<', false);
    }

    public function test_guest_login_keeps_public_header_without_admin_navigation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in')
            ->assertDontSee('workspace-mode')
            ->assertDontSee('workspace-sidebar');
    }

    public function test_cross_tenant_access_is_still_forbidden(): void
    {
        $staff = User::factory()->create();
        $own = $this->merchant('own');
        $other = $this->merchant('other');
        $own->users()->attach($staff->id, ['role' => 'owner']);

        $this->actingAs($staff)->get(route('merchant.products.index', $other))->assertForbidden();
    }
}
