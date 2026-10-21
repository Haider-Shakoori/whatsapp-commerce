<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const TRANSITIONS = [
        'pending' => ['accepted', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Merchant $merchant): View
    {
        return view('merchant.orders.index', [
            'merchant' => $merchant,
            'orders' => $merchant->orders()->latest()->paginate(25),
            'canManage' => request()->user()->merchants()
                ->whereKey($merchant->id)->wherePivotIn('role', ['owner', 'manager'])->exists(),
        ]);
    }

    public function show(Merchant $merchant, Order $order): View
    {
        $order = $merchant->orders()->with('items')->whereKey($order->id)->firstOrFail();

        return view('merchant.orders.show', [
            'merchant' => $merchant,
            'order' => $order,
            'allowed' => self::TRANSITIONS[$order->status] ?? [],
            'canManage' => request()->user()->merchants()
                ->whereKey($merchant->id)->wherePivotIn('role', ['owner', 'manager'])->exists(),
        ]);
    }

    public function updateStatus(Request $request, Merchant $merchant, Order $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:accepted,preparing,ready,completed,cancelled']]);

        DB::transaction(function () use ($merchant, $order, $request, $data): void {
            $order = $merchant->orders()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $target = $data['status'];

            if (! in_array($target, self::TRANSITIONS[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This status transition is not permitted.']);
            }

            if ($target === 'cancelled' && ($order->payment_status === 'paid'
                || $order->paymentAttempts()->where('method', 'stripe_test')->whereIn('status', ['creating', 'pending'])->exists())) {
                throw ValidationException::withMessages(['status' => 'A paid order or an outstanding online checkout requires a separate refund/expiry review before cancellation.']);
            }

            if ($target === 'cancelled') {
                $items = $order->items()->get();
                $productTotals = $items->filter(fn ($item) => $item->product_id !== null)
                    ->groupBy('product_id')->map(fn ($lines) => $lines->sum('quantity'));
                $variantTotals = $items->filter(fn ($item) => $item->product_variant_id !== null)
                    ->groupBy('product_variant_id')->map(fn ($lines) => $lines->sum('quantity'));

                foreach ($productTotals->keys()->sort()->values() as $id) {
                    $product = $merchant->products()->whereKey($id)->lockForUpdate()->first();
                    if (! $product) {
                        throw ValidationException::withMessages(['status' => 'A product was deleted; resolve stock manually before cancelling.']);
                    }
                    if ($product->stock_quantity !== null) {
                        $before = $product->stock_quantity;
                        $after = $before + $productTotals[$id];
                        if ($after > 4294967295) {
                            throw ValidationException::withMessages(['status' => 'Stock balance overflow.']);
                        }
                        $product->update(['stock_quantity' => $after]);
                        $product->inventoryAdjustments()->create([
                            'merchant_id' => $merchant->id,
                            'user_id' => $request->user()->id,
                            'before_quantity' => $before,
                            'after_quantity' => $after,
                            'delta' => $productTotals[$id],
                            'reason' => 'Cancelled order #'.$order->id,
                        ]);
                    }
                }

                foreach ($variantTotals->keys()->sort()->values() as $id) {
                    $variant = ProductVariant::query()->where('merchant_id', $merchant->id)
                        ->whereKey($id)->lockForUpdate()->first();
                    if (! $variant) {
                        throw ValidationException::withMessages(['status' => 'A variant was deleted; resolve stock before cancelling.']);
                    }
                    if ($variant->stock_quantity !== null) {
                        $after = $variant->stock_quantity + $variantTotals[$id];
                        if ($after > 4294967295) {
                            throw ValidationException::withMessages(['status' => 'Variant stock balance overflow.']);
                        }
                        $variant->update(['stock_quantity' => $after]);
                    }
                }

                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()->id,
                ]);
            } else {
                $order->update(['status' => $target]);
            }
        });

        return back()->with('status', 'Order status updated.');
    }
}
