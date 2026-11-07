@extends('layouts.app')
@section('title', 'Home')
@section('content')
<section class="hero">
    <span class="pill">Foundation • Batch 01</span>
    <h1>Sell through conversations. Manage your business in one place.</h1>
    <p class="muted">Create a merchant account, organize your products and prepare your catalog for conversational ordering. Messaging and checkout integrations will follow after the foundation is tested.</p>
    @guest<a class="btn" href="{{ route('register') }}">Create your business</a>@else<a class="btn" href="{{ route('dashboard') }}">Open dashboard</a>@endguest
</section>
<section class="grid">
    <div class="card"><h2>Multi-business ready</h2><p class="muted">Restaurant, groceries, fashion, electronics and general retail workflows share one tenant-isolated backend.</p></div>
    <div class="card"><h2>Catalog first</h2><p class="muted">Manage categories, prices, stock and variants centrally before publishing through chat providers.</p></div>
    <div class="card"><h2>Provider independent</h2><p class="muted">RelayWA or another messaging engine can connect later without taking ownership of business data.</p></div>
</section>
<p class="footnote">This is a development foundation, not an active WhatsApp order or payment service.</p>
@endsection
