@extends('layouts.app')
@section('title', 'Merchant administration')
@section('content')
<div class="ws-page-intro">
    <div>
        <span class="ws-eyebrow">TENANT OPERATIONS</span>
        <h1>Merchant directory</h1>
        <p>Find customers, review subscription entitlements and open a merchant's operational profile.</p>
    </div>
    <span class="pill">{{ number_format($merchants->total()) }} matching</span>
</div>
<section class="ws-stat-grid" aria-label="Merchant status overview">
    <div class="ws-stat-card"><small>Total merchants</small><strong>{{ number_format($merchantTotal) }}</strong></div>
    <div class="ws-stat-card"><small>Active merchants</small><strong>{{ number_format($merchantActive) }}</strong></div>
    <div class="ws-stat-card"><small>Suspended merchants</small><strong>{{ number_format($merchantSuspended) }}</strong></div>
</section>
<div class="card">
    <form method="get" action="{{ route('platform.merchants.index') }}" class="ws-toolbar">
        <div class="field search"><label for="q">Search business name or slug</label><input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search merchant…"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status">
            <option value="">All statuses</option>
            @foreach(['active' => 'Active', 'suspended' => 'Suspended'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="plan">Plan</label><select id="plan" name="plan">
            <option value="">All plans</option>
            <option value="none" @selected(request('plan') === 'none')>No plan</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->code }}" @selected(request('plan') === $plan->code)>{{ $plan->name }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="sort">Sort</label><select id="sort" name="sort">
            @foreach(['newest' => 'Newest', 'oldest' => 'Oldest', 'name' => 'Business name', 'orders' => 'Most orders'] as $key => $label)
                <option value="{{ $key }}" @selected(request('sort', 'newest') === $key)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="from">Joined after</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label for="to">Joined before</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
        <button class="btn" type="submit">Apply filters</button>
        <a class="btn btn-light" href="{{ route('platform.merchants.index') }}">Clear</a>
    </form>
</div>
<section class="card" style="margin-top:18px">
    <div class="ws-panel-heading"><div><h2>Merchants</h2><p>Showing {{ number_format($merchants->count()) }} on this page of {{ number_format($merchants->total()) }} matches.</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Business</th><th>Status</th><th>Current plan</th><th>Team</th><th>Channels</th><th>Products</th><th>Orders</th><th>Joined</th><th></th></tr></thead>
            <tbody>
                @forelse($merchants as $merchant)
                    <tr>
                        <td><a class="ws-row-title" href="{{ route('platform.merchants.show', $merchant) }}">{{ $merchant->name }}</a><small class="ws-row-subtitle">{{ $merchant->slug }} · {{ strtoupper($merchant->currency) }}</small></td>
                        <td><span @class(['ws-status-chip', 'suspended' => $merchant->status !== 'active'])>{{ ucfirst($merchant->status) }}</span></td>
                        <td>{{ $merchant->subscription?->plan?->name ?? 'None' }}<small class="ws-row-subtitle">{{ ucfirst($merchant->subscription?->status ?? 'No subscription') }}</small></td>
                        <td>{{ number_format($merchant->users_count) }}</td>
                        <td>{{ number_format($merchant->messaging_channels_count) }}</td>
                        <td>{{ number_format($merchant->products_count) }}</td>
                        <td>{{ number_format($merchant->orders_count) }}</td>
                        <td>{{ $merchant->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td><a class="ws-link-action" href="{{ route('platform.merchants.show', $merchant) }}">View profile &rarr;</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9">No merchants match your filters. <a href="{{ route('platform.merchants.index') }}">Clear filters</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px">{{ $merchants->links() }}</div>
</section>
@endsection
