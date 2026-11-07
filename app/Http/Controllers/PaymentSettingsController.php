<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantPaymentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    public function index(Merchant $merchant): View
    {
        $settings = MerchantPaymentSetting::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['webhook_token' => (string) Str::uuid()]
        );

        return view('merchant.payments.settings', compact('merchant', 'settings'));
    }

    public function update(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'cod_enabled' => ['required', 'boolean'],
            'bank_transfer_enabled' => ['required', 'boolean'],
            'bank_instructions' => ['nullable', 'string', 'max:500'],
            'stripe_test_enabled' => ['required', 'boolean'],
            'stripe_test_secret' => ['nullable', 'string', 'max:255'],
            'stripe_test_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);
        $settings = MerchantPaymentSetting::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['webhook_token' => (string) Str::uuid()]
        );

        $key = $data['stripe_test_secret'] ?? $settings->stripe_test_secret;
        $webhook = $data['stripe_test_webhook_secret'] ?? $settings->stripe_test_webhook_secret;
        if ($key !== null && ! str_starts_with($key, 'sk_test_')) {
            throw ValidationException::withMessages(['stripe_test_secret' => 'Only Stripe test-mode keys are accepted.']);
        }
        if ($webhook !== null && ! str_starts_with($webhook, 'whsec_')) {
            throw ValidationException::withMessages(['stripe_test_webhook_secret' => 'Invalid Stripe webhook signing secret.']);
        }
        if ($data['stripe_test_enabled'] && (! $key || ! $webhook)) {
            throw ValidationException::withMessages(['stripe_test_enabled' => 'Provide a test API key and webhook signing secret first.']);
        }
        if ($data['bank_transfer_enabled'] && empty($data['bank_instructions'])) {
            throw ValidationException::withMessages(['bank_instructions' => 'Bank transfer instructions are required.']);
        }

        $settings->update([
            'cod_enabled' => (bool) $data['cod_enabled'],
            'bank_transfer_enabled' => (bool) $data['bank_transfer_enabled'],
            'bank_instructions' => $data['bank_instructions'] ?? null,
            'stripe_test_enabled' => (bool) $data['stripe_test_enabled'],
            'stripe_test_secret' => $key,
            'stripe_test_webhook_secret' => $webhook,
        ]);

        return back()->with('status', 'Payment settings updated. Live card payments remain disabled.');
    }
}
