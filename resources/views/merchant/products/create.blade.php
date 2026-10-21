@extends('layouts.app')
@section('title', 'Add product')
@section('content')
<div class="card narrow">
    <a href="{{ route('merchant.products.index', $merchant) }}">&larr; Back to products</a>
    <h1 style="margin-top:16px">Add product</h1>
    <p class="muted">Create a product for {{ $merchant->name }}. Add the main photo now; you can add more photos and variants afterward.</p>
    <form action="{{ route('merchant.products.store', $merchant) }}" method="post" enctype="multipart/form-data">
        @csrf
        <div class="field"><label for="name">Product name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="160"></div>
        <div class="field"><label for="category_id">Category</label><select id="category_id" name="category_id">
            <option value="">Uncategorized</option>
            @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach
        </select></div>
        <div class="field"><label for="sku">SKU (optional)</label><input id="sku" name="sku" value="{{ old('sku') }}" maxlength="120"></div>
        <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="3">{{ old('description') }}</textarea></div>
        <div class="grid">
            <div class="field"><label for="price">Selling price ({{ $merchant->currency }})</label><input id="price" name="price" type="text" inputmode="decimal" placeholder="250.00" value="{{ old('price') }}" required><small class="muted">Enter the normal price, e.g. 250.00 (not minor units).</small></div>
            <div class="field"><label for="stock_quantity">Initial stock (blank = not tracked)</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" step="1" value="{{ old('stock_quantity') }}"></div>
        </div>
        <section class="product-photo-field" aria-labelledby="product-photo-label">
            <h2 id="product-photo-label">Product image</h2>
            <p class="muted">Upload a clear JPEG, PNG or WebP photo (up to 4 MB). Optional—add more photos after saving.</p>
            <label class="product-photo-drop" for="image">
                <span class="product-photo-placeholder" data-product-photo-placeholder aria-hidden="true">📷</span>
                <img class="product-photo-preview" data-product-photo-preview alt="Selected product photo preview" hidden>
                <span><strong>Choose a product photo</strong><small>Click to select from your device</small></span>
            </label>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="product-photo-help">
            <small id="product-photo-help" class="muted">The photo will be saved securely with this product. Maximum 4 MB.</small>
            @error('image') <p class="errors" role="alert">{{ $message }}</p> @enderror
        </section>
        <input type="hidden" name="is_active" value="0">
        <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')> Available for sale</label></div>
        <button class="btn" type="submit">Save product and photo</button>
    </form>
</div>
@endsection
