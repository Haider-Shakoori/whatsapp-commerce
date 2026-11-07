<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function adjust(Request $request, Merchant $merchant, Product $product): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $data = $request->validate([
            'delta' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($merchant, $product, $data, $request): void {
            $locked = $merchant->products()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($locked->stock_quantity === null) {
                throw ValidationException::withMessages(['delta' => 'Enable stock tracking for this product first.']);
            }
            $after = $locked->stock_quantity + (int) $data['delta'];
            if ($after < 0 || $after > 4294967295) {
                throw ValidationException::withMessages(['delta' => 'Adjustment results in invalid stock.']);
            }
            $before = $locked->stock_quantity;
            $locked->update(['stock_quantity' => $after]);
            $locked->inventoryAdjustments()->create([
                'merchant_id' => $merchant->id,
                'user_id' => $request->user()->id,
                'delta' => $data['delta'],
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('status', 'Stock adjusted and recorded.');
    }
}
