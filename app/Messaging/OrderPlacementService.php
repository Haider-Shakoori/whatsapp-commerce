<?php

namespace App\Messaging;

use App\Messaging\Exceptions\CheckoutException;
use App\Models\ChatMenuSession;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShoppingCart;

final class OrderPlacementService
{
    /**
     * Caller owns a DB transaction and a lock on the conversation row.
     * Every product is locked in increasing ID order to avoid overselling.
     */
    public function place(Merchant $merchant, MessagingChannel $channel, ChatMenuSession $session): Order
    {
        if ($channel->merchant_id !== $merchant->id || $session->merchant_id !== $merchant->id
            || $session->messaging_channel_id !== $channel->id
            || ! $session->checkout_token || ! $session->checkout_customer_name
            || ! in_array($session->checkout_method, ['pickup', 'delivery'], true)
            || ($session->checkout_method === 'delivery' && ! $session->checkout_address)) {
            throw new CheckoutException('Checkout information is incomplete. Send CHECKOUT to restart.');
        }

        $key = $session->checkout_token;
        $existing = $merchant->orders()->where('checkout_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        $cart = ShoppingCart::query()->where('merchant_id', $merchant->id)
            ->where('messaging_channel_id', $channel->id)
            ->where('customer_phone', $session->customer_phone)
            ->lockForUpdate()->first();

        if (! $cart || $cart->currency !== $merchant->currency) {
            throw new CheckoutException('Your cart is unavailable. Send MENU to start again.');
        }

        $lines = $cart->items()->orderBy('id')->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw new CheckoutException('Your cart is empty. Send MENU to add products.');
        }

        $products = [];
        $variants = [];
        $quantityByProduct = [];
        $quantityByVariant = [];
        $validated = [];
        $subtotal = 0;
        foreach ($lines->pluck('product_id')->unique()->sort()->values() as $id) {
            $product = $merchant->products()->whereKey($id)->lockForUpdate()->first();
            if (! $product || ! $product->is_active || $product->currency !== $merchant->currency) {
                throw new CheckoutException('A product is no longer available. Please review your cart.');
            }
            $products[$id] = $product;
        }
        foreach ($lines->pluck('product_variant_id')->filter()->unique()->sort()->values() as $id) {
            $variant = ProductVariant::query()->where('merchant_id', $merchant->id)->whereKey($id)->lockForUpdate()->first();
            if (! $variant || ! $variant->is_active) {
                throw new CheckoutException('A selected variant is no longer available. Please review your cart.');
            }
            $variants[$id] = $variant;
        }

        foreach ($lines as $line) {
            $product = $products[$line->product_id];
            $variant = $line->product_variant_id ? ($variants[$line->product_variant_id] ?? null) : null;
            if ($line->quantity < 1 || $line->quantity > 99
                || ($variant && $variant->product_id !== $product->id)
                || ($line->variant_name !== null && ! $variant)
                || ($variant === null && $product->variants()->exists())) {
                throw new CheckoutException('A cart selection needs to be updated before checkout.');
            }

            // Verify every modifier option still belongs to this product and
            // all group choice rules remain valid. Never trust cart snapshot prices.
            $groups = $product->modifierGroups()->with('options')->get();
            $chosen = collect($line->selected_options ?? [])->flatMap(
                fn (array $group) => collect($group['options'] ?? [])->pluck('id')
            )->values()->all();
            if (count($chosen) !== count(array_unique($chosen))) {
                throw new CheckoutException('Duplicate product options require review.');
            }
            $optionTotal = 0;
            $chosenCount = 0;
            foreach ($groups as $group) {
                $options = $group->options->whereIn('id', $chosen);
                if ($options->count() < $group->min_choices || $options->count() > $group->max_choices) {
                    throw new CheckoutException('Required product options have changed. Please rebuild this cart line.');
                }
                $chosenCount += $options->count();
                $optionTotal += $options->sum('price_minor');
            }
            if ($chosenCount !== count($chosen)) {
                throw new CheckoutException('A product extra is no longer available. Please rebuild this cart line.');
            }
            $currentUnit = $product->price_minor + ($variant?->price_delta_minor ?? 0) + $optionTotal;
            if ($currentUnit < 0 || $currentUnit > 99999999999 || $currentUnit !== $line->unit_price_minor) {
                throw new CheckoutException('A product price changed. Please review the cart before checkout.');
            }
            $subtotal += $currentUnit * $line->quantity;
            if ($subtotal > 99999999999) {
                throw new CheckoutException('Order exceeds the permitted total.');
            }

            $quantityByProduct[$product->id] = ($quantityByProduct[$product->id] ?? 0) + $line->quantity;
            if ($variant) {
                $quantityByVariant[$variant->id] = ($quantityByVariant[$variant->id] ?? 0) + $line->quantity;
            }
            $validated[] = [$line, $product, $variant, $currentUnit];
        }

        foreach ($quantityByProduct as $id => $quantity) {
            if ($products[$id]->stock_quantity !== null && $quantity > $products[$id]->stock_quantity) {
                throw new CheckoutException('Insufficient stock for '.$products[$id]->name.'. Please review your cart.');
            }
        }
        foreach ($quantityByVariant as $id => $quantity) {
            if ($variants[$id]->stock_quantity !== null && $quantity > $variants[$id]->stock_quantity) {
                throw new CheckoutException('A variant has insufficient stock. Please review your cart.');
            }
        }

        $order = $merchant->orders()->create([
            'messaging_channel_id' => $channel->id,
            'checkout_key' => $key,
            'customer_name' => $session->checkout_customer_name,
            'customer_phone' => $session->customer_phone,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => $merchant->currency,
            'subtotal_minor' => $subtotal,
            'delivery_minor' => 0,
            'total_minor' => $subtotal,
            'delivery_details' => [
                'method' => $session->checkout_method,
                'address' => $session->checkout_method === 'delivery' ? $session->checkout_address : null,
                'delivery_fee_status' => $session->checkout_method === 'delivery' ? 'not_configured' : 'not_applicable',
            ],
        ]);
        foreach ($validated as [$line, $product, $variant, $unit]) {
            $order->items()->create([
                'merchant_id' => $merchant->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'quantity' => $line->quantity,
                'unit_price_minor' => $unit,
                'line_total_minor' => $unit * $line->quantity,
                'options' => $line->selected_options,
            ]);
        }

        foreach ($quantityByProduct as $id => $quantity) {
            if ($products[$id]->stock_quantity !== null) {
                $products[$id]->decrement('stock_quantity', $quantity);
            }
        }
        foreach ($quantityByVariant as $id => $quantity) {
            if ($variants[$id]->stock_quantity !== null) {
                $variants[$id]->decrement('stock_quantity', $quantity);
            }
        }

        $cart->items()->delete();

        return $order;
    }
}
