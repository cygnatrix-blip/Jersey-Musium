<?php

return [
    'name'     => env('APP_NAME', 'Jersey Musium'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    'key'      => env('APP_KEY', ''),
    'currency' => 'INR',

    // Admin console: require a 6-digit email OTP after the password (strongly recommended in production)
    'admin_2fa' => (bool) env('ADMIN_2FA', true),
    // Trust CF-Connecting-IP only when the site is actually behind Cloudflare (prevents IP spoofing of rate limits)
    'trust_cloudflare' => (bool) env('TRUST_CLOUDFLARE', false),

    'session' => [
        'name'     => env('SESSION_NAME', 'jm_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 120),
        'secure'   => (bool) env('SESSION_SECURE', true),
    ],

    'uploads' => [
        'max_mb' => (int) env('UPLOAD_MAX_MB', 5),
        'dir'    => BASE_PATH . '/public/uploads',
        'url'    => '/uploads',
    ],

    // Catalog
    'per_page'       => 24,
    'admin_per_page' => 25,
    'sizes'          => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'],
    'kit_types'      => ['home' => 'Home', 'away' => 'Away', 'third' => 'Third', 'gk' => 'Goalkeeper', 'training' => 'Training', 'retro' => 'Retro'],
    'genders'        => ['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids', 'unisex' => 'Unisex'],
];
