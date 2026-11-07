<?php

namespace App\Messaging;

use App\Messaging\Exceptions\CartException;
use App\Models\ChatMenuSession;
use App\Models\MessagingChannel;
use App\Models\Product;
use App\Support\Money;

final class CartConversation
{
    private const VARIANTS_PER_PAGE = 8;

    public function __construct(private readonly ShoppingCartService $carts) {}

    /**
     * Called only after the signed RelayWA message has been validated and
     * the customer has opened the menu. The caller owns the DB transaction.
     */
    public function respond(MessagingChannel $channel, ChatMenuSession $session, string $command): ?string
    {
        $merchant = $channel->merchant;
        if ($command === 'CART') {
            $this->reset($session);
            $session->screen = 'cart';

            return $this->carts->show($merchant, $channel, $session->customer_phone);
        }

        if ($command === 'ORDER' || $command === 'PAY') {
            return 'Payment is not available yet. Send CHECKOUT to place an unpaid order or CART to review your basket.';
        }

        if ($command === 'CLEAR') {
            return 'To empty your basket, reply CLEAR YES. Your current products will be kept until confirmed.';
        }
        if ($command === 'CLEAR YES') {
            $this->reset($session);
            $session->screen = 'cart';

            return $this->carts->clear($merchant, $channel, $session->customer_phone);
        }

        if (preg_match('/^REMOVE(?:\s+(.+))?$/D', $command, $matches)) {
            if (! preg_match('/^[1-9][0-9]?$/D', $matches[1] ?? '')) {
                return 'Use REMOVE <item number>, for example REMOVE 1.';
            }
            try {
                return $this->carts->remove($merchant, $channel, $session->customer_phone, (int) $matches[1]);
            } catch (CartException $e) {
                return $e->getMessage();
            }
        }

        if (preg_match('/^QTY(?:\s+(.+))?$/D', $command, $matches)) {
            if (! preg_match('/^([1-9][0-9]?)\s+(0|[1-9][0-9]?)$/D', $matches[1] ?? '', $values)) {
                return 'Use QTY <item number> <quantity>, for example QTY 1 3. Quantity 0 removes the item.';
            }
            try {
                return $this->carts->setQuantity(
                    $merchant, $channel, $session->customer_phone, (int) $values[1], (int) $values[2]
                );
            } catch (CartException $e) {
                return $e->getMessage();
            }
        }

        if (preg_match('/^ADD(?:\s+(.+))?$/D', $command, $matches)) {
            if ($session->screen !== 'detail' || ! $session->selected_product_id) {
                return 'First open a product from MENU, then reply ADD or ADD 2.';
            }
            if (! preg_match('/^[1-9][0-9]?$/D', $matches[1] ?? '1')) {
                return 'Use ADD or ADD <quantity> (1–99).';
            }
            $quantity = (int) ($matches[1] ?? 1);
            $this->reset($session);
            $session->pending_quantity = $quantity;
            $product = $this->product($channel, $session);
            if (! $product) {
                $this->reset($session);

                return 'This product is no longer available. Reply MENU to choose another.';
            }

            if ($product->variants()->exists()) {
                $session->screen = 'variants';

                return $this->variantChoices($product, $session);
            }

            return $this->nextGroupOrAdd($channel, $session, $product);
        }

        if ($session->screen === 'variants') {
            if ($command === 'BACK' || $command === '0') {
                $this->reset($session);
                $session->screen = 'detail';

                return 'Selection cancelled. Reply ADD to start again or BACK for products.';
            }
            $product = $this->product($channel, $session);
            if (! $product) {
                $this->reset($session);

                return 'Product unavailable. Reply MENU to browse again.';
            }
            if ($command === 'NEXT' || $command === 'PREV') {
                $session->pending_variant_page = max(1, $session->pending_variant_page + ($command === 'NEXT' ? 1 : -1));

                return $this->variantChoices($product, $session);
            }
            if (! preg_match('/^[1-8]$/D', $command)) {
                return 'Choose a displayed variant number or NEXT, PREV, BACK.';
            }
            $variants = $this->eligibleVariants($product);
            $variant = $variants->forPage($session->pending_variant_page, self::VARIANTS_PER_PAGE)->values()->get((int) $command - 1);
            if (! $variant) {
                return 'That variant is not on this page. Choose a displayed number.';
            }
            $session->pending_variant_id = $variant->id;

            return $this->nextGroupOrAdd($channel, $session, $product);
        }

        if ($session->screen === 'modifiers') {
            if ($command === 'BACK') {
                $this->reset($session);
                $session->screen = 'detail';

                return 'Selection cancelled. Reply ADD to start again.';
            }
            $product = $this->product($channel, $session);
            if (! $product) {
                $this->reset($session);

                return 'Product unavailable. Reply MENU to browse again.';
            }
            $group = $product->modifierGroups()->with('options')->orderBy('id')
                ->skip($session->pending_group_index)->first();
            if (! $group) {
                $this->reset($session);

                return 'The product options changed. Reply ADD to choose again.';
            }

            $input = preg_replace('/\s+/', '', $command);
            if (! is_string($input) || ! preg_match('/^(?:0|[1-9][0-9]*(?:,[1-9][0-9]*)*)$/D', $input)) {
                return 'Reply with option numbers separated by commas, like 1,3. Use 0 to skip optional extras.';
            }
            $indexes = $input === '0' ? [] : array_map('intval', explode(',', $input));
            $all = $group->options->sortBy('id')->values();
            if (count($indexes) < $group->min_choices || count($indexes) > $group->max_choices
                || count(array_unique($indexes)) !== count($indexes)
                || collect($indexes)->contains(fn ($i) => $i < 1 || $i > $all->count())) {
                return "Choose between {$group->min_choices} and {$group->max_choices} distinct valid options.";
            }
            $selected = array_map(fn (int $i) => $all[$i - 1]->id, $indexes);
            $options = $session->pending_options ?? [];
            $options[(string) $group->id] = $selected;
            $session->pending_options = $options;
            $session->pending_group_index++;

            return $this->nextGroupOrAdd($channel, $session, $product);
        }

        return null;
    }

