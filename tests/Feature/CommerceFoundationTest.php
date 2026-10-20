<?php

namespace Tests\Feature;

use App\Messaging\Contracts\MessagingTransport;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CommerceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_merchant_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Demo Owner',
            'email' => 'owner@example.test',
            'password' => 'StrongPassword_123',
            'password_confirmation' => 'StrongPassword_123',
            'business_name' => 'Demo Restaurant',
            'slug' => 'demo-restaurant',
            'business_type' => 'restaurant',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect('/m/demo-restaurant/products');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('merchants', ['slug' => 'demo-restaurant']);
        $this->assertDatabaseHas('merchant_user', ['role' => 'owner']);
    }

    public function test_guest_cannot_manage_catalog(): void
    {
        $merchant = Merchant::create(['name' => 'Demo', 'slug' => 'demo', 'business_type' => 'retail', 'currency' => 'USD']);
        $this->get("/m/{$merchant->slug}/products")->assertRedirect('/login');
    }

    public function test_merchant_cannot_view_or_create_products_in_another_tenant(): void
    {
        [$user, $own] = $this->owner('own');
        [, $other] = $this->owner('other');

        $this->actingAs($user)
            ->get("/m/{$other->slug}/products")
            ->assertForbidden();

        $this->actingAs($user)
            ->post("/m/{$other->slug}/products", ['name' => 'Injected', 'price_minor' => 100])
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'Injected']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_cross_tenant_category_id_is_rejected(): void
    {
        [$user, $own] = $this->owner('own');
        [, $other] = $this->owner('other');
        $category = $other->categories()->create(['name' => 'Private', 'slug' => 'private']);

        $this->actingAs($user)
            ->post("/m/{$own->slug}/products", [
                'name' => 'Product', 'category_id' => $category->id, 'price_minor' => 1250, 'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_owner_can_save_product_for_only_their_business(): void
    {
        [$user, $merchant] = $this->owner('bakery');
        $category = $merchant->categories()->create(['name' => 'Bread', 'slug' => 'bread']);

        $this->actingAs($user)->post("/m/{$merchant->slug}/products", [
            'name' => 'Sourdough', 'category_id' => $category->id,
            'price_minor' => 725, 'stock_quantity' => 12, 'is_active' => '1',
        ])->assertRedirect("/m/{$merchant->slug}/products");

        $this->assertDatabaseHas('products', [
            'merchant_id' => $merchant->id, 'category_id' => $category->id,
            'slug' => 'sourdough', 'price_minor' => 725, 'currency' => 'USD',
        ]);
    }

    public function test_messages_cannot_be_sent_before_a_provider_is_configured(): void
    {
        $this->expectException(LogicException::class);
        $this->app->make(MessagingTransport::class)
            ->sendText(new \App\Models\MessagingChannel, '+10000000000', 'hello');
    }

    private function owner(string $slug): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'business_type' => 'retail',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        $merchant->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $merchant];
    }
}
