<?php

return [
    'razorpay' => [
        'key_id'         => env('RAZORPAY_KEY_ID', ''),
        'key_secret'     => env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
        'base_url'       => 'https://api.razorpay.com/v1',
        'checkout_js'    => 'https://checkout.razorpay.com/v1/checkout.js',
    ],
    'cashfree' => [
        'env'         => env('CASHFREE_ENV', 'sandbox'),
        'app_id'      => env('CASHFREE_APP_ID', ''),
        'secret_key'  => env('CASHFREE_SECRET_KEY', ''),
        'api_version' => env('CASHFREE_API_VERSION', '2023-08-01'),
        'base_url'    => env('CASHFREE_ENV', 'sandbox') === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg',
        'checkout_js' => 'https://sdk.cashfree.com/js/v3/cashfree.js',
    ],
];
