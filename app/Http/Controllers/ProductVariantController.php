<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProductVariantController extends Controller
{
    public function store(Request $request, Merchant $merchant, Product $product): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('product_variants')->where('merchant_id', $merchant->id)],
            'size' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:80'],
            'price_delta' => ['required', 'string', 'max:20'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);
        try {
            $delta = Money::toMinor($data['price_delta'], $merchant->currency, true);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['price_delta' => $e->getMessage()]);
        }
        if ($product->price_minor + $delta < 0) {
            throw ValidationException::withMessages(['price_delta' => 'Variant price cannot be negative.']);
        }

        $product->variants()->create([
            'merchant_id' => $merchant->id,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'options' => array_filter(['size' => $data['size'] ?? null, 'color' => $data['color'] ?? null]),
            'price_delta_minor' => $delta,
            'stock_quantity' => $data['stock_quantity'] ?? null,
        ]);

        return back()->with('status', 'Variant added.');
    }

    public function destroy(Merchant $merchant, Product $product, ProductVariant $variant): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $product->variants()->whereKey($variant->id)->firstOrFail()->delete();

        return back()->with('status', 'Variant deleted.');
    }
}
