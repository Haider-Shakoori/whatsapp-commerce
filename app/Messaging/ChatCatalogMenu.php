<?php

namespace App\Messaging;

use App\Models\ChatMenuSession;
use App\Models\InboundEvent;
use App\Models\MessagingChannel;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

final class ChatCatalogMenu
{
    private const PAGE_SIZE = 5;

    private const CATEGORY_PAGE_SIZE = 8;

    public function __construct(private readonly CommerceOutbox $outbox) {}

    /**
     * Invoked with the inbound event row locked in a database transaction.
     * Both the conversation state and its outbound reply are committed atomically.
     * This is text-only catalog browsing: cart and checkout are separate batches.
     */
    public function handle(InboundEvent $event, MessagingChannel $channel, array $message): void
    {
        if ($channel->merchant_id !== $event->merchant_id || ! $channel->menu_enabled || $channel->status !== 'connected'
            || $channel->merchant?->status !== 'active' || config('commerce.messaging_driver') !== 'relaywa') {
            return;
        }

        $phone = $message['sender_phone'] ?? null;
        $text = $message['text_body'] ?? null;
        $chatJid = $message['chat_jid'] ?? '';

        // Never respond to groups, broadcasts, malformed recipients or non-text messages.
        if (($message['direction'] ?? null) !== 'inbound' || ($message['message_type'] ?? null) !== 'text'
            || ! is_string($phone) || ! preg_match('/^[1-9][0-9]{6,14}$/D', $phone)
            || ! is_string($chatJid) || ! str_ends_with($chatJid, '@s.whatsapp.net')
            || ! is_string($text) || mb_strlen($text) > 200) {
            return;
        }

        $command = mb_strtoupper(trim($text));
        if ($command === '') {
            return;
        }

        $session = ChatMenuSession::query()
            ->where('messaging_channel_id', $channel->id)
            ->where('customer_phone', $phone)->lockForUpdate()->first();

        // Do not answer unrelated messages unless the customer explicitly opens the menu.
        $opens = in_array($command, ['MENU', 'HI', 'HELLO', 'START'], true);
        if (! $session && ! $opens) {
            return;
        }

        if (! $session) {
            $session = ChatMenuSession::create([
                'merchant_id' => $channel->merchant_id, 'messaging_channel_id' => $channel->id,
                'customer_phone' => $phone, 'screen' => 'categories',
            ]);
        }

        if ($command === 'STOP') {
            $session->update(['opted_out' => true, 'choice_ids' => []]);
            $reply = 'Menu replies are stopped. Send START to reopen the shopping menu.';
        } elseif ($session->opted_out && $command !== 'START') {
            return;
        } elseif ($opens) {
            $session->update(['opted_out' => false, 'screen' => 'categories', 'page' => 1, 'category_id' => null]);
            $reply = $this->categories($channel, $session, true);
        } elseif ($command === 'NEXT' || $command === 'PREV') {
            $session->page = max(1, $session->page + ($command === 'NEXT' ? 1 : -1));
            $reply = $session->screen === 'products'
                ? $this->products($channel, $session)
                : $this->categories($channel, $session);
        } elseif (in_array($command, ['0', 'BACK'], true)) {
            if ($session->screen === 'detail') {
                $session->screen = 'products';
                $reply = $this->products($channel, $session);
            } else {
                $session->screen = 'categories';
                $session->page = 1;
                $session->category_id = null;
                $reply = $this->categories($channel, $session);
            }
        } elseif (preg_match('/^[1-9]$/D', $command)) {
            $ids = $session->choice_ids ?? [];
            $id = $ids[((int) $command) - 1] ?? null;
            if (! is_int($id)) {
                $reply = 'That option is not on this page. Reply MENU to restart.';
            } elseif ($session->screen === 'categories') {
                // 0 signifies "all products" only in this internal category choice mapping.
                $category = $id === 0 ? null : $channel->merchant->categories()->whereKey($id)->first();
                if ($id !== 0 && ! $category) {
                    $reply = $this->categories($channel, $session);
                } else {
                    $session->screen = 'products';
                    $session->page = 1;
                    $session->category_id = $category?->id;
                    $reply = $this->products($channel, $session);
                }
            } elseif ($session->screen === 'products') {
                $product = $this->eligibleProducts($channel, $session->category_id)
                    ->whereKey($id)->with(['variants', 'modifierGroups.options'])->first();
                if (! $product) {
                    $reply = $this->products($channel, $session);
                } else {
                    $session->screen = 'detail';
                    $session->choice_ids = [];
                    $reply = $this->detail($product);
                }
            } else {
                $reply = 'Reply BACK for products or MENU for all categories.';
            }
        } else {
            $reply = $session->screen === 'detail'
                ? 'Reply BACK to browse products or MENU for categories.'
                : 'Choose a displayed number, NEXT, PREV or MENU.';
        }

        $session->save();
        $this->outbox->queueText(
            $channel->merchant, $channel, $phone, $reply,
            'menu-event-'.$event->id
        );
    }

