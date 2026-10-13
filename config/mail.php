<?php

return [
    'driver'     => env('MAIL_DRIVER', 'log'),
    'host'       => env('MAIL_HOST', ''),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Jersey Museum'),
    ],
];
