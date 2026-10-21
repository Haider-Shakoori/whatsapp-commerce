<?php

namespace App\Messaging;

use App\Messaging\Exceptions\CartException;
use App\Models\CartItem;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShoppingCart;
use App\Support\Money;

final class ShoppingCartService
{
    private const MAX_LINES = 30;

    private const MAX_QUANTITY = 99;

    public function add(
        Merchant $merchant,
        MessagingChannel $channel,
        string $phone,
        int $productId,
        ?int $variantId,
        int $quantity,
        array $choices
    ): string {
        $this->checkOwner($merchant, $channel);
        $this->checkQuantity($quantity);

        $cart = $this->cart($merchant, $channel, $phone);
        $product = $merchant->products()->whereKey($productId)->lockForUpdate()
            ->with(['variants', 'modifierGroups.options'])->first();

        if (! $product || ! $product->is_active || $product->currency !== $merchant->currency
            || ($product->stock_quantity !== null && $product->stock_quantity === 0)) {
            throw new CartException('This product is no longer available.');
        }

        $variant = null;
        if ($product->variants->isNotEmpty()) {
            $variant = $product->variants->firstWhere('id', $variantId);
            if (! $variant || ! $variant->is_active
                || ($variant->stock_quantity !== null && $variant->stock_quantity === 0)) {
                throw new CartException('Please choose an available product variant.');
            }
        } elseif ($variantId !== null) {
            throw new CartException('The selected variant is unavailable.');
        }

        [$optionSnapshots, $optionTotal, $canonical] = $this->validateOptions($product, $choices);
        $unit = $product->price_minor + ($variant?->price_delta_minor ?? 0) + $optionTotal;
        if ($unit < 0 || $unit > 99999999999) {
            throw new CartException('The configured product price is invalid.');
        }

        $selectionKey = hash('sha256', json_encode([
            'product' => $product->id,
            'variant' => $variant?->id,
            'options' => $canonical,
        ], JSON_THROW_ON_ERROR));

        $existing = $cart->items()->where('selection_key', $selectionKey)->lockForUpdate()->first();
        if (! $existing && $cart->items()->count() >= self::MAX_LINES) {
            throw new CartException('Your cart has reached the maximum of 30 distinct items.');
        }

        $newQuantity = ($existing?->quantity ?? 0) + $quantity;
        $this->checkQuantity($newQuantity);
        $this->assertStock($cart, $product, $variant, $newQuantity, $existing?->id);

        if ($existing) {
            $existing->update([
                'quantity' => $newQuantity,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'unit_price_minor' => $unit,
                'selected_options' => $optionSnapshots,
            ]);
        } else {
            $cart->items()->create([
                'merchant_id' => $merchant->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'selection_key' => $selectionKey,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'selected_options' => $optionSnapshots,
                'quantity' => $newQuantity,
                'unit_price_minor' => $unit,
            ]);
        }

        return $product->name.' × '.$newQuantity.' added to cart at '.Money::format($unit, $merchant->currency).' each.'
            ."\nSend CART to see your basket, or MENU to continue browsing.";
    }

    public function show(Merchant $merchant, MessagingChannel $channel, string $phone): string
    {
        $this->checkOwner($merchant, $channel);
        $cart = ShoppingCart::query()
            ->where('merchant_id', $merchant->id)
            ->where('messaging_channel_id', $channel->id)
            ->where('customer_phone', $phone)->first();

        if (! $cart || $cart->items()->count() === 0) {
            return "🛒 Your cart is empty.\nSend MENU to browse products.";
        }

        $items = $cart->items()->orderBy('id')->get();
        $total = 0;
        $rows = [];
        foreach ($items as $i => $item) {
            $line = $item->quantity * $item->unit_price_minor;
            $total += $line;
            $variant = $item->variant_name ? ' ('.$item->variant_name.')' : '';
            $names = collect($item->selected_options ?? [])->flatMap(
                fn (array $group) => array_column($group['options'] ?? [], 'name')
            )->implode(', ');
            $rows[] = ($i + 1).'. '.mb_substr($item->product_name, 0, 70).mb_substr($variant, 0, 40)
                .($names !== '' ? "\n   Extras: ".mb_substr($names, 0, 120) : '')
                ."\n   ".$item->quantity.' × '.Money::format($item->unit_price_minor, $cart->currency)
                .' = '.Money::format($line, $cart->currency);
        }

        return "🛒 Your cart\n".implode("\n", $rows)
            ."\n\nSubtotal: ".Money::format($total, $cart->currency)
            ."\n\nQTY <item #> <number> to change quantity\nREMOVE <item #> to delete\nCLEAR to empty cart"
            ."\nMENU to browse more products\nReply CHECKOUT to place an order. Prices and stock are checked again at confirmation.";
    }

