@extends('layouts.app')
@section('title', $merchant->name . ' products')
@section('content')
<div class="actions">
    <div>
        <a href="{{ route('dashboard') }}">&larr; My businesses</a>
        <h1 style="margin-top:12px">{{ $merchant->name }} · Products</h1>
        <p class="muted">Catalog management · {{ $merchant->currency }} · WhatsApp publishing not yet enabled</p>
    </div>
    <a class="btn" href="{{ route('merchant.products.create', $merchant) }}">+ Add product</a>
</div>
<section class="grid" style="margin:22px 0">
    <div class="card"><p class="muted">Products</p><div class="kpi">{{ $merchant->products()->count() }}</div></div>
    <div class="card"><p class="muted">Categories</p><div class="kpi">{{ $categories->count() }}</div></div>
    <div class="card"><p class="muted">Available products</p><div class="kpi">{{ $merchant->products()->where('is_active', true)->count() }}</div></div>
</section>
<div class="card" style="margin-bottom:22px">
    <h2>Categories</h2>
    <p class="muted">Add a category before assigning it to products.</p>
    <form method="post" action="{{ route('merchant.categories.store', $merchant) }}" class="actions">
        @csrf
        <div style="flex:1;min-width:190px"><label for="category_name">New category name</label><input id="category_name" name="name" required maxlength="100" placeholder="Pizza, Clothing, Electronics..."></div>
        <button class="btn" type="submit">Add category</button>
    </form>
    @if($categories->isNotEmpty())<p class="muted" style="margin-top:14px">{{ $categories->pluck('name')->join(' · ') }}</p>@endif
</div>
<div class="card"><div class="actions"><h2>Product catalog</h2><span class="muted">{{ $products->total() }} total</span></div>
    <div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Price (minor units)</th><th>Stock</th><th>Status</th></tr></thead><tbody>
    @forelse($products as $product)
        <tr><td><strong>{{ $product->name }}</strong><br><small class="muted">{{ $product->sku ?? $product->slug }}</small></td>
            <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
            <td>{{ $product->price_minor }} {{ $product->currency }}</td>
            <td>{{ $product->stock_quantity ?? 'Not tracked' }}</td>
            <td><span class="pill {{ $product->is_active ? '' : 'off' }}">{{ $product->is_active ? 'Available' : 'Unavailable' }}</span></td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">No products yet. Add your first product.</td></tr>
    @endforelse
    </tbody></table></div>
    <div style="margin-top:14px">{{ $products->links() }}</div>
</div>
<p class="footnote">Prices are stored in integer minor units for precision (e.g. 1299 = 12.99 when the currency has two decimals). Currency-specific user-friendly formatting is planned for the catalog UX batch.</p>
@endsection
