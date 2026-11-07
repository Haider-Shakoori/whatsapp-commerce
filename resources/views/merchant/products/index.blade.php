@extends('layouts.app')
@section('title', $merchant->name . ' products')
@section('content')
@php($canManage = auth()->user()->merchants()->whereKey($merchant->id)->wherePivotIn('role', ['owner', 'manager'])->exists())
<div class="actions">
    <div>
        <a class="muted" href="{{ route('dashboard') }}">&larr; My businesses</a><span class="muted"> / Product catalog</span>
        <h1 style="margin-top:12px">{{ $merchant->name }} · Products</h1>
        <p class="muted">Catalog management · {{ $merchant->currency }} · WhatsApp publishing not yet enabled</p>
    </div>
    @if($canManage)<div class="actions"><a class="btn btn-light" href="{{ route('merchant.channels.index', $merchant) }}">WhatsApp connections</a><a class="btn" href="{{ route('merchant.products.create', $merchant) }}">+ Add product</a></div>@endif
</div>
<section class="grid" style="margin:22px 0">
    <div class="card"><p class="muted">Products</p><div class="kpi">{{ $merchant->products()->count() }}</div></div>
    <div class="card"><p class="muted">Categories</p><div class="kpi">{{ $categories->count() }}</div></div>
    <div class="card"><p class="muted">Available products</p><div class="kpi">{{ $merchant->products()->where('is_active', true)->count() }}</div></div>
</section>
@if($canManage)
<div class="card" style="margin-bottom:22px">
    <h2>Categories</h2>
    <form method="post" action="{{ route('merchant.categories.store', $merchant) }}" class="actions">
        @csrf
        <div style="flex:1;min-width:190px"><label for="category_name">New category name</label><input id="category_name" name="name" required maxlength="100" placeholder="Pizza, Clothing, Electronics..."></div>
        <button class="btn" type="submit">Add category</button>
    </form>
    @if($categories->isNotEmpty())<p class="muted" style="margin-top:14px">{{ $categories->pluck('name')->join(' · ') }}</p>@endif
</div>
<div class="card" style="margin-bottom:22px">
    <h2>Bulk import from CSV</h2>
    <p class="muted">Import up to 500 products at once. Existing SKUs update prices and details; stock quantities on existing products are preserved for auditability.</p>
    <p><a href="{{ asset('samples/products.csv') }}" download>Download sample CSV</a></p>
    <form method="post" action="{{ route('merchant.products.import', $merchant) }}" enctype="multipart/form-data" class="actions">
        @csrf
        <div style="flex:1;min-width:240px"><label for="csv_file">CSV file (2 MB max)</label><input id="csv_file" type="file" name="file" accept=".csv,text/csv" required></div>
        <button class="btn" type="submit">Import products</button>
    </form>
</div>
@endif
<div class="card"><div class="actions"><h2>Product catalog</h2><span class="muted">{{ $products->total() }} total</span></div>
    <div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th>@if($canManage)<th>Manage</th>@endif</tr></thead><tbody>
    @forelse($products as $product)
        <tr>
            <td><div class="product-row">
                @if($product->images->isNotEmpty())<img class="product-thumb" src="{{ $product->images->first()->url }}" alt="{{ $product->name }}" loading="lazy">
                @else<span class="product-thumb placeholder" aria-hidden="true">◻</span>@endif
                <span><strong>{{ $product->name }}</strong><br><small class="muted">{{ $product->sku ?? $product->slug }}</small></span>
            </div></td>
            <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
            <td>{{ \App\Support\Money::format($product->price_minor, $product->currency) }}</td>
            <td>{{ $product->stock_quantity ?? 'Not tracked' }}</td>
            <td><span class="pill {{ $product->is_active ? '' : 'off' }}">{{ $product->is_active ? 'Available' : 'Unavailable' }}</span></td>
            @if($canManage)<td><a href="{{ route('merchant.products.edit', [$merchant, $product]) }}">Edit / options</a></td>@endif
        </tr>
    @empty
        <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="muted">No products yet. Add your first product.</td></tr>
    @endforelse
    </tbody></table></div>
    <div style="margin-top:14px">{{ $products->links() }}</div>
</div>
@endsection
