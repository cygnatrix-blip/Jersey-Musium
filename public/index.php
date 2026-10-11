<?php

declare(strict_types=1);

/*
 * Front controller – every request that is not a static file lands here.
 */

// Let PHP's built-in dev server serve static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Request;
use App\Core\Router;

$request = Request::capture();
session()->start();

$router = new Router(
    [
        'auth'       => App\Middleware\Authenticate::class,
        'guest'      => App\Middleware\Guest::class,
        'admin'      => App\Middleware\AdminAuthenticate::class,
        'verified'   => App\Middleware\EnsureEmailVerified::class,
        'can'        => App\Middleware\Can::class,
        'throttle'   => App\Middleware\Throttle::class,
        'csrf'       => App\Middleware\VerifyCsrf::class,
        'headers'    => App\Middleware\SecurityHeaders::class,
    ],
    ['headers', 'csrf'] // global middleware, outermost first
);

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/admin.php';

$response = $router->dispatch($request);

// Remember the last full page viewed, so validation errors can return the visitor to the form
// even when the browser sends no Referer header.
if ($request->method() === 'GET' && $response->status() === 200 && !$request->wantsJson()
    && !preg_match('#^/(api|webhooks)/|\.(xml|txt)$#', $request->path())) {
    session()->set('_previous_url', $request->fullUrl());
}

$response->send();

// Flush the response to the client now; deferred work (emails) runs afterwards in shutdown functions.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();      // PHP-FPM
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();    // LiteSpeed (Hostinger)
}
