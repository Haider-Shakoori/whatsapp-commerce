<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

final class PublicProductMedia
{
    /**
     * Uploaded media is intentionally public, but newly created intermediate
     * directories may be 0700 when the host runs a restrictive umask.
     * Explicitly publish only the directory chain for this product. Never
     * change storage/app/private or unrelated application permissions.
     */
    public static function ensureBrowsableDirectories(Merchant $merchant, Product $product): void
    {
        $disk = Storage::disk('public');

        foreach ([
            'merchants',
            "merchants/{$merchant->id}",
            "merchants/{$merchant->id}/products",
            "merchants/{$merchant->id}/products/{$product->id}",
        ] as $directory) {
            $disk->setVisibility($directory, 'public');
        }
    }
}
