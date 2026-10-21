@extends('layouts.app')
@section('title', 'Add product')
@section('content')
<div class="card narrow">
    <a href="{{ route('merchant.products.index', $merchant) }}">&larr; Back to products</a>
    <h1 style="margin-top:16px">Add product</h1>
    <p class="muted">Add a photo, pricing and product details in one step. You can add more images, variants and extras later.</p>
    <form action="{{ route('merchant.products.store', $merchant) }}" method="post" enctype="multipart/form-data">
        @csrf
        <div class="field">
            <label for="image">Product photo <span class="muted">(optional)</span></label>
            <div class="product-image-uploader">
                <div class="product-image-preview" id="product-image-preview">
                    <span id="product-preview-placeholder">Add a product photo</span>
                    <img id="product-preview-image" alt="Selected product preview" hidden>
                </div>
                <div class="product-image-input">
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="image-help image-error">
                    <small class="muted" id="image-help">JPEG, PNG or WebP. Maximum 4 MB. Product photo uploads with the product.</small>
                    <p class="errors" id="image-error" role="alert" hidden></p>
                </div>
            </div>
        </div>
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
        <input type="hidden" name="is_active" value="0">
        <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')> Available for sale</label></div>
        <button class="btn" type="submit">Save product</button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('image');
    const preview = document.getElementById('product-preview-image');
    const placeholder = document.getElementById('product-preview-placeholder');
    const error = document.getElementById('image-error');
    if (!input || !preview || !placeholder || !error) return;
    let currentUrl;
    input.addEventListener('change', () => {
        if (currentUrl) URL.revokeObjectURL(currentUrl);
        currentUrl = undefined;
        preview.hidden = true;
        preview.removeAttribute('src');
        placeholder.hidden = false;
        error.hidden = true;
        const file = input.files?.[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024) {
            error.textContent = 'Choose a JPEG, PNG or WebP image up to 4 MB.';
            error.hidden = false;
            input.value = '';
            return;
        }
        currentUrl = URL.createObjectURL(file);
        preview.src = currentUrl;
        preview.hidden = false;
        placeholder.hidden = true;
    });
    window.addEventListener('pagehide', () => { if (currentUrl) URL.revokeObjectURL(currentUrl); });
});
</script>
@endsection
