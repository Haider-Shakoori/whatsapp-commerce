<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvancedCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(string $slug): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'business_type' => 'restaurant',
            'currency' => 'AFN',
            'timezone' => 'Asia/Kabul',
        ]);
        $merchant->users()->attach($owner->id, ['role' => 'owner']);

        return [$owner, $merchant];
    }

    private function product(Merchant $merchant, string $slug = 'pizza'): Product
    {
        return $merchant->products()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'sku' => strtoupper($slug),
            'price_minor' => 25000,
            'currency' => $merchant->currency,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_human_price_is_stored_as_exact_integer_minor_units(): void
    {
        [$owner, $merchant] = $this->merchant('pricing');
        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Chicken Burger', 'sku' => 'burger-1', 'price' => '250.75', 'stock_quantity' => 10,
        ])->assertRedirect(route('merchant.products.index', $merchant));

        $this->assertDatabaseHas('products', [
            'merchant_id' => $merchant->id,
            'sku' => 'BURGER-1',
            'price_minor' => 25075,
        ]);
    }

    public function test_price_input_supports_iso_currency_minor_units(): void
    {
        $this->assertSame(125, Money::toMinor('125', 'JPY'));
        $this->assertSame(1234, Money::toMinor('1.234', 'KWD'));
        $this->assertSame('250.75', Money::forInput(25075, 'AFN'));
        $this->assertSame('1,250.75 AFN', Money::format(125075, 'AFN'));
    }

    public function test_product_edit_rejects_other_tenants_and_preserves_stock(): void
    {
        [$owner, $merchant] = $this->merchant('mine');
        [, $other] = $this->merchant('theirs');
        $own = $this->product($merchant);
        $foreign = $this->product($other);

        $this->actingAs($owner)->get(route('merchant.products.edit', [$merchant, $foreign]))->assertNotFound();
        $this->actingAs($owner)->put(route('merchant.products.update', [$merchant, $foreign]), [
            'name' => 'Stolen', 'price' => '999.00',
        ])->assertNotFound();

        $this->actingAs($owner)->put(route('merchant.products.update', [$merchant, $own]), [
            'name' => 'New Pizza', 'price' => '299.25', 'stock_quantity' => 999,
        ])->assertSessionHasErrors('stock_quantity');
        $this->assertDatabaseHas('products', ['id' => $own->id, 'stock_quantity' => 5]);

        $this->actingAs($owner)->put(route('merchant.products.update', [$merchant, $own]), [
            'name' => 'New Pizza', 'price' => '299.25', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $own->id, 'price_minor' => 29925, 'stock_quantity' => 5]);
    }

    public function test_variant_creation_and_cross_tenant_protection(): void
    {
        [$owner, $merchant] = $this->merchant('variants');
        [, $other] = $this->merchant('external');
        $product = $this->product($merchant);
        $foreign = $this->product($other);

        $this->actingAs($owner)->post(route('merchant.products.variants.store', [$merchant, $foreign]), [
            'name' => 'Unsafe', 'price_delta' => '1.00',
        ])->assertNotFound();

        $this->actingAs($owner)->post(route('merchant.products.variants.store', [$merchant, $product]), [
            'name' => 'Large', 'sku' => 'large-1', 'size' => 'Large', 'color' => 'Red',
            'price_delta' => '25.50', 'stock_quantity' => 3,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'merchant_id' => $merchant->id, 'product_id' => $product->id,
            'sku' => 'LARGE-1', 'price_delta_minor' => 2550,
        ]);
    }

    public function test_restaurant_extras_have_valid_choice_limits_and_tenant_scope(): void
    {
        [$owner, $merchant] = $this->merchant('extras');
        [, $other] = $this->merchant('external');
        $product = $this->product($merchant);
        $foreign = $this->product($other);

        $this->actingAs($owner)->post(route('merchant.products.modifiers.store', [$merchant, $foreign]), [
            'name' => 'Unsafe', 'min_choices' => 0, 'max_choices' => 1, 'options' => 'Cheese|1.00',
        ])->assertNotFound();

        $this->actingAs($owner)->post(route('merchant.products.modifiers.store', [$merchant, $product]), [
            'name' => 'Toppings', 'min_choices' => 2, 'max_choices' => 1, 'options' => 'Cheese|1.50',
        ])->assertSessionHasErrors('max_choices');

        $this->actingAs($owner)->post(route('merchant.products.modifiers.store', [$merchant, $product]), [
            'name' => 'Toppings', 'min_choices' => 0, 'max_choices' => 2,
            'options' => "Cheese|1.50\nOlives|0.50",
        ])->assertRedirect();

        $this->assertDatabaseHas('product_modifier_groups', ['merchant_id' => $merchant->id, 'name' => 'Toppings']);
        $this->assertDatabaseHas('product_modifier_options', ['merchant_id' => $merchant->id, 'name' => 'Cheese', 'price_minor' => 150]);
    }

    public function test_image_upload_is_scoped_and_records_its_path(): void
    {
        Storage::fake('public');
        [$owner, $merchant] = $this->merchant('photos');
        [, $other] = $this->merchant('others');
        $product = $this->product($merchant);
        $foreign = $this->product($other);

        $this->actingAs($owner)->post(route('merchant.products.images.store', [$merchant, $foreign]), [
            'image' => UploadedFile::fake()->image('dish.jpg'),
        ])->assertNotFound();
        $this->assertDatabaseCount('product_images', 0);

        $this->actingAs($owner)->post(route('merchant.products.images.store', [$merchant, $product]), [
            'image' => UploadedFile::fake()->image('dish.jpg'),
        ])->assertRedirect();

        $image = $product->images()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame($merchant->id, $image->merchant_id);
    }

    public function test_stock_adjustment_is_audited_and_cannot_go_negative(): void
    {
        [$owner, $merchant] = $this->merchant('stock');
        $product = $this->product($merchant);

        $this->actingAs($owner)->post(route('merchant.products.stock.adjust', [$merchant, $product]), [
            'delta' => -6, 'reason' => 'Damaged',
        ])->assertSessionHasErrors('delta');

        $this->actingAs($owner)->post(route('merchant.products.stock.adjust', [$merchant, $product]), [
            'delta' => 3, 'reason' => 'Supplier received',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
        $this->assertDatabaseHas('inventory_adjustments', [
            'merchant_id' => $merchant->id, 'product_id' => $product->id,
            'delta' => 3, 'before_quantity' => 5, 'after_quantity' => 8,
        ]);
        $this->assertDatabaseCount('inventory_adjustments', 1);
    }

    public function test_csv_import_is_tenant_scoped_and_idempotent_on_sku(): void
    {
        [$owner, $merchant] = $this->merchant('bulk');
        [, $other] = $this->merchant('elsewhere');
        $csv = "sku,name,category,price,stock,active\nABC-1,Apple Juice,Drinks,45.50,10,yes\n";

        $this->actingAs($owner)->post(route('merchant.products.import', $other), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('merchant.products.import', $merchant), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['merchant_id' => $merchant->id, 'sku' => 'ABC-1', 'price_minor' => 4550]);
        $this->assertDatabaseCount('products', 1);

        $product = $merchant->products()->firstOrFail();
        $product->update(['stock_quantity' => 7]);
        $updated = "sku,name,category,price,stock,active\nABC-1,Apple Juice,Drinks,48.00,100,no\n";

        $this->actingAs($owner)->post(route('merchant.products.import', $merchant), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $updated),
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['merchant_id' => $merchant->id, 'sku' => 'ABC-1', 'price_minor' => 4800, 'stock_quantity' => 7, 'is_active' => false]);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_invalid_csv_never_creates_partial_import(): void
    {
        [$owner, $merchant] = $this->merchant('invalid');
        $csv = "sku,name,price\nABC-1,Valid,10.00\nABC-2,Bad,not-a-price\n";
        $this->actingAs($owner)->post(route('merchant.products.import', $merchant), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_owner_can_upload_main_photo_when_creating_product(): void
    {
        Storage::fake('public');
        [$owner, $merchant] = $this->merchant('photo-on-create');
        $this->actingAs($owner)->get(route('merchant.products.create', $merchant))
            ->assertOk()->assertSee('name="image"', false)->assertSee('multipart/form-data');

        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Camera-ready product',
            'price' => '250.00',
            'image' => UploadedFile::fake()->image('product.jpg'),
        ])->assertRedirect(route('merchant.products.index', $merchant));

        $product = $merchant->products()->where('slug', 'camera-ready-product')->firstOrFail();
        $image = $product->images()->firstOrFail();
        $this->assertSame($merchant->id, $image->merchant_id);
        $this->assertSame('public', $image->disk);
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame(1, $product->images()->count());
    }

    public function test_invalid_photo_is_rejected_without_creating_product(): void
    {
        Storage::fake('public');
        [$owner, $merchant] = $this->merchant('photo-validation');
        $this->actingAs($owner)->post(route('merchant.products.store', $merchant), [
            'name' => 'Invalid image',
            'price' => '50.00',
            'image' => UploadedFile::fake()->create('payload.txt', 5, 'text/plain'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_other_tenant_cannot_upload_photo_during_product_creation(): void
    {
        Storage::fake('public');
        [$owner] = $this->merchant('owned');
        [, $other] = $this->merchant('other-photo-tenant');
        $this->actingAs($owner)->post(route('merchant.products.store', $other), [
            'name' => 'Unauthorized image',
            'price' => '10.00',
            'image' => UploadedFile::fake()->image('not-mine.jpg'),
        ])->assertForbidden();

        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('products', 0);
    }
}
