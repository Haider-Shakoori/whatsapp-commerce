<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'merchants' => auth()->user()->merchants()->orderBy('name')->get(),
            'isPlatformAdmin' => auth()->user()->platformAdministrator()->exists(),
        ]);
    }
}
