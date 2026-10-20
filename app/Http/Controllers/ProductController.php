<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Merchant $merchant): View
    {
        return view('merchant.products.index', [
            'merchant' => $merchant,
            'products' => $merchant->products()->with('category')->latest()->paginate(20),
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

    public function store(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => ['nullable', 'string', 'max:120'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('merchant_id', $merchant->id),
            ],
            'price_minor' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($data['name']);

        if ($slug === '' || $merchant->products()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Choose a unique product name for this business.']);
        }

        $merchant->products()->create([
            ...$data,
            'slug' => $slug,
            'currency' => $merchant->currency,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('merchant.products.index', $merchant)
            ->with('status', 'Product saved. WhatsApp publishing is not yet enabled.');
    }
}
