@extends('layouts.app')
@section('title', 'Order #'.$order->id)
@section('content')
<a href="{{ route('merchant.orders.index', $merchant) }}">&larr; All orders</a>
<div class="actions" style="margin:16px 0 22px"><div><h1>Order #{{ $order->id }}</h1><p class="muted">{{ $order->created_at->format('Y-m-d H:i') }} · {{ $merchant->name }}</p></div><span class="pill {{ $order->status === 'cancelled' ? 'off' : '' }}">{{ ucfirst($order->status) }}</span></div>
<div class="grid" style="align-items:start;margin-bottom:22px">
    <div class="card"><h2>Customer & fulfillment</h2>
        <p><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_phone }}</p>
        <p><strong>Method:</strong> {{ ucfirst($order->delivery_details['method'] ?? 'Unknown') }}</p>
        @if(($order->delivery_details['method'] ?? '') === 'delivery')
            <p><strong>Delivery address:</strong> {{ $order->delivery_details['address'] ?? 'Missing' }}</p>
            <p class="muted">Delivery fees have not been configured. Confirm any additional delivery charge with the customer.</p>
        @endif
    </div>
    <div class="card"><h2>Payment & actions</h2>
        <p><strong>Payment:</strong> {{ ucfirst($order->payment_status) }}</p>
        <p class="muted">Online links below are TEST only; manual settlements require a verified cash/bank receipt.</p>
        <p><strong>Amount for products:</strong> {{ \App\Support\Money::format($order->total_minor, $order->currency) }}</p>
        @if($canManage && $allowed)
            <form method="post" action="{{ route('merchant.orders.status', [$merchant, $order]) }}">
                @csrf @method('PATCH')
                <div class="field"><label for="status">Update status</label><select id="status" name="status" required>
                    @foreach($allowed as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach
                </select></div>
                <button class="btn" type="submit">Update order</button>
            </form>
            <small class="muted">Cancelling restores tracked stock exactly once. Completed orders cannot be cancelled.</small>
        @else
            <p class="muted">No further status changes permitted.</p>
        @endif
    </div>
</div>
@if($canManage)
<div class="card" style="margin-bottom:22px">
    <div class="actions"><h2>Payment management</h2><a href="{{ route('merchant.payments.settings', $merchant) }}">Configure payment methods</a></div>
    @php($paymentSettings = $merchant->paymentSetting)
    @if($order->payment_status === 'unpaid' && $order->status !== 'cancelled')
        <div class="actions" style="margin:16px 0">
            @if($paymentSettings?->cod_enabled)
                <form method="post" action="{{ route('merchant.orders.payments.method', [$merchant, $order]) }}">
                    @csrf <input type="hidden" name="method" value="cod"><button class="btn btn-light">Record cash payment due</button>
                </form>
            @endif
            @if($paymentSettings?->bank_transfer_enabled)
                <form method="post" action="{{ route('merchant.orders.payments.method', [$merchant, $order]) }}">
                    @csrf <input type="hidden" name="method" value="bank_transfer"><button class="btn btn-light">Record bank payment due</button>
                </form>
            @endif
            @if($paymentSettings?->stripe_test_enabled)
                <form method="post" action="{{ route('merchant.orders.payments.link', [$merchant, $order]) }}">
                    @csrf <button class="btn">Generate Stripe TEST checkout</button>
                </form>
            @endif
        </div>
        @if($paymentSettings?->bank_transfer_enabled && $paymentSettings->bank_instructions)
            <p><strong>Bank instructions:</strong> {{ $paymentSettings->bank_instructions }}</p>
        @endif
    @endif
    @forelse($order->paymentAttempts()->latest('id')->get() as $attempt)
        <div style="padding:14px 0;border-top:1px solid #e1e6ec">
            <p><strong>{{ strtoupper(str_replace('_', ' ', $attempt->method)) }}</strong> · {{ $attempt->status }}
                · {{ \App\Support\Money::format($attempt->amount_minor, $attempt->currency) }}</p>
            @if($attempt->method === 'stripe_test' && $attempt->payment_url)
                <p><a href="{{ $attempt->payment_url }}" target="_blank" rel="noopener noreferrer">Open Stripe test checkout</a></p>
                <div class="field"><label>Copy test checkout URL</label><input readonly value="{{ $attempt->payment_url }}" onclick="this.select()"></div>
            @endif
            @if($attempt->method === 'stripe_test' && $attempt->provider_session_id)
                <form method="post" action="{{ route('merchant.orders.payments.reconcile', [$merchant, $order, $attempt]) }}">
                    @csrf <button class="btn btn-light">Reconcile test payment status</button>
                </form>
            @endif
            @if($order->payment_status === 'unpaid' && $attempt->status === 'pending' && in_array($attempt->method, ['cod', 'bank_transfer']))
                <form method="post" action="{{ route('merchant.orders.payments.confirm', [$merchant, $order, $attempt]) }}">
                    @csrf
                    <div class="field"><label for="reference_{{ $attempt->id }}">Receipt / bank reference</label>
                        <input id="reference_{{ $attempt->id }}" name="reference" maxlength="120" required placeholder="Receipt number or verified bank transaction ID"></div>
                    <div class="field"><label for="confirmation_{{ $attempt->id }}">Type RECEIVED to confirm money was actually collected</label>
                        <input id="confirmation_{{ $attempt->id }}" name="confirmation" required></div>
                    <button class="btn">Confirm received payment</button>
                </form>
            @endif
            @if($attempt->verified_at)<p class="muted">Verified: {{ $attempt->verified_at->format('Y-m-d H:i') }} UTC</p>@endif
            @if($attempt->review_note)<p class="muted">Attention: {{ $attempt->review_note }}</p>@endif
        </div>
    @empty <p class="muted">No payment attempt recorded.</p>
    @endforelse
</div>
@endif
<div class="card"><h2>Ordered items</h2>
    <div class="table-wrap"><table><thead><tr><th>Product</th><th>Quantity</th><th>Unit price</th><th>Total</th></tr></thead>
    <tbody>@foreach($order->items as $line)
        <tr><td>{{ $line->product_name }}@if($line->variant_name)<br><small>{{ $line->variant_name }}</small>@endif
        @foreach($line->options ?? [] as $group)
            <div class="muted"><small>{{ $group['group'] ?? 'Extras' }}:
                {{ collect($group['options'] ?? [])->pluck('name')->join(', ') }}</small></div>
        @endforeach
        </td><td>{{ $line->quantity }}</td><td>{{ \App\Support\Money::format($line->unit_price_minor, $order->currency) }}</td>
        <td>{{ \App\Support\Money::format($line->line_total_minor, $order->currency) }}</td></tr>
    @endforeach</tbody></table></div>
    <p><strong>Subtotal: {{ \App\Support\Money::format($order->subtotal_minor, $order->currency) }}</strong></p>
</div>
@endsection
