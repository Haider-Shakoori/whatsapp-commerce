@extends('layouts.app')
@section('title', 'Add product')
@section('content')
<div class="card narrow">
    <a href="{{ route('merchant.products.index', $merchant) }}">&larr; Back to products</a>
    <h1 style="margin-top:16px">Add product</h1>
    <p class="muted">Create a product for {{ $merchant->name }}. You can upload the first photo now, then add more photos, variants and extras after saving.</p>
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
        <section class="product-create-photo">
            <h2>Product image</h2>
            <p class="muted">Optional cover photo. JPEG, PNG or WebP · up to 4 MB. Add more images after creating the product.</p>
            <div class="product-image-picker">
                <label for="image" class="product-image-placeholder" id="product-image-preview" aria-label="Preview of selected product image">
                    <span id="product-image-placeholder-text">+ Select a product photo</span>
                    <img id="product-image-preview-photo" alt="Selected product preview" hidden>
                </label>
                <div class="field">
                    <label for="image">Choose photo</label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="image-help">
                    <small class="muted" id="image-help">The photo is saved with your product. File formats are checked on the server.</small>
                </div>
            </div>
        </section>
        <input type="hidden" name="is_active" value="0">
        <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')> Available for sale</label></div>
        <button class="btn" type="submit">Save product</button>
    </form>
</div>
<script>
document.getElementById('image')?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    const image = document.getElementById('product-image-preview-photo');
    const label = document.getElementById('product-image-placeholder-text');
    if (image.dataset.previewUrl) URL.revokeObjectURL(image.dataset.previewUrl);
    image.hidden = true;
    image.removeAttribute('src');
    if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        label.hidden = false;
        return;
    }
    const url = URL.createObjectURL(file);
    image.dataset.previewUrl = url;
    image.src = url;
    image.hidden = false;
    label.hidden = true;
});
</script>
@endsection
