<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_catalog_but_cannot_modify_it(): void
    {
        $merchant = Merchant::create(['name' => 'Cafe', 'slug' => 'cafe', 'business_type' => 'restaurant', 'currency' => 'USD']);
        $staff = User::factory()->create();
        $merchant->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)->get('/m/cafe/products')->assertOk();
        $this->actingAs($staff)->get('/m/cafe/products/create')->assertForbidden();
        $this->actingAs($staff)->post('/m/cafe/categories', ['name' => 'Drinks'])->assertForbidden();
        $this->actingAs($staff)->post('/m/cafe/products', ['name' => 'Tea', 'price_minor' => 100])->assertForbidden();
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_manager_can_create_categories(): void
    {
        $merchant = Merchant::create(['name' => 'Cafe', 'slug' => 'cafe', 'business_type' => 'restaurant', 'currency' => 'USD']);
        $manager = User::factory()->create();
        $merchant->users()->attach($manager->id, ['role' => 'manager']);

        $this->actingAs($manager)->post('/m/cafe/categories', ['name' => 'Drinks'])->assertRedirect();
        $this->assertDatabaseHas('categories', ['merchant_id' => $merchant->id, 'slug' => 'drinks']);
    }
}
