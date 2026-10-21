<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\Checkout;

class StorefrontController extends Controller
{
    public function config()
    {
        return response()->json([
            'tax_rate' => max(0, min(1, (float) SystemSetting::value('tax_rate', 0.11))),
            'checkout_demo_mode' => Checkout::autoPaid(),
        ]);
    }
}
