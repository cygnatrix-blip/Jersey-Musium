<?php

declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CatalogController;
use App\Controllers\CheckoutController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\PaymentController;
use App\Controllers\ProductController;
use App\Controllers\SeoController;
use App\Controllers\SetupController;
use App\Controllers\VerifyEmailController;
use App\Controllers\WishlistController;

// ---- Discovery ----
$router->get('/', [HomeController::class, 'index']);
$router->get('/shop', [CatalogController::class, 'shop']);
$router->get('/c/{slug:[a-z0-9-]+}', [CatalogController::class, 'category']);
$router->get('/search', [CatalogController::class, 'search']);
$router->get('/api/search-suggest', [CatalogController::class, 'suggest']);
$router->get('/p/{slug:[a-z0-9-]+}', [ProductController::class, 'show']);
$router->get('/api/pincode', [ProductController::class, 'pincode']);
$router->post('/p/{slug:[a-z0-9-]+}/review', [ProductController::class, 'review'], ['auth', 'verified', 'throttle:5,300']);

// ---- Bag ----
$router->get('/cart', [CartController::class, 'index']);
$router->get('/api/cart/count', [CartController::class, 'count']);
$router->post('/cart/add', [CartController::class, 'add'], ['throttle:30,60']);
$router->post('/cart/update', [CartController::class, 'update']);
$router->post('/cart/remove', [CartController::class, 'remove']);
$router->post('/cart/coupon', [CartController::class, 'applyCoupon'], ['throttle:10,60']);
$router->post('/cart/coupon/remove', [CartController::class, 'removeCoupon']);

// ---- Checkout & payment ----
$router->group('', ['auth', 'verified'], function ($r) {
    $r->get('/checkout', [CheckoutController::class, 'index']);
    $r->post('/checkout/place', [CheckoutController::class, 'place'], ['throttle:6,60']);
    $r->get('/checkout/pay/{number:[A-Z0-9]+}', [PaymentController::class, 'pay']);
    $r->post('/payment/razorpay/verify', [PaymentController::class, 'razorpayVerify']);
    $r->get('/payment/cashfree/return', [PaymentController::class, 'cashfreeReturn']);
    $r->get('/order/success/{number:[A-Z0-9]+}', [PaymentController::class, 'success']);
});
// Server-to-server, HMAC-verified, CSRF-exempt
$router->post('/webhooks/razorpay', [PaymentController::class, 'webhookRazorpay']);
$router->post('/webhooks/cashfree', [PaymentController::class, 'webhookCashfree']);

// ---- Auth ----
$router->group('', ['guest'], function ($r) {
    $r->get('/login', [AuthController::class, 'loginForm']);
    $r->post('/login', [AuthController::class, 'login'], ['throttle:8,300']);
    $r->get('/register', [AuthController::class, 'registerForm']);
    $r->post('/register', [AuthController::class, 'register'], ['throttle:5,600']);
    $r->get('/forgot-password', [AuthController::class, 'forgotForm']);
    $r->post('/forgot-password', [AuthController::class, 'forgot'], ['throttle:3,600']);
    $r->get('/reset-password/{token:[a-f0-9]{64}}', [AuthController::class, 'resetForm']);
    $r->post('/reset-password', [AuthController::class, 'reset'], ['throttle:5,600']);
});
$router->post('/logout', [AuthController::class, 'logout']);

// ---- Email verification (6-digit code sent at sign-up) ----
$router->get('/verify-email', [VerifyEmailController::class, 'show'], ['auth']);
$router->post('/verify-email', [VerifyEmailController::class, 'verify'], ['auth', 'throttle:10,300']);
$router->post('/verify-email/resend', [VerifyEmailController::class, 'resend'], ['auth', 'throttle:4,600']);

// ---- Account ----
$router->group('/account', ['auth'], function ($r) {
    $r->get('', [AccountController::class, 'index']);
    $r->post('/profile', [AccountController::class, 'updateProfile']);
    $r->post('/password', [AccountController::class, 'updatePassword'], ['throttle:5,300']);
    $r->get('/orders', [AccountController::class, 'orders']);
    $r->get('/orders/{number:[A-Z0-9]+}', [AccountController::class, 'order']);
    $r->get('/orders/{number:[A-Z0-9]+}/invoice', [AccountController::class, 'invoice']);
    $r->post('/orders/{number:[A-Z0-9]+}/cancel', [AccountController::class, 'cancel']);
    $r->get('/orders/{number:[A-Z0-9]+}/return', [AccountController::class, 'returnForm']);
    $r->post('/orders/{number:[A-Z0-9]+}/return', [AccountController::class, 'returnStore'], ['verified', 'throttle:5,300']);
    $r->get('/addresses', [AccountController::class, 'addresses']);
    $r->post('/addresses', [AccountController::class, 'addressStore']);
    $r->post('/addresses/{id:\d+}/delete', [AccountController::class, 'addressDelete']);
    $r->post('/addresses/{id:\d+}/default', [AccountController::class, 'addressDefault']);
});
$router->get('/wishlist', [WishlistController::class, 'index'], ['auth']);
$router->post('/wishlist/toggle', [WishlistController::class, 'toggle'], ['auth']);

// ---- Content & SEO ----
$router->get('/page/{slug:[a-z0-9-]+}', [PageController::class, 'show']);
$router->get('/track-order', [PageController::class, 'track']);
$router->post('/track-order', [PageController::class, 'track'], ['throttle:10,300']);
$router->post('/newsletter', [PageController::class, 'newsletter'], ['throttle:5,600']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);

// ---- One-time installer (no-SSH hosting): create first Super Admin; 404 unless SETUP_TOKEN matches and no admin exists ----
$router->get('/setup', [SetupController::class, 'show']);
$router->post('/setup', [SetupController::class, 'store'], ['throttle:5,600']);
