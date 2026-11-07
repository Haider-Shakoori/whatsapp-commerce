<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchant = $request->route('merchant');

        abort_unless(
            $merchant instanceof Merchant
            && $request->user()?->merchants()
                ->whereKey($merchant->id)
                ->wherePivotIn('role', ['owner', 'manager'])
                ->exists(),
            403,
        );

        return $next($request);
    }
}
