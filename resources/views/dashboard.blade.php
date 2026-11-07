@extends('layouts.app')
@section('title', 'My businesses')
@section('content')
<section class="ws-hero-panel">
    <div>
        <span class="ws-eyebrow" style="color:#70e9cf">COMMERCE WORKSPACES</span>
        <h1>Welcome back, {{ auth()->user()->name }}</h1>
        <p>Manage your connected businesses, customer conversations, product catalogs and orders from one place.</p>
    </div>
    @if($isPlatformAdmin)
        <a class="btn" href="{{ route('platform.index') }}">Open platform console &rarr;</a>
    @endif
</section>
<div class="ws-page-intro">
    <div>
        <span class="ws-eyebrow">YOUR BUSINESSES</span>
        <h2>Choose a workspace</h2>
        <p>Your merchant access is determined by your assigned role. Only your businesses appear below.</p>
    </div>
    <span class="pill">{{ $merchants->count() }} {{ $merchants->count() === 1 ? 'workspace' : 'workspaces' }}</span>
</div>
<div class="grid">
    @forelse($merchants as $merchant)
        <article class="card ws-workspace-card">
            <div class="ws-meta"><span class="pill">{{ ucfirst($merchant->business_type) }}</span><span>{{ strtoupper($merchant->currency) }}</span><span>{{ $merchant->timezone }}</span></div>
            <h2>{{ $merchant->name }}</h2>
            <p class="muted">Manage customer interactions and commerce operations for this business.</p>
            <div class="ws-card-actions">
                <a class="btn" href="{{ route('merchant.products.index', $merchant) }}">Open workspace &rarr;</a>
                <a class="btn btn-light" href="{{ route('merchant.subscription.index', $merchant) }}">Plan & usage</a>
            </div>
        </article>
    @empty
        <div class="card ws-empty">
            @if($isPlatformAdmin)
                <h2>Admin access is separate from merchant access</h2>
                <p>Your administrator account can manage the platform but does not automatically gain access to customer data.</p>
                <a class="btn" href="{{ route('platform.index') }}">Go to platform console</a>
            @else
                <h2>No business workspaces assigned</h2>
                <p>Your account is not assigned to a merchant. Contact your business owner to request access.</p>
            @endif
        </div>
    @endforelse
</div>
@endsection