    private function categories(MessagingChannel $channel, ChatMenuSession $session, bool $greeting = false): string
    {
        $categories = $channel->merchant->categories()
            ->whereHas('products', fn (Builder $query) => $this->filterAvailable($query))
            ->orderBy('name')->get(['id', 'name']);

        // Offer "All products" independently of categorization.
        $options = collect([['id' => 0, 'name' => 'All products']]);
        foreach ($categories as $category) {
            $options->push(['id' => (int) $category->id, 'name' => $category->name]);
        }

        $totalPages = max(1, (int) ceil($options->count() / self::CATEGORY_PAGE_SIZE));
        $session->page = min(max(1, $session->page), $totalPages);
        $visible = $options->slice(($session->page - 1) * self::CATEGORY_PAGE_SIZE, self::CATEGORY_PAGE_SIZE)->values();
        $session->screen = 'categories';
        $session->category_id = null;
        $session->choice_ids = $visible->pluck('id')->all();

        $header = $greeting ? "Welcome to {$channel->merchant->name}!\n" : '';
        $items = $visible->map(fn (array $c, int $i) => ($i + 1).'. '.$this->short($c['name'], 75))->implode("\n");

        return $header."🛍️ Browse categories (page {$session->page}/{$totalPages}):\n".$items
            ."\n\nReply with a number, NEXT, PREV or MENU.\nSend STOP to leave the menu.";
    }

    private function products(MessagingChannel $channel, ChatMenuSession $session): string
    {
        $query = $this->eligibleProducts($channel, $session->category_id)->orderBy('name')->orderBy('id');
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $session->page = min(max(1, $session->page), $pages);
        $products = $query->forPage($session->page, self::PAGE_SIZE)->get();
        $session->screen = 'products';
        $session->choice_ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();

        $label = $session->category_id
            ? $channel->merchant->categories()->whereKey($session->category_id)->value('name')
            : 'All products';
        $label = $label ?: 'Products';

        if ($products->isEmpty()) {
            return $this->short($label, 75)."\nNo items are available right now.\nReply BACK for categories.";
        }

        $rows = $products->map(fn (Product $p, int $i) => ($i + 1).'. '
            .$this->short($p->name, 55).' — '.Money::format($p->price_minor, $p->currency))->implode("\n");

        return '🛒 '.$this->short($label, 75)." (page {$session->page}/{$pages}):\n".$rows
            ."\n\nReply with a number for details, NEXT, PREV or BACK.\nOrdering is coming soon.";
    }

    private function detail(Product $product): string
    {
        $message = $product->name."\nPrice: ".Money::format($product->price_minor, $product->currency);
        if ($product->description) {
            $message .= "\n".$this->short($product->description, 500);
        }

        $message .= $product->stock_quantity === null
            ? '' : "\nAvailable stock: ".$product->stock_quantity;

        $variants = $product->variants->filter(fn ($variant) => $variant->is_active
            && ($variant->stock_quantity === null || $variant->stock_quantity > 0))->take(10);
        if ($variants->isNotEmpty()) {
            $message .= "\n\nVariants:";
            foreach ($variants as $variant) {
                $price = $product->price_minor + $variant->price_delta_minor;
                if ($price >= 0) {
                    $message .= "\n• ".$this->short($variant->name, 50).' — '.Money::format($price, $product->currency);
                }
            }
        }

        $groups = $product->modifierGroups->take(4);
        foreach ($groups as $group) {
            $message .= "\n\n".$this->short($group->name, 60)." (choose {$group->min_choices}–{$group->max_choices}):";
            foreach ($group->options->take(8) as $option) {
                $message .= "\n• ".$this->short($option->name, 50).' +'.Money::format($option->price_minor, $product->currency);
            }
        }

        return $this->short($message, 3700)."\n\nReply BACK for products or MENU for categories.\nCheckout is not available yet.";
    }

    private function eligibleProducts(MessagingChannel $channel, ?int $categoryId): Builder
    {
        $query = $channel->merchant->products()->getQuery();
        $this->filterAvailable($query);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $query;
    }

    private function filterAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('stock_quantity')->orWhere('stock_quantity', '>', 0));
    }

    private function short(string $value, int $max): string
    {
        $value = trim(preg_replace('/[ \t]+/u', ' ', $value) ?? '');

        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max - 1).'…';
    }
}
