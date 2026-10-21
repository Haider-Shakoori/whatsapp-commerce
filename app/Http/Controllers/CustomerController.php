<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, Merchant $merchant): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $query = $merchant->customers()->withCount('conversations')->latest('id');
        if (! empty($data['search'])) {
            $search = trim($data['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('phone', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('name', 'like', '%'.addcslashes($search, '%_\\').'%');
            });
        }

        return view('merchant.customers.index', [
            'merchant' => $merchant,
            'customers' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Merchant $merchant, Customer $customer): View
    {
        $customer = $merchant->customers()->with(['notes.author', 'conversations.channel'])
            ->whereKey($customer->id)->firstOrFail();
        $orders = $merchant->orders()->where('customer_phone', $customer->phone)
            ->latest('id')->limit(20)->get();

        return view('merchant.customers.show', compact('merchant', 'customer', 'orders'));
    }

    public function update(Request $request, Merchant $merchant, Customer $customer): RedirectResponse
    {
        $customer = $merchant->customers()->whereKey($customer->id)->firstOrFail();
        $data = $request->validate(['name' => ['nullable', 'string', 'max:120']]);
        $customer->update(['name' => $data['name'] ?? null]);

        return back()->with('status', 'Customer profile updated.');
    }

    public function addNote(Request $request, Merchant $merchant, Customer $customer): RedirectResponse
    {
        $customer = $merchant->customers()->whereKey($customer->id)->firstOrFail();
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $customer->notes()->create([
            'merchant_id' => $merchant->id, 'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Private internal note saved.');
    }
}
