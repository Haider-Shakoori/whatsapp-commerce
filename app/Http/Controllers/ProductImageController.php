<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\PublicProductMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Merchant $merchant, Product $product): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $request->validate(['image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096']]);

        $image = $request->file('image');
        $path = $image->store("merchants/{$merchant->id}/products/{$product->id}", 'public');
        if (! $path) {
            return back()->withErrors(['image' => 'Image could not be saved.']);
        }

        try {
            PublicProductMedia::ensureBrowsableDirectories($merchant, $product);
            $product->images()->create([
                'merchant_id' => $merchant->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => substr($image->getClientOriginalName(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Product image uploaded.');
    }

    public function destroy(Merchant $merchant, Product $product, ProductImage $image): RedirectResponse
    {
        $product = $merchant->products()->whereKey($product->id)->firstOrFail();
        $image = $product->images()->whereKey($image->id)->firstOrFail();
        $disk = $image->disk;
        $path = $image->path;
        $image->delete();
        Storage::disk($disk)->delete($path);

        return back()->with('status', 'Image removed.');
    }
}
