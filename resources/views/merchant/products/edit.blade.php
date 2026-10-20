@extends('layouts.app')
@section('title', 'Edit ' . $product->name)
@section('content')
<a href="{{ route('merchant.products.index', $merchant) }}">&larr; All products</a>
<h1 style="margin-top:14px">{{ $product->name }}</h1>
<p class="muted">{{ $merchant->name }} · {{ $merchant->currency }} · Product ID {{ $product->id }}</p>
<section class="grid" style="align-items:start">
<div class="card">
    <h2>Product details</h2>
    <form method="post" action="{{ route('merchant.products.update', [$merchant, $product]) }}">
        @csrf @method('PUT')
        <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $product->name) }}" required></div>
        <div class="field"><label for="category_id">Category</label><select id="category_id" name="category_id"><option value="">Uncategorized</option>
        @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="field"><label for="sku">SKU</label><input id="sku" name="sku" value="{{ old('sku', $product->sku) }}"></div>
        <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="3">{{ old('description', $product->description) }}</textarea></div>
        <div class="field"><label for="price">Price ({{ $merchant->currency }})</label><input id="price" name="price" inputmode="decimal" value="{{ old('price', \App\Support\Money::forInput($product->price_minor, $merchant->currency)) }}" required><small class="muted">Enter price without currency suffix or thousands separators.</small></div>
        <input type="hidden" name="is_active" value="0">
        <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ? '1' : '0') === '1')> Available for sale</label></div>
        <button class="btn" type="submit">Save changes</button>
    </form>
</div>
<div class="card">
    <h2>Product photos</h2>
    <div class="product-gallery">
        @forelse($product->images as $image)
            <div class="gallery-item">
                <img src="{{ $image->url }}" alt="{{ $product->name }}" loading="lazy">
                <form method="post" action="{{ route('merchant.products.images.destroy', [$merchant, $product, $image]) }}">@csrf @method('DELETE')<button class="btn btn-light" type="submit">Remove</button></form>
            </div>
        @empty <p class="muted">No images uploaded.</p> @endforelse
    </div>
    <form method="post" enctype="multipart/form-data" action="{{ route('merchant.products.images.store', [$merchant, $product]) }}">
        @csrf
        <div class="field"><label for="image">Upload JPEG, PNG or WebP (up to 4 MB)</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required></div>
        <button class="btn" type="submit">Upload photo</button>
    </form>
</div>
</section>
<section class="grid" style="align-items:start">
<div class="card">
    <h2>Variants (sizes, colors, models)</h2>
    @forelse($product->variants as $variant)
        <div class="catalog-line">
            <div><strong>{{ $variant->name }}</strong><div class="muted">{{ implode(' · ', $variant->options ?? []) }} · Price {{ \App\Support\Money::format($product->price_minor + $variant->price_delta_minor, $merchant->currency) }} · Stock {{ $variant->stock_quantity ?? 'not tracked' }}</div></div>
            <form method="post" action="{{ route('merchant.products.variants.destroy', [$merchant, $product, $variant]) }}">@csrf @method('DELETE')<button class="btn btn-light" type="submit">Remove</button></form>
        </div>
    @empty <p class="muted">No variants yet.</p> @endforelse
    <form method="post" action="{{ route('merchant.products.variants.store', [$merchant, $product]) }}">
        @csrf
        <div class="field"><label for="variant_name">Variant name</label><input id="variant_name" name="name" placeholder="Large / Blue / 128GB" required></div>
        <div class="grid">
            <div class="field"><label for="size">Size</label><input id="size" name="size" placeholder="Large"></div>
            <div class="field"><label for="color">Color</label><input id="color" name="color" placeholder="Blue"></div>
        </div>
        <div class="grid">
            <div class="field"><label for="variant_sku">Variant SKU</label><input id="variant_sku" name="sku"></div>
            <div class="field"><label for="price_delta">Price difference ({{ $merchant->currency }})</label><input id="price_delta" name="price_delta" value="0" placeholder="+10.00 or -5.00" required></div>
            <div class="field"><label for="variant_stock">Variant stock (optional)</label><input id="variant_stock" name="stock_quantity" type="number" min="0"></div>
        </div>
        <button class="btn" type="submit">Add variant</button>
    </form>
</div>
<div class="card">
    <h2>Customizable extras</h2>
    <p class="muted">For restaurants: toppings, sauces or add-ons. Also works for other businesses.</p>
    @forelse($product->modifierGroups as $group)
        <div class="catalog-line">
            <div><strong>{{ $group->name }}</strong> <small class="muted">(choose {{ $group->min_choices }}–{{ $group->max_choices }})</small>
                @foreach($group->options as $option)<div class="muted">{{ $option->name }} · +{{ \App\Support\Money::format($option->price_minor, $merchant->currency) }}</div>@endforeach
            </div>
            <form method="post" action="{{ route('merchant.products.modifiers.destroy', [$merchant, $product, $group]) }}">@csrf @method('DELETE')<button class="btn btn-light" type="submit">Remove</button></form>
        </div>
    @empty <p class="muted">No extra options yet.</p> @endforelse
    <form method="post" action="{{ route('merchant.products.modifiers.store', [$merchant, $product]) }}">
        @csrf
        <div class="field"><label for="group_name">Group name</label><input id="group_name" name="name" placeholder="Pizza toppings" required></div>
        <div class="grid">
            <div class="field"><label for="min_choices">Minimum choices</label><input id="min_choices" name="min_choices" type="number" min="0" max="20" value="0" required></div>
            <div class="field"><label for="max_choices">Maximum choices</label><input id="max_choices" name="max_choices" type="number" min="1" max="20" value="1" required></div>
        </div>
        <div class="field"><label for="options">Options — one Name|Price per line</label><textarea id="options" name="options" rows="4" placeholder="Extra cheese|1.50&#10;Olives|0.50" required></textarea></div>
        <button class="btn" type="submit">Add options group</button>
    </form>
</div>
</section>
<div class="card">
    <h2>Inventory tracking</h2>
    @if($product->stock_quantity === null)
        <p class="muted">Stock is not tracked for this product. Initial tracking is set when creating a product.</p>
    @else
        <p><strong>Current stock: {{ number_format($product->stock_quantity) }}</strong></p>
        <form method="post" action="{{ route('merchant.products.stock.adjust', [$merchant, $product]) }}" class="grid">
            @csrf
            <div class="field"><label for="delta">Stock adjustment (+ or −)</label><input id="delta" name="delta" type="number" step="1" placeholder="-2" required></div>
            <div class="field"><label for="reason">Reason</label><input id="reason" name="reason" maxlength="255" placeholder="Received new stock / damaged item" required></div>
            <div class="field"><button class="btn" type="submit">Record adjustment</button></div>
        </form>
        @if($product->inventoryAdjustments->isNotEmpty())
            <div class="table-wrap"><table><thead><tr><th>Date</th><th>Change</th><th>Balance</th><th>Reason</th></tr></thead>
            <tbody>@foreach($product->inventoryAdjustments->sortByDesc('id')->take(10) as $adjustment)
            <tr><td>{{ $adjustment->created_at->format('Y-m-d H:i') }}</td><td>{{ $adjustment->delta }}</td><td>{{ $adjustment->after_quantity }}</td><td>{{ $adjustment->reason }}</td></tr>
            @endforeach</tbody></table></div>
        @endif
    @endif
</div>
@endsection
