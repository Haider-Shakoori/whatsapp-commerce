<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProductModifierController extends Controller
{
    public function store(Request $request, Merchant $merchant, Product $product): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'min_choices' => ['required', 'integer', 'min:0', 'max:20'],
            'max_choices' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_choices'],
            'options' => ['required', 'string', 'max:5000'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($data['options']));
        $options = [];
        foreach ($lines as $line) {
            $parts = explode('|', trim($line));
            $name = trim($parts[0] ?? '');
            if (count($parts) !== 2 || $name === '' || mb_strlen($name) > 120) {
                throw ValidationException::withMessages(['options' => 'Each option must use Name|Price on a separate line.']);
            }
            try {
                $price = Money::toMinor(trim($parts[1]), $merchant->currency);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['options' => $e->getMessage()]);
            }
            $options[] = ['name' => $name, 'price_minor' => $price, 'merchant_id' => $merchant->id];
        }
        if (count($options) > 20 || count($options) < $data['max_choices']) {
            throw ValidationException::withMessages(['options' => 'Provide enough options for the maximum selection (up to 20).']);
        }

        DB::transaction(function () use ($merchant, $product, $data, $options): void {
            $group = $product->modifierGroups()->create([
                'merchant_id' => $merchant->id,
                'name' => $data['name'],
                'min_choices' => $data['min_choices'],
                'max_choices' => $data['max_choices'],
            ]);
            $group->options()->createMany($options);
        });

        return back()->with('status', 'Options group created.');
    }

    public function destroy(Merchant $merchant, Product $product, ProductModifierGroup $group): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $product->modifierGroups()->whereKey($group->id)->firstOrFail()->delete();

        return back()->with('status', 'Options group removed.');
    }
}
