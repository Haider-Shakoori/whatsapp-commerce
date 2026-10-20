<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProductImportController extends Controller
{
    public function store(Request $request, Merchant $merchant): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $stream = fopen($request->file('file')->getRealPath(), 'rb');
        if (! $stream) {
            throw ValidationException::withMessages(['file' => 'Unable to read the CSV file.']);
        }
        try {
            $header = fgetcsv($stream, 0, ',', '"', '');
            if (! $header) {
                throw ValidationException::withMessages(['file' => 'CSV file has no header.']);
            }
            $header = array_map(fn ($value) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))), $header);
            $required = ['sku', 'name', 'price'];
            if (count(array_unique($header)) !== count($header) || array_diff($required, $header)) {
                throw ValidationException::withMessages(['file' => 'CSV requires unique sku, name and price headers.']);
            }

            $records = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header) || count($records) >= 500) {
                    throw ValidationException::withMessages(['file' => 'Invalid CSV structure or over 500 rows near line '.$line.'.']);
                }
                $row = array_combine($header, array_map(fn ($v) => trim((string) $v), $values));
                $sku = strtoupper($row['sku']);
                if ($sku === '' || strlen($sku) > 120 || isset($seen[$sku])) {
                    throw ValidationException::withMessages(['file' => 'Missing or duplicate SKU near line '.$line.'.']);
                }
                $seen[$sku] = true;
                if (($row['name'] ?? '') === '' || mb_strlen($row['name']) > 160) {
                    throw ValidationException::withMessages(['file' => 'Invalid product name on line '.$line.'.']);
                }
                try {
                    $minor = Money::toMinor($row['price'], $merchant->currency);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['file' => 'Line '.$line.': '.$e->getMessage()]);
                }
                $stock = ($row['stock'] ?? '') !== '' ? $row['stock'] : null;
                if ($stock !== null && (! ctype_digit($stock) || (int) $stock > 4294967295)) {
                    throw ValidationException::withMessages(['file' => 'Invalid stock quantity on line '.$line.'.']);
                }
                $active = strtolower($row['active'] ?? 'yes');
                if (! in_array($active, ['yes', 'no', '1', '0', 'true', 'false'], true)) {
                    throw ValidationException::withMessages(['file' => 'Invalid active flag on line '.$line.'.']);
                }
                if (mb_strlen($row['description'] ?? '') > 5000 || mb_strlen($row['category'] ?? '') > 100) {
                    throw ValidationException::withMessages(['file' => 'Description or category too long on line '.$line.'.']);
                }
                $records[] = [
                    'sku' => $sku, 'name' => $row['name'], 'price_minor' => $minor,
                    'stock_quantity' => $stock === null ? null : (int) $stock,
                    'description' => $row['description'] ?? '',
                    'category' => $row['category'] ?? '',
                    'is_active' => in_array($active, ['yes', '1', 'true'], true),
                ];
            }
        } finally {
            fclose($stream);
        }
        if (! $records) {
            throw ValidationException::withMessages(['file' => 'No products found in CSV.']);
        }

        // All rows are validated before the transaction; a failure never leaves a partial import.
        [$created, $updated] = DB::transaction(function () use ($records, $merchant): array {
            $created = 0;
            $updated = 0;
            foreach ($records as $row) {
                $categoryId = null;
                if ($row['category'] !== '') {
                    $slug = Str::slug($row['category']);
                    if ($slug === '') {
                        throw ValidationException::withMessages(['file' => 'Invalid category slug.']);
                    }
                    $category = $merchant->categories()->firstOrCreate(['slug' => $slug], ['name' => $row['category']]);
                    $categoryId = $category->id;
                }
                $product = $merchant->products()->where('sku', $row['sku'])->first();
                if ($product) {
                    // Stock is intentionally not overwritten during reimports: adjustments must be audited.
                    $product->update([
                        'name' => $row['name'], 'description' => $row['description'],
                        'price_minor' => $row['price_minor'], 'category_id' => $categoryId,
                        'is_active' => $row['is_active'],
                    ]);
                    $updated++;
                } else {
                    $base = Str::slug($row['name']);
                    if ($base === '') {
                        throw ValidationException::withMessages(['file' => 'Product name cannot generate a URL slug.']);
                    }
                    $slug = $base;
                    $suffix = 2;
                    while ($merchant->products()->where('slug', $slug)->exists()) {
                        $slug = $base.'-'.$suffix++;
                    }
                    $merchant->products()->create([
                        'name' => $row['name'], 'slug' => $slug, 'sku' => $row['sku'],
                        'description' => $row['description'], 'price_minor' => $row['price_minor'],
                        'currency' => $merchant->currency, 'stock_quantity' => $row['stock_quantity'],
                        'category_id' => $categoryId, 'is_active' => $row['is_active'],
                    ]);
                    $created++;
                }
            }

            return [$created, $updated];
        });

        return back()->with('status', "CSV import finished: {$created} created, {$updated} updated. Stock on existing SKUs was preserved.");
    }
}
