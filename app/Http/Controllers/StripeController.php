<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Checkout;
use App\Models\User;

class StripeController extends Controller
{
    public function showSubscribe()
    {
        return view('subscribe');
    }

    public function checkout(Request $request)
    {
        $user = User::find(1); // just using demo user ID 1

        return $user->checkout([
            'price' => 'price_1NzXXXXXXXYourTestStripePriceID',
        ]);
    }
}
