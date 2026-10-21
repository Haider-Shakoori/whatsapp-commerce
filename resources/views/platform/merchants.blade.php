@extends('layouts.app')
@section('title', 'Merchant administration')
@section('content')
<div class="ws-page-intro">
    <div><span class="ws-eyebrow">MERCHANT OPERATIONS</span><h1>Merchant directory</h1>
        <p>Find businesses, inspect usage and subscriptions, and review account status without accessing customer message content.</p></div>
    <a href="{{ route('platform.index') }}" class="btn btn-light">Platform overview</a>
</div>
<section class="card">
    <form method="get" action="{{ route('platform.merchants.index') }}" class="ws-filter-grid">
        <div class="field"><label for="q">Business name or slug</label><input type="search" id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Find a business"></div>
        <div class="field"><label for="status">Account status</label><select id="status" name="status">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
        </select></div>
        <div class="field"><label for="plan">Subscription plan</label><select id="plan" name="plan">
            <option value="">All plans</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->code }}" @selected(request('plan') === $plan->code)>{{ $plan->name }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="created_from">Created after</label><input type="date" id="created_from" name="created_from" value="{{ request('created_from') }}"></div>
        <div class="field"><label for="sort">Sort by</label><select id="sort" name="sort">
            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
            <option value="name" @selected(request('sort') === 'name')>Business name</option>
            <option value="orders" @selected(request('sort') === 'orders')>Most orders</option>
        </select></div>
        <div class="ws-filter-actions"><button class="btn" type="submit">Apply filters</button><a class="btn btn-light" href="{{ route('platform.merchants.index') }}">Clear</a></div>
    </form>
</section>
<section class="card" style="margin-top:20px">
    <div class="actions" style="margin-bottom:16px"><h2>Merchant accounts</h2><span class="pill">{{ number_format($merchants->total()) }} results</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Merchant</th><th>Account</th><th>Subscription</th><th>Team</th><th>Channels</th><th>Products</th><th>Orders</th><th>Created</th></tr></thead>
        <tbody>@forelse($merchants as $merchant)
            <tr>
                <td><a href="{{ route('platform.merchants.show', $merchant) }}"><strong>{{ $merchant->name }}</strong></a><div class="muted">{{ $merchant->slug }}</div></td>
                <td><span class="pill {{ $merchant->status !== 'active' ? 'off' : '' }}">{{ ucfirst($merchant->status) }}</span></td>
                <td>{{ $merchant->subscription?->plan?->name ?? 'No plan' }}<div class="muted">{{ ucfirst($merchant->subscription?->status ?? 'Unknown') }}</div></td>
                <td>{{ number_format($merchant->users_count) }}</td><td>{{ number_format($merchant->messaging_channels_count) }}</td>
                <td>{{ number_format($merchant->products_count) }}</td><td>{{ number_format($merchant->orders_count) }}</td>
                <td>{{ $merchant->created_at?->format('Y-m-d') ?? '—' }}</td>
            </tr>
        @empty <tr><td colspan="8">No merchants match these filters.</td></tr> @endforelse</tbody>
    </table></div>
    <div class="ws-pagination">{{ $merchants->links() }}</div>
</section>
@endsection