    public function reset(ChatMenuSession $session): void
    {
        $session->pending_variant_id = null;
        $session->pending_quantity = null;
        $session->pending_options = [];
        $session->pending_group_index = 0;
        $session->pending_variant_page = 1;
    }

    private function product(MessagingChannel $channel, ChatMenuSession $session): ?Product
    {
        return $channel->merchant->products()->whereKey($session->selected_product_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('stock_quantity')->orWhere('stock_quantity', '>', 0))
            ->first();
    }

    private function eligibleVariants(Product $product)
    {
        return $product->variants()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('stock_quantity')->orWhere('stock_quantity', '>', 0))
            ->orderBy('id')->get();
    }

    private function variantChoices(Product $product, ChatMenuSession $session): string
    {
        $variants = $this->eligibleVariants($product);
        $pages = max(1, (int) ceil($variants->count() / self::VARIANTS_PER_PAGE));
        $session->pending_variant_page = min($session->pending_variant_page, $pages);
        $visible = $variants->forPage($session->pending_variant_page, self::VARIANTS_PER_PAGE)->values();
        if ($visible->isEmpty()) {
            return 'No variants are currently available. Reply BACK to cancel.';
        }
        $list = $visible->map(fn ($v, $i) => ($i + 1).'. '.mb_substr($v->name, 0, 55).' — '
            .Money::format($product->price_minor + $v->price_delta_minor, $product->currency))->implode("\n");

        return "Choose a variant (page {$session->pending_variant_page}/{$pages}):\n".$list
            ."\n\nReply with a number, NEXT, PREV or BACK.";
    }

    private function nextGroupOrAdd(MessagingChannel $channel, ChatMenuSession $session, Product $product): string
    {
        $group = $product->modifierGroups()->with('options')->orderBy('id')
            ->skip($session->pending_group_index)->first();

        if ($group) {
            $session->screen = 'modifiers';
            $list = $group->options->sortBy('id')->values()->map(
                fn ($option, $i) => ($i + 1).'. '.mb_substr($option->name, 0, 70)
                    .' +'.Money::format($option->price_minor, $product->currency)
            )->implode("\n");

            return "Select {$group->name} ({$group->min_choices}–{$group->max_choices}):\n".$list
                ."\n\nReply 1 or 1,2 to choose; 0 to skip optional extras. BACK cancels.";
        }

        try {
            $reply = $this->carts->add(
                $channel->merchant, $channel, $session->customer_phone,
                $product->id, $session->pending_variant_id, $session->pending_quantity ?? 1,
                $session->pending_options ?? []
            );
        } catch (CartException $e) {
            $reply = $e->getMessage()."\nReply ADD to try again or MENU to browse.";
        }

        $session->screen = 'detail';
        $this->reset($session);

        return $reply;
    }
}
