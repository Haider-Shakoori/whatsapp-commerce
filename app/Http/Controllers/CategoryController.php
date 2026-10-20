<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function store(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $slug = Str::slug($data['name']);

        if ($slug === '' || $merchant->categories()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Choose a different category name.']);
        }

        $merchant->categories()->create(['name' => $data['name'], 'slug' => $slug]);

        return back()->with('status', 'Category created.');
    }
}
