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
        <p><strong>Payment:</strong> {{ ucfirst($order->payment_status) }} (not collected by the platform)</p>
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
