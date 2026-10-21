<?php

return [
    'api_rate_limit' => max(1, min(1000, (int) env('API_RATE_LIMIT', 60))),
    'checkout_demo_mode' => env('CHECKOUT_DEMO_MODE', false),
    'checkout_auto_paid' => env('CHECKOUT_AUTO_PAID', false),
];
