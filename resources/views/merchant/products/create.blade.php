@extends('layouts.app')
@section('title', 'Add product')
@section('content')
<div class="card narrow">
    <a href="{{ route('merchant.products.index', $merchant) }}">&larr; Back to products</a>
    <h1 style="margin-top:16px">Add product</h1>
    <p class="muted">{{ $merchant->name }} · {{ $merchant->currency }}</p>
    <form action="{{ route('merchant.products.store', $merchant) }}" method="post">
        @csrf
        <div class="field"><label for="name">Product name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="160"></div>
        <div class="field"><label for="category_id">Category</label><select id="category_id" name="category_id">
            <option value="">Uncategorized</option>
            @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id') === (string)$category->id)>{{ $category->name }}</option>@endforeach
        </select></div>
        <div class="field"><label for="sku">SKU (optional)</label><input id="sku" name="sku" value="{{ old('sku') }}" maxlength="120"></div>
        <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="3">{{ old('description') }}</textarea></div>
        <div class="grid">
            <div class="field"><label for="price_minor">Price in minor units *</label><input id="price_minor" name="price_minor" type="number" min="0" step="1" value="{{ old('price_minor') }}" required><small class="muted">For USD: 1299 means $12.99</small></div>
            <div class="field"><label for="stock_quantity">Stock (blank = not tracked)</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" step="1" value="{{ old('stock_quantity') }}"></div>
        </div>
        <input type="hidden" name="is_active" value="0">
        <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')> Available for sale</label></div>
        <button class="btn" type="submit">Save product</button>
    </form>
</div>
@endsection
