<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Payments\PaymentSettlement;
use App\Payments\StripeTestGateway;
use Illuminate\Console\Command;
use Throwable;

class ReconcileStripeTestPayments extends Command
{
    protected $signature = 'commerce:payments:reconcile';

    protected $description = 'Reconcile outstanding Stripe test checkout sessions without touching live payments';

    public function handle(StripeTestGateway $gateway, PaymentSettlement $settlement): int
    {
        PaymentAttempt::where('method', 'stripe_test')->where('status', 'pending')
            ->whereNotNull('provider_session_id')->orderBy('id')
            ->chunkById(100, function ($attempts) use ($gateway, $settlement): void {
                foreach ($attempts as $attempt) {
                    try {
                        $settings = $attempt->merchant->paymentSetting;
                        if (! $settings) {
                            continue;
                        }
                        $session = $gateway->retrieveCheckout($attempt, $settings);
                        $outcome = $settlement->applyStripeCheckout($attempt, $session, 'reconcile');
                        $this->line('Payment attempt #'.$attempt->id.': '.$outcome);
                    } catch (Throwable $e) {
                        $this->warn('Payment attempt #'.$attempt->id.': reconciliation unavailable (retry later).');
                    }
                }
            });

        return self::SUCCESS;
    }
}
