<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PaymentReturnController extends Controller
{
    public function __invoke(): View
    {
        // This page never changes order or payment status. Only a trusted
        // webhook or server-side reconciliation can confirm a test payment.
        return view('payments.return');
    }
}
