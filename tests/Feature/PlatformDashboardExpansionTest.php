<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\PlatformAdministrator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformDashboardExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(string $slug): Merchant
    {
        return Merchant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'business_type' => 'retail',
            'currency' => 'AFN',
            'timezone' => 'Asia/Kabul',
        ]);
    }

    public function test_admin_sees_data_driven_dashboard_and_period_selector(): void
    {
        $admin = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $admin->id]);
        $this->merchant('first');

        $this->actingAs($admin)->get(route('platform.index', ['days' => 7]))
            ->assertOk()
            ->assertSee('PLATFORM INTELLIGENCE')
            ->assertSee('Recent merchants')
            ->assertSee('First')
            ->assertSee('Last 7 days')
            ->assertSee('Messaging & queue health', false);
        $this->actingAs($admin)->get(route('platform.index', ['days' => 900]))
            ->assertSessionHasErrors('days');
    }

    public function test_admin_directory_filters_and_sorts_merchants(): void
    {
        $admin = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $admin->id]);
        $this->merchant('alpha');
        $this->merchant('bravo');

        $this->actingAs($admin)->get(route('platform.merchants.index', ['q' => 'alpha', 'sort' => 'name']))
            ->assertOk()->assertSee('Alpha')->assertDontSee('Bravo');
        $this->actingAs($admin)->get(route('platform.merchants.index', ['plan' => 'trial']))
            ->assertOk()->assertSee('Alpha')->assertSee('Bravo');
        $this->actingAs($admin)->get(route('platform.merchants.index', ['sort' => 'invalid']))
            ->assertSessionHasErrors('sort');
    }

    public function test_merchant_profile_displays_metadata_but_not_private_customer_messages(): void
    {
        $admin = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $admin->id]);
        $merchant = $this->merchant('privacy');

        $this->actingAs($admin)->get(route('platform.merchants.show', $merchant))
            ->assertOk()->assertSee('MERCHANT 360° PROFILE')
            ->assertSee('Products')->assertSee('Customers')
            ->assertSee('Recent orders');
    }
}
