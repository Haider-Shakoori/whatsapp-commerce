<?php

namespace App\Messaging;

use App\Messaging\Exceptions\CheckoutException;
use App\Models\ChatMenuSession;
use App\Models\MessagingChannel;
use App\Models\ShoppingCart;
use App\Support\Money;
use Illuminate\Support\Str;

final class CheckoutConversation
{
    public function __construct(private readonly OrderPlacementService $orders) {}

    /** Called inside the message's transactional, deduplicated inbox handler. */
    public function respond(MessagingChannel $channel, ChatMenuSession $session, string $rawText): ?string
    {
        $command = mb_strtoupper(trim($rawText));
        if (! $session->checkout_step && $command !== 'CHECKOUT') {
            return null;
        }

        if ($command === 'CANCEL') {
            $this->cancel($session);
            $session->screen = 'cart';

            return 'Checkout cancelled. Your cart is saved. Reply CART to review it.';
        }

        if ($command === 'CHECKOUT') {
            if ($session->checkout_step) {
                return $this->prompt($session, $channel);
            }
            $cart = ShoppingCart::query()
                ->where('merchant_id', $channel->merchant_id)
                ->where('messaging_channel_id', $channel->id)
                ->where('customer_phone', $session->customer_phone)->first();
            if (! $cart || ! $cart->items()->exists()) {
                return 'Your cart is empty. Send MENU to choose products first.';
            }
            $session->checkout_token = (string) Str::uuid();
            $session->checkout_step = 'name';
            $session->screen = 'checkout';
            $session->checkout_customer_name = null;
            $session->checkout_method = null;
            $session->checkout_address = null;

            return $this->prompt($session);
        }

        if ($command === 'BACK') {
            $session->checkout_step = match ($session->checkout_step) {
                'method' => 'name',
                'address' => 'method',
                'confirm' => $session->checkout_method === 'delivery' ? 'address' : 'method',
                default => 'name',
            };

            return $this->prompt($session);
        }

        if ($session->checkout_step === 'name') {
            $name = trim(preg_replace('/\s+/u', ' ', $rawText) ?? '');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120
                || ! preg_match('/\p{L}/u', $name)
                || in_array($command, ['YES', 'CONFIRM', 'DELIVERY', 'PICKUP'], true)) {
                return 'Please enter a customer name (2–120 characters), or send CANCEL.';
            }
            $session->checkout_customer_name = $name;
            $session->checkout_step = 'method';

            return $this->prompt($session);
        }

        if ($session->checkout_step === 'method') {
            if (! in_array($command, ['1', '2', 'PICKUP', 'DELIVERY'], true)) {
                return "Choose 1 for pickup or 2 for delivery. Reply BACK to change your name.";
            }
            $session->checkout_method = in_array($command, ['1', 'PICKUP'], true) ? 'pickup' : 'delivery';
            $session->checkout_address = null;
            $session->checkout_step = $session->checkout_method === 'delivery' ? 'address' : 'confirm';

            return $this->prompt($session);
        }

        if ($session->checkout_step === 'address') {
            $address = trim(preg_replace('/\s+/u', ' ', $rawText) ?? '');
            if (mb_strlen($address) < 10 || mb_strlen($address) > 200
                || in_array($command, ['CONFIRM', 'YES', 'NO', 'PAY'], true)) {
                return 'Enter your delivery address (10–200 characters), or send BACK.';
            }
            $session->checkout_address = $address;
            $session->checkout_step = 'confirm';

            return $this->prompt($session);
        }

        if ($session->checkout_step === 'confirm') {
            if ($command !== 'CONFIRM') {
                return "Reply CONFIRM to place this order, BACK to edit, or CANCEL to stop.\n".$this->prompt($session);
            }
            try {
                $order = $this->orders->place($channel->merchant, $channel, $session);
            } catch (CheckoutException $e) {
                return $e->getMessage()."\nReply BACK to edit or CANCEL to leave checkout.";
            }

            $this->cancel($session);
            $session->screen = 'cart';

            $method = ($order->delivery_details['method'] ?? '') === 'delivery' ? 'delivery' : 'pickup';

            return '✅ Order #'.$order->id." placed.\nTotal for products: "
                .Money::format($order->total_minor, $order->currency)
                ."\nFulfillment: {$method}\nPayment: unpaid — arrange payment with the merchant."
                .($method === 'delivery' ? "\nAny delivery fee requires separate merchant confirmation." : '')
                ."\nYour cart is now empty. Send MENU to browse more.";
        }

        $this->cancel($session);

        return 'Checkout restarted for safety. Send CHECKOUT again.';
    }

    public function cancel(ChatMenuSession $session): void
    {
        $session->checkout_step = null;
        $session->checkout_token = null;
        $session->checkout_customer_name = null;
        $session->checkout_method = null;
        $session->checkout_address = null;
    }

    private function preview(MessagingChannel $channel, ChatMenuSession $session): string
    {
        $cart = ShoppingCart::query()->where('merchant_id', $channel->merchant_id)
            ->where('messaging_channel_id', $channel->id)
            ->where('customer_phone', $session->customer_phone)->first();
        if (! $cart) {
            return "\nCart: unavailable";
        }
        $subtotal = $cart->items()->get()->sum(fn ($line) => $line->quantity * $line->unit_price_minor);

        return "\nProduct subtotal (provisional): ".Money::format($subtotal, $cart->currency);
    }

    private function prompt(ChatMenuSession $session, MessagingChannel $channel): string
    {
        return match ($session->checkout_step) {
            'name' => "🧾 Checkout\nPlease type your name.\nReply CANCEL at any time.",
            'method' => "Thanks, {$session->checkout_customer_name}.\nChoose fulfillment:\n1. Pickup\n2. Delivery\nReply BACK to edit name.",
            'address' => "Please type your complete delivery address (street, area and landmarks).\nReply BACK to change method.",
            'confirm' => "Review order:\nCustomer: {$session->checkout_customer_name}\nPhone: {$session->customer_phone}"
                ."\nMethod: {$session->checkout_method}"
                .($session->checkout_method === 'delivery' ? "\nAddress: {$session->checkout_address}" : '')
                .$this->preview($channel, $session)."
Prices and stock are rechecked when you confirm."
                .($session->checkout_method === 'delivery'
                    ? "\nDelivery fees are not configured. The merchant must agree on any fee separately."
                    : '')
                ."\nNo payment is taken now.\nReply CONFIRM to place the order, BACK to edit or CANCEL.",
            default => 'Send CHECKOUT to begin.',
        };
    }
}
