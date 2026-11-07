<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'business_name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('merchants', 'slug')],
            'business_type' => ['required', Rule::in(['restaurant', 'retail', 'grocery', 'fashion', 'electronics', 'general'])],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'timezone'],
        ]);

        [$user, $merchant] = DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            ]);

            $merchant = Merchant::create([
                'name' => $data['business_name'], 'slug' => $data['slug'],
                'business_type' => $data['business_type'], 'currency' => $data['currency'],
                'timezone' => $data['timezone'], 'status' => 'active',
            ]);

            $merchant->users()->attach($user->id, ['role' => 'owner']);

            return [$user, $merchant];
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('merchant.products.index', $merchant);
    }

    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
