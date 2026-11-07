@extends('layouts.app')
@section('title', 'Merchant administration')
@section('content')
<h1>Merchant administration</h1>
@include('platform.partials.nav')
<div class="card">
<form method="get" action="{{ route('platform.merchants.index') }}" class="actions">
<div class="field"><label for="q">Business name or slug</label><input id="q" name="q" value="{{ request('q') }}" maxlength="100"></div>
<div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
<option value="active" @selected(request('status') === 'active')>Active</option>
<option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
</select></div>
<button class="btn" type="submit">Filter</button>
</form></div>
<div class="card" style="margin-top:18px"><div class="table-wrap"><table>
<thead><tr><th>Merchant</th><th>Status</th><th>Subscription</th><th>Team</th><th>Channels</th><th>Products</th><th>Orders</th></tr></thead>
<tbody>@forelse($merchants as $merchant)
<tr><td><a href="{{ route('platform.merchants.show', $merchant) }}">{{ $merchant->name }}</a><small class="muted"> · {{ $merchant->slug }}</small></td>
<td>{{ $merchant->status }}</td><td>{{ $merchant->subscription?->plan?->name ?? 'No plan' }} · {{ $merchant->subscription?->status ?? 'Unknown' }}</td>
<td>{{ $merchant->users_count }}</td><td>{{ $merchant->messaging_channels_count }}</td>
<td>{{ $merchant->products_count }}</td><td>{{ $merchant->orders_count }}</td></tr>
@empty<tr><td colspan="7">No merchants found.</td></tr>@endforelse
</tbody></table></div>{{ $merchants->links() }}</div>
@endsection
