@extends('layouts.app')
@section('title', 'Platform subscriptions')
@section('content')
<h1>Subscription oversight</h1>
@include('platform.partials.nav')
<p class="muted">Billing is not activated. Plan grants are recorded entitlements, not cash receipts.</p>
<div class="card">
<form method="get" action="{{ route('platform.subscriptions.index') }}" class="actions">
<div class="field"><label for="status">Status filter</label><select id="status" name="status">
<option value="">All statuses</option>
@foreach(['trialing','active','expired','cancelled'] as $status)
<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
@endforeach
</select></div><button class="btn" type="submit">Filter</button></form>
<div class="table-wrap"><table>
<thead><tr><th>Merchant</th><th>Plan</th><th>Status</th><th>Expires</th><th>Renewal</th></tr></thead>
<tbody>@forelse($subscriptions as $sub)
<tr><td><a href="{{ route('platform.merchants.show', $sub->merchant) }}">{{ $sub->merchant->name }}</a></td>
<td>{{ $sub->plan?->name }}</td><td>{{ $sub->status }}</td>
<td>{{ $sub->period_end->format('Y-m-d H:i') }} UTC</td>
<td>{{ $sub->auto_renew ? 'Flag set; billing unconfigured' : 'Off' }}</td></tr>
@empty<tr><td colspan="5">No subscriptions.</td></tr>@endforelse
</tbody></table></div>{{ $subscriptions->links() }}</div>
<div class="card" style="margin-top:18px">
<h2>Recent merchant plan requests</h2>
@forelse($requests as $requestEvent)
<p><a href="{{ route('platform.merchants.show', $requestEvent->merchant) }}">{{ $requestEvent->merchant?->name ?? 'Deleted merchant' }}</a> · Requested: {{ $requestEvent->details['plan'] ?? 'Unspecified' }} · {{ $requestEvent->created_at->format('Y-m-d H:i') }} UTC</p>
@empty<p class="muted">No recent upgrade requests.</p>@endforelse
<p class="muted">Requests are informational; the platform does not collect a payment or auto-approve an upgrade.</p>
</div>
@endsection
