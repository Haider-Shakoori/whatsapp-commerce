@extends('layouts.app')
@section('title', 'Team inbox · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.products.index', $merchant) }}">&larr; {{ $merchant->name }} products</a>
<div class="actions" style="margin:16px 0"><div><h1>Team inbox</h1><p class="muted">All merchant WhatsApp conversations · {{ $unread }} with unread messages</p></div><a class="btn btn-light" href="{{ route('merchant.customers.index', $merchant) }}">Customer directory</a></div>
<div class="card" style="margin-bottom:18px">
<form method="get" action="{{ route('merchant.inbox.index', $merchant) }}" class="actions">
  <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open</option><option value="closed" @selected(request('status') === 'closed')>Closed</option></select></div>
  <div class="field"><label for="assigned">Assigned</label><select id="assigned" name="assigned"><option value="">Everyone</option><option value="mine" @selected(request('assigned') === 'mine')>Assigned to me</option><option value="unassigned" @selected(request('assigned') === 'unassigned')>Unassigned</option></select></div>
  <div class="field"><label><input type="checkbox" name="unread" value="1" @checked(request('unread') === '1')> Unread only</label></div>
  <button class="btn" type="submit">Filter</button>
</form>
</div>
<div class="card">
<div class="table-wrap"><table><thead><tr><th>Customer</th><th>Channel</th><th>Status</th><th>Assigned</th><th>Unread</th><th>Last message</th></tr></thead>
<tbody>@forelse($conversations as $conversation)
<tr><td><a href="{{ route('merchant.inbox.show', [$merchant, $conversation]) }}">{{ $conversation->customer->name ?: $conversation->customer->phone }}</a><br><small class="muted">{{ $conversation->customer->phone }}</small></td>
<td>{{ $conversation->channel?->display_name ?? 'Unlinked' }}</td>
<td>{{ ucfirst($conversation->status) }}</td>
<td>{{ $conversation->assignee?->name ?? 'Unassigned' }}</td>
<td><strong>{{ $conversation->unread_count }}</strong></td>
<td>{{ $conversation->last_message_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
@empty<tr><td colspan="6" class="muted">No conversations found.</td></tr>@endforelse
</tbody></table></div>
<div style="margin-top:14px">{{ $conversations->links() }}</div>
</div>
@endsection
