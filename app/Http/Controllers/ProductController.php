<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use App\Saas\MerchantEntitlements;
use App\Saas\Exceptions\SubscriptionLimitException;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
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

        try {
            DB::transaction(function () use ($entitlements, $merchant, $data, $slug, $request): void {
                $entitlements->assertCapacity($merchant, 'products');
                $merchant->products()->create([
            ...$data,
            'slug' => $slug,
            'currency' => $merchant->currency,
            'is_active' => $request->boolean('is_active', true),
                ]);
            });
        } catch (SubscriptionLimitException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('merchant.products.index', $merchant)
            ->with('status', 'Product saved. Open Edit / options to add photos, variants and extras.');
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
        unset($data['price']);

        if (array_key_exists('sku', $data) && $data['sku'] !== null) {
            $data['sku'] = strtoupper(trim($data['sku']));
        }

        return $data;
    }
}
