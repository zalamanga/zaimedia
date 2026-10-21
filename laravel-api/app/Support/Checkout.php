<?php

namespace App\Support;

use App\Models\SystemSetting;

class Checkout
{
    public static function autoPaid(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('zaimedia.checkout_demo_mode', false)
            && (bool) config('zaimedia.checkout_auto_paid', false)
            && (bool) SystemSetting::value('checkout_auto_paid', true);
    }
}
