@extends('layouts.app')
@section('title', 'My businesses')
@section('content')
<div class="actions"><div><h1>My businesses</h1><p class="muted">Choose a workspace to manage its catalog.</p></div></div>
<div class="grid">
    @forelse($merchants as $merchant)
        <div class="card">
            <span class="pill">{{ ucfirst($merchant->business_type) }}</span>
            <h2 style="margin-top:16px">{{ $merchant->name }}</h2>
            <p class="muted">{{ $merchant->currency }} · {{ $merchant->timezone }}</p>
            <a class="btn" href="{{ route('merchant.products.index', $merchant) }}">Manage products</a>
        </div>
    @empty
        <div class="card"><p>You do not belong to any businesses yet.</p></div>
    @endforelse
</div>
@endsection
