@extends('layouts.app')
@section('title', 'Customer · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.customers.index', $merchant) }}">&larr; Customers</a>
<h1 style="margin:16px 0">{{ $customer->name ?: $customer->phone }}</h1>
<p class="muted">{{ $customer->phone }} · {{ $merchant->name }}</p>
<div class="grid" style="align-items:start">
<div class="card">
  <h2>Customer profile</h2>
  @if(auth()->user()->merchants()->whereKey($merchant->id)->wherePivotIn('role', ['owner', 'manager'])->exists())
  <form method="post" action="{{ route('merchant.customers.update', [$merchant, $customer]) }}">@csrf @method('PUT')
  <div class="field"><label for="name">Display name</label><input id="name" name="name" value="{{ old('name', $customer->name) }}" maxlength="120"></div>
  <button class="btn btn-light" type="submit">Save name</button></form>
  @endif
  <h2 style="margin-top:22px">WhatsApp threads</h2>
  @forelse($customer->conversations as $conversation)<p><a href="{{ route('merchant.inbox.show', [$merchant, $conversation]) }}">{{ $conversation->channel?->display_name ?? 'WhatsApp' }}</a> · {{ $conversation->status }} · {{ $conversation->unread_count }} unread</p>
  @empty<p class="muted">No conversations yet.</p>@endforelse
  <h2 style="margin-top:22px">Orders</h2>
  @forelse($orders as $order)<p><a href="{{ route('merchant.orders.show', [$merchant, $order]) }}">Order #{{ $order->id }}</a> · {{ ucfirst($order->status) }} · {{ \App\Support\Money::format($order->total_minor, $order->currency) }}</p>
  @empty<p class="muted">No linked orders yet.</p>@endforelse
</div>
<div class="card">
  <h2>Private customer notes</h2>
  <p class="muted">Visible only to this merchant's team. Never transmitted to customers.</p>
  @forelse($customer->notes->sortByDesc('id') as $note)
    <div style="border-top:1px solid #e5e7eb;padding:12px 0">
      <small class="muted">{{ $note->author?->name ?? 'Former staff' }} · {{ $note->created_at->format('Y-m-d H:i') }}</small>
      <p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $note->body }}</p>
    </div>
  @empty<p class="muted">No internal notes yet.</p>@endforelse
  <form method="post" action="{{ route('merchant.customers.notes', [$merchant, $customer]) }}">@csrf
  <div class="field"><label for="note_body">New private note</label><textarea id="note_body" name="body" maxlength="2000" rows="4" required></textarea></div>
  <button class="btn" type="submit">Save internal note</button></form>
</div>
</div>
@endsection
