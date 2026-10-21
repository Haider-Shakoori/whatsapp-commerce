<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use App\Saas\Exceptions\SubscriptionLimitException;
use App\Saas\MerchantEntitlements;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ProductController extends Controller
{
    public function index(Merchant $merchant): View
    {
        return view('merchant.products.index', [
            'merchant' => $merchant,
            'products' => $merchant->products()->with(['category', 'images'])->latest()->paginate(20),
            'categories' => $merchant->categories()->orderBy('name')->get(),
        ]);
    }

    public function create(Merchant $merchant): View
    {
        return view('merchant.products.create', [
            'merchant' => $merchant,
            'categories' => $merchant->categories()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Merchant $merchant, MerchantEntitlements $entitlements): RedirectResponse
    {
        $data = $this->validatedProduct($request, $merchant);
        $slug = Str::slug($data['name']);

        if ($slug === '' || $merchant->products()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Choose a unique product name for this business.']);
        }

        $uploadedPath = null;

        try {
            DB::transaction(function () use ($entitlements, $merchant, $data, $slug, $request, &$uploadedPath): void {
                $entitlements->assertCapacity($merchant, 'products');
                $product = $merchant->products()->create([
                    ...$data,
                    'slug' => $slug,
                    'currency' => $merchant->currency,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    $uploadedPath = $image->store("merchants/{$merchant->id}/products/{$product->id}", 'public');

                    if (! $uploadedPath) {
                        throw ValidationException::withMessages(['image' => 'Photo could not be stored. Please try again.']);
                    }

                    $product->images()->create([
                        'merchant_id' => $merchant->id,
                        'disk' => 'public',
                        'path' => $uploadedPath,
                        'original_name' => substr($image->getClientOriginalName(), 0, 255),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // A failed insert/transaction must not leave an orphaned uploaded file.
            if ($uploadedPath) {
                Storage::disk('public')->delete($uploadedPath);
            }

            if ($e instanceof SubscriptionLimitException) {
                throw ValidationException::withMessages(['name' => $e->getMessage()]);
            }

            throw $e;
        }

        return redirect()->route('merchant.products.index', $merchant)
            ->with('status', $uploadedPath ? 'Product and photo saved.' : 'Product saved. You can add photos, variants and extras in Edit / options.');
    }

    public function edit(Merchant $merchant, Product $product): View
    {
        $product = $merchant->products()
            ->with(['images', 'variants', 'modifierGroups.options', 'inventoryAdjustments'])
            ->whereKey($product->id)->firstOrFail();

        return view('merchant.products.edit', [
            'merchant' => $merchant,
            'product' => $product,
            'categories' => $merchant->categories()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Merchant $merchant, Product $product): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $data = $this->validatedProduct($request, $merchant, $product, false);
        $slug = Str::slug($data['name']);

        if ($slug === '' || $merchant->products()->where('slug', $slug)->whereKeyNot($product->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Choose a unique product name for this business.']);
        }

        $product->update([
            ...$data,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Product updated.');
    }

    private function validatedProduct(Request $request, Merchant $merchant, ?Product $product = null, bool $creating = true): array
    {
        if ($request->filled('sku')) {
            $request->merge(['sku' => strtoupper(trim($request->input('sku')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('products')->where('merchant_id', $merchant->id)->ignore($product?->id)],
            'category_id' => [
                'nullable', 'integer', Rule::exists('categories', 'id')->where('merchant_id', $merchant->id),
            ],
            'price' => ['nullable', 'string', 'max:25'],
            'price_minor' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
            'stock_quantity' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image' => [$creating ? 'nullable' : 'prohibited', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ]);

        // Old foundation API posts price_minor; the new UI uses a merchant-facing decimal price.
        if (isset($data['price']) && isset($data['price_minor'])) {
            throw ValidationException::withMessages(['price' => 'Supply only one price representation.']);
        }
        if (isset($data['price'])) {
            try {
                $data['price_minor'] = Money::toMinor($data['price'], $merchant->currency);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['price' => $e->getMessage()]);
            }
        } elseif (! isset($data['price_minor'])) {
            throw ValidationException::withMessages(['price' => 'A price is required.']);
        }
        unset($data['price'], $data['image']);

        if (array_key_exists('sku', $data) && $data['sku'] !== null) {
            $data['sku'] = strtoupper(trim($data['sku']));
        }

        return $data;
    }
}
