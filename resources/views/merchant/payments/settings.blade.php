@extends('layouts.app')
@section('title', 'Payment methods · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.orders.index', $merchant) }}">&larr; Back to orders</a>
<h1 style="margin-top:16px">Payment settings</h1>
<p class="muted">Configure how customers can settle orders. Live card processing is not enabled.</p>
<div class="card" style="max-width:780px">
    <form method="post" action="{{ route('merchant.payments.settings.update', $merchant) }}">
        @csrf @method('PUT')
        <h2>Offline payment methods</h2>
        <input type="hidden" name="cod_enabled" value="0">
        <div class="field"><label><input type="checkbox" name="cod_enabled" value="1" @checked(old('cod_enabled', $settings->cod_enabled ? '1' : '0') == '1')> Offer cash-on-delivery / cash at pickup</label></div>
        <input type="hidden" name="bank_transfer_enabled" value="0">
        <div class="field"><label><input type="checkbox" name="bank_transfer_enabled" value="1" @checked(old('bank_transfer_enabled', $settings->bank_transfer_enabled ? '1' : '0') == '1')> Allow manual bank transfer</label></div>
        <div class="field"><label for="bank_instructions">Bank transfer instructions</label>
        <textarea id="bank_instructions" name="bank_instructions" maxlength="500" rows="3" placeholder="Bank name, account details, reference instructions">{{ old('bank_instructions', $settings->bank_instructions) }}</textarea>
        <small class="muted">Only visible to authorized merchants in this version. No automatic bank settlement occurs.</small></div>
        <h2>Stripe sandbox checkout (test mode only)</h2>
        <p class="muted">This is for development/testing, not collecting customer funds. Each merchant configures their own test account credentials. Only USD, EUR and GBP are enabled for this test connector.</p>
        <input type="hidden" name="stripe_test_enabled" value="0">
        <div class="field"><label><input type="checkbox" name="stripe_test_enabled" value="1" @checked(old('stripe_test_enabled', $settings->stripe_test_enabled ? '1' : '0') == '1')> Enable test checkout for this merchant</label></div>
        <div class="field"><label for="stripe_test_secret">Stripe TEST secret key</label><input type="password" id="stripe_test_secret" name="stripe_test_secret" placeholder="sk_test_..." autocomplete="new-password"><small class="muted">Leave blank to keep existing secret.</small></div>
        <div class="field"><label for="stripe_test_webhook_secret">Stripe test webhook signing secret</label><input type="password" id="stripe_test_webhook_secret" name="stripe_test_webhook_secret" placeholder="whsec_..." autocomplete="new-password"><small class="muted">Leave blank to keep existing secret.</small></div>
        <p><strong>Webhook endpoint:</strong><br><code style="overflow-wrap:anywhere">{{ route('api.stripe-test.webhook', ['token' => $settings->webhook_token]) }}</code></p>
        <p class="muted">Subscribe to checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed and checkout.session.expired. Keep secrets off GitHub.</p>
        <button class="btn" type="submit">Save payment settings</button>
    </form>
</div>
@endsection
