@extends('layouts.app')
@section('title', 'Platform subscriptions')
@section('content')
<div class="ws-page-intro"><div><span class="ws-eyebrow">SUBSCRIPTION OPERATIONS</span><h1>Subscription oversight</h1>
<p>Review plan entitlements and upcoming expirations. Billing is not activated; manual grants are not revenue.</p></div>
<a href="{{ route('platform.merchants.index') }}" class="btn btn-light">Merchant directory &rarr;</a></div>
<section class="ws-stat-grid" aria-label="Subscription summary">
    @foreach([
    ['label' => 'Current paid-tier entitlements', 'count' => $activeCount],
    ['label' => 'Active trials', 'count' => $trialCount],
    ['label' => 'Expiring in 7 days', 'count' => $expiringCount],
    ['label' => 'Past end date (all statuses)', 'count' => $expiredCount],
    ] as $stat)<div class="ws-stat-card"><small>{{ $stat['label'] }}</small><strong>{{ number_format($stat['count']) }}</strong></div>@endforeach
</section>
<div class="card">
<form method="get" action="{{ route('platform.subscriptions.index') }}" class="ws-toolbar">
<div class="field search"><label for="q">Merchant name / slug</label><input id="q" type="search" name="q" maxlength="100" value="{{ request('q') }}"></div>
<div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
@foreach(['trialing','active','expired','cancelled'] as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst($state) }}</option>@endforeach</select></div>
<div class="field"><label for="plan">Plan</label><select id="plan" name="plan"><option value="">All plans</option>
@foreach($plans as $plan)<option value="{{ $plan->code }}" @selected(request('plan') === $plan->code)>{{ $plan->name }}</option>@endforeach</select></div>
<div class="field"><label for="expiry">End date</label><select id="expiry" name="expiry">
<option value="">Any</option><option value="7" @selected(request('expiry') === '7')>Next 7 days</option><option value="30" @selected(request('expiry') === '30')>Next 30 days</option><option value="expired" @selected(request('expiry') === 'expired')>Already passed</option></select></div>
<button class="btn" type="submit">Filter</button><a class="btn btn-light" href="{{ route('platform.subscriptions.index') }}">Clear</a>
</form>
</div>
<div class="card" style="margin-top:18px">
<div class="ws-panel-heading"><div><h2>Subscription directory</h2><p>Current entitlements and expiry dates (UTC). An active status alone does not guarantee an unexpired entitlement.</p></div><span class="pill">{{ $subscriptions->total() }} matching</span></div>
<div class="table-wrap"><table>
<thead><tr><th>Merchant</th><th>Plan</th><th>Recorded status</th><th>Entitled now</th><th>End date (UTC)</th><th>Renewal</th></tr></thead>
<tbody>@forelse($subscriptions as $sub)
<tr><td><a class="ws-row-title" href="{{ route('platform.merchants.show', $sub->merchant) }}">{{ $sub->merchant?->name ?? 'Deleted merchant' }}</a></td>
<td>{{ $sub->plan?->name ?? 'Unassigned' }}</td><td>{{ ucfirst($sub->status) }}</td>
<td>{{ $sub->isEntitled() ? 'Yes' : 'No' }}</td>
<td>{{ $sub->period_end?->format('Y-m-d H:i') ?? 'Not set' }}</td>
<td>{{ $sub->auto_renew ? 'Flag set (no recurring billing)' : 'Off' }}</td></tr>
@empty<tr><td colspan="6">No subscriptions match your filters.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:18px">{{ $subscriptions->links() }}</div>
</div>
<div class="card" style="margin-top:18px">
<div class="ws-panel-heading"><div><h2>Latest merchant plan requests</h2><p>Requests are informational; no payment is collected and upgrades are not auto-approved.</p></div></div>
@forelse($requests as $event)
<div class="catalog-line"><div>
<strong><a href="{{ route('platform.merchants.show', $event->merchant) }}">{{ $event->merchant?->name ?? 'Deleted merchant' }}</a></strong>
<small class="ws-row-subtitle">Requested plan: {{ $event->details['plan'] ?? 'Unspecified' }} · {{ $event->created_at?->format('Y-m-d H:i') ?? '—' }} UTC</small>
</div></div>
@empty<p class="muted">No plan requests recorded.</p>@endforelse
</div>
@endsection