    public function setQuantity(Merchant $merchant, MessagingChannel $channel, string $phone, int $index, int $quantity): string
    {
        $this->checkOwner($merchant, $channel);
        if ($quantity === 0) {
            return $this->remove($merchant, $channel, $phone, $index);
        }
        $this->checkQuantity($quantity);
        $cart = $this->existing($merchant, $channel, $phone);
        $item = $this->line($cart, $index);
        $product = $merchant->products()->whereKey($item->product_id)->lockForUpdate()->first();
        if (! $product || ! $product->is_active) {
            throw new CartException('This product is no longer available. Remove it from your cart.');
        }
        $variant = $item->product_variant_id
            ? $product->variants()->whereKey($item->product_variant_id)->first()
            : null;
        if (($item->variant_name !== null && ! $variant) || ($variant && ! $variant->is_active)) {
            throw new CartException('The selected variant is no longer available.');
        }
        $this->assertStock($cart, $product, $variant, $quantity, $item->id);
        $item->update(['quantity' => $quantity]);

        return "Quantity updated.\n".$this->show($merchant, $channel, $phone);
    }

    public function remove(Merchant $merchant, MessagingChannel $channel, string $phone, int $index): string
    {
        $this->checkOwner($merchant, $channel);
        $cart = $this->existing($merchant, $channel, $phone);
        $item = $this->line($cart, $index);
        $item->delete();

        return "Item removed.\n".$this->show($merchant, $channel, $phone);
    }

    public function clear(Merchant $merchant, MessagingChannel $channel, string $phone): string
    {
        $this->checkOwner($merchant, $channel);
        $cart = ShoppingCart::query()->where('merchant_id', $merchant->id)
            ->where('messaging_channel_id', $channel->id)->where('customer_phone', $phone)
            ->lockForUpdate()->first();
        $cart?->items()->delete();

        return "Cart cleared.\nSend MENU to browse products.";
    }

    private function cart(Merchant $merchant, MessagingChannel $channel, string $phone): ShoppingCart
    {
        return ShoppingCart::firstOrCreate(
            ['messaging_channel_id' => $channel->id, 'customer_phone' => $phone],
            ['merchant_id' => $merchant->id, 'currency' => $merchant->currency]
        );
    }

    private function existing(Merchant $merchant, MessagingChannel $channel, string $phone): ShoppingCart
    {
        $cart = ShoppingCart::query()->where('merchant_id', $merchant->id)
            ->where('messaging_channel_id', $channel->id)->where('customer_phone', $phone)
            ->lockForUpdate()->first();
        if (! $cart) {
            throw new CartException('Your cart is empty.');
        }

        return $cart;
    }

    private function line(ShoppingCart $cart, int $index): CartItem
    {
        if ($index < 1 || $index > self::MAX_LINES) {
            throw new CartException('Choose an item number shown in your cart.');
        }
        $item = $cart->items()->orderBy('id')->skip($index - 1)->first();
        if (! $item) {
            throw new CartException('Choose an item number shown in your cart.');
        }

        return $item;
    }

    private function validateOptions(Product $product, array $choices): array
    {
        $snapshots = [];
        $canonical = [];
        $total = 0;
        $known = [];
        foreach ($product->modifierGroups as $group) {
            $known[] = (string) $group->id;
            $chosen = $choices[(string) $group->id] ?? [];
            if (! is_array($chosen) || count($chosen) < $group->min_choices
                || count($chosen) > $group->max_choices
                || count(array_unique($chosen)) !== count($chosen)) {
                throw new CartException('Please select valid options for '.$group->name.'.');
            }
            $selected = [];
            foreach ($chosen as $id) {
                $option = $group->options->firstWhere('id', $id);
                if (! $option) {
                    throw new CartException('A product option is no longer available.');
                }
                $selected[] = ['id' => $option->id, 'name' => $option->name, 'price_minor' => $option->price_minor];
                $total += $option->price_minor;
            }
            usort($selected, fn ($a, $b) => $a['id'] <=> $b['id']);
            $canonical[(string) $group->id] = array_column($selected, 'id');
            if ($selected) {
                $snapshots[] = ['group' => $group->name, 'options' => $selected];
            }
        }
        if (array_diff(array_keys($choices), $known)) {
            throw new CartException('A product option group is no longer available.');
        }
        ksort($canonical, SORT_NUMERIC);

        return [$snapshots, $total, $canonical];
    }

    private function assertStock(ShoppingCart $cart, Product $product, ?ProductVariant $variant, int $quantity, ?int $excludeId): void
    {
        $sameProduct = $cart->items()->where('product_id', $product->id)->get()
            ->reject(fn (CartItem $item) => $item->id === $excludeId);
        $baseTotal = $sameProduct->sum('quantity') + $quantity;
        if ($product->stock_quantity !== null && $baseTotal > $product->stock_quantity) {
            throw new CartException('Not enough available stock for '.$product->name.'.');
        }

        if ($variant && $variant->stock_quantity !== null) {
            $variantsTotal = $sameProduct->where('product_variant_id', $variant->id)->sum('quantity') + $quantity;
            if ($variantsTotal > $variant->stock_quantity) {
                throw new CartException('The selected variant has insufficient stock.');
            }
        }
    }

    private function checkOwner(Merchant $merchant, MessagingChannel $channel): void
    {
        if ($channel->merchant_id !== $merchant->id) {
            throw new CartException('Invalid merchant channel.');
        }
    }

    private function checkQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new CartException('Choose a quantity between 1 and 99.');
        }
    }
}
