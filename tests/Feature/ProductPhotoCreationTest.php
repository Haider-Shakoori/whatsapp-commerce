<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPhotoCreationTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(string $slug): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'business_type' => 'retail',
            'currency' => 'AFN',
            'timezone' => 'Asia/Kabul',
        ]);
        $merchant->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $merchant];
    }

    public function test_add_product_screen_contains_cover_photo_uploader(): void
    {
        [$owner, $merchant] = $this->merchant('photo-picker');
        $this->actingAs($owner)->get(route('merchant.products.create', $merchant))
            ->assertOk()->assertSee('Product image')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="image"', false);
    }

    public function test_owner_can_create_a_product_and_cover_image_together(): void
    {
        Storage::fake('public');
        [$owner, $merchant] = $this->merchant('photo-shop');
        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Photo Pizza',
            'price' => '129.00',
            'image' => UploadedFile::fake()->image('pizza.jpg'),
        ])->assertRedirect(route('merchant.products.index', $merchant));
        $product = $merchant->products()->where('name', 'Photo Pizza')->firstOrFail();
        $image = $product->images()->firstOrFail();
        $this->assertSame($merchant->id, $image->merchant_id);
        $this->assertSame('pizza.jpg', $image->original_name);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_image_is_optional_for_backwards_compatible_product_creation(): void
    {
        [$owner, $merchant] = $this->merchant('plain-shop');
        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Plain Product', 'price' => '30.00',
        ])->assertRedirect(route('merchant.products.index', $merchant));
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_invalid_and_oversized_images_are_rejected_without_product_creation(): void
    {
        Storage::fake('public');
        [$owner, $merchant] = $this->merchant('validate-photo');

        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Bad Upload', 'price' => '20.00',
            'image' => UploadedFile::fake()->create('document.txt', 12, 'text/plain'),
        ])->assertSessionHasErrors('image');

        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Too Large', 'price' => '20.00',
            'image' => UploadedFile::fake()->image('giant.jpg')->size(5000),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_cross_tenant_creation_cannot_upload_photo(): void
    {
        Storage::fake('public');
        [$owner] = $this->merchant('my-shop');
        [, $other] = $this->merchant('other-shop');

        $this->actingAs($owner)->post(route('merchant.products.store', $other), [
            'name' => 'Should Not Create', 'price' => '20.00',
            'image' => UploadedFile::fake()->image('secret.png'),
        ])->assertForbidden();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_images', 0);
    }
}
