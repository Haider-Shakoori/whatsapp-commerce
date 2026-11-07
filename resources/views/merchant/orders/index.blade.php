@extends('layouts.app')
@section('title', 'Orders · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.products.index', $merchant) }}">&larr; {{ $merchant->name }} products</a>
<div class="actions" style="margin:16px 0 22px">
    <div><h1>Orders</h1><p class="muted">Customer orders from connected messaging channels. Payment verification is not enabled in Batch 6.</p></div>
    <span class="pill">{{ $orders->total() }} orders</span>
</div>
<div class="card">
    <div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Payment</th><th>Created</th></tr></thead>
    <tbody>
        @forelse($orders as $order)
            <tr><td><a href="{{ route('merchant.orders.show', [$merchant, $order]) }}">#{{ $order->id }}</a></td>
                <td>{{ $order->customer_name }}<br><small class="muted">{{ $order->customer_phone }}</small></td>
                <td>{{ \App\Support\Money::format($order->total_minor, $order->currency) }}</td>
                <td><span class="pill {{ $order->status === 'cancelled' ? 'off' : '' }}">{{ ucfirst($order->status) }}</span></td>
                <td>{{ ucfirst($order->payment_status) }}</td>
                <td>{{ $order->created_at->format('Y-m-d H:i') }}</td></tr>
        @empty<tr><td colspan="6">No customer orders yet.</td></tr>@endforelse
    </tbody></table></div>
    <div style="margin-top:16px">{{ $orders->links() }}</div>
</div>
@endsection
