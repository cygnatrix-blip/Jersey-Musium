<?php

declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin;

$router->group('/admin', [], function ($r) {
    $r->get('/login', [Admin\AuthController::class, 'loginForm'], ['guest:admin']);
    $r->post('/login', [Admin\AuthController::class, 'login'], ['guest:admin', 'throttle:5,300']);
    $r->get('/login/verify', [Admin\AuthController::class, 'verifyForm'], ['guest:admin']);
    $r->post('/login/verify', [Admin\AuthController::class, 'verify'], ['guest:admin', 'throttle:10,300']);
    $r->post('/login/resend', [Admin\AuthController::class, 'resend'], ['guest:admin', 'throttle:3,300']);
    $r->post('/logout', [Admin\AuthController::class, 'logout']);

    $r->group('', ['admin'], function ($r) {
        $r->get('', [Admin\DashboardController::class, 'index'], ['can:dashboard.view']);

        // Catalog
        $r->group('/products', ['can:products.manage'], function ($r) {
            $r->get('', [Admin\ProductController::class, 'index']);
            $r->get('/create', [Admin\ProductController::class, 'create']);
            $r->post('', [Admin\ProductController::class, 'store']);
            $r->get('/{id:\d+}/edit', [Admin\ProductController::class, 'edit']);
            $r->post('/{id:\d+}', [Admin\ProductController::class, 'update']);
            $r->post('/{id:\d+}/delete', [Admin\ProductController::class, 'destroy']);
            $r->post('/{id:\d+}/images/{imageId:\d+}/delete', [Admin\ProductController::class, 'deleteImage']);
            $r->post('/{id:\d+}/stock', [Admin\ProductController::class, 'stock']);
        });
        $r->group('/categories', ['can:categories.manage'], function ($r) {
            $r->get('', [Admin\CategoryController::class, 'index']);
            $r->get('/create', [Admin\CategoryController::class, 'create']);
            $r->post('', [Admin\CategoryController::class, 'store']);
            $r->get('/{id:\d+}/edit', [Admin\CategoryController::class, 'edit']);
            $r->post('/{id:\d+}', [Admin\CategoryController::class, 'update']);
            $r->post('/{id:\d+}/delete', [Admin\CategoryController::class, 'destroy']);
        });

        // Orders & delivery lifecycle
        $r->get('/orders', [Admin\OrderController::class, 'index'], ['can:orders.view']);
        $r->get('/orders/export', [Admin\OrderController::class, 'export'], ['can:orders.view']);
        $r->get('/orders/{id:\d+}', [Admin\OrderController::class, 'show'], ['can:orders.view']);
        $r->get('/orders/{id:\d+}/invoice', [Admin\OrderController::class, 'invoice'], ['can:orders.view']);
        $r->group('/orders/{id:\d+}', ['can:orders.manage'], function ($r) {
            $r->post('/status', [Admin\OrderController::class, 'status']);
            $r->post('/ship', [Admin\OrderController::class, 'ship']);
            $r->post('/shipments/{shipmentId:\d+}/event', [Admin\OrderController::class, 'shipmentEvent']);
            $r->post('/cancel', [Admin\OrderController::class, 'cancel']);
            $r->post('/note', [Admin\OrderController::class, 'note']);
        });

        // Money
        $r->get('/payments', [Admin\PaymentController::class, 'index'], ['can:payments.view']);
        $r->get('/refunds', [Admin\RefundController::class, 'index'], ['can:refunds.manage']);
        $r->post('/orders/{id:\d+}/refund', [Admin\RefundController::class, 'store'], ['can:refunds.manage']);
        $r->post('/refunds/{id:\d+}/retry', [Admin\RefundController::class, 'retry'], ['can:refunds.manage']);

        // Returns
        $r->group('/returns', ['can:returns.manage'], function ($r) {
            $r->get('', [Admin\ReturnController::class, 'index']);
            $r->get('/{id:\d+}', [Admin\ReturnController::class, 'show']);
            $r->post('/{id:\d+}/status', [Admin\ReturnController::class, 'status']);
            $r->post('/{id:\d+}/refund', [Admin\ReturnController::class, 'refund']);
        });

        // Marketing
        $r->group('/coupons', ['can:coupons.manage'], function ($r) {
            $r->get('', [Admin\CouponController::class, 'index']);
            $r->get('/create', [Admin\CouponController::class, 'create']);
            $r->post('', [Admin\CouponController::class, 'store']);
            $r->get('/{id:\d+}/edit', [Admin\CouponController::class, 'edit']);
            $r->post('/{id:\d+}', [Admin\CouponController::class, 'update']);
            $r->post('/{id:\d+}/delete', [Admin\CouponController::class, 'destroy']);
        });
        $r->group('/banners', ['can:banners.manage'], function ($r) {
            $r->get('', [Admin\BannerController::class, 'index']);
            $r->get('/create', [Admin\BannerController::class, 'create']);
            $r->post('', [Admin\BannerController::class, 'store']);
            $r->get('/{id:\d+}/edit', [Admin\BannerController::class, 'edit']);
            $r->post('/{id:\d+}', [Admin\BannerController::class, 'update']);
            $r->post('/{id:\d+}/delete', [Admin\BannerController::class, 'destroy']);
        });

        // People & content
        $r->group('/customers', ['can:customers.manage'], function ($r) {
            $r->get('', [Admin\CustomerController::class, 'index']);
            $r->get('/{id:\d+}', [Admin\CustomerController::class, 'show']);
            $r->post('/{id:\d+}/toggle', [Admin\CustomerController::class, 'toggle']);
            $r->post('/{id:\d+}', [Admin\CustomerController::class, 'update']);
            $r->post('/{id:\d+}/reset-link', [Admin\CustomerController::class, 'sendReset'], ['throttle:5,600']);
            $r->post('/{id:\d+}/anonymise', [Admin\CustomerController::class, 'anonymise']);
        });
        $r->get('/reviews', [Admin\ReviewController::class, 'index'], ['can:reviews.manage']);
        $r->post('/reviews/{id:\d+}', [Admin\ReviewController::class, 'moderate'], ['can:reviews.manage']);
        $r->group('/pages', ['can:pages.manage'], function ($r) {
            $r->get('', [Admin\PageController::class, 'index']);
            $r->get('/create', [Admin\PageController::class, 'create']);
            $r->post('', [Admin\PageController::class, 'store']);
            $r->get('/{id:\d+}/edit', [Admin\PageController::class, 'edit']);
            $r->post('/{id:\d+}', [Admin\PageController::class, 'update']);
        });

        // System
        $r->get('/settings', [Admin\SettingController::class, 'index'], ['can:settings.manage']);
        $r->post('/settings', [Admin\SettingController::class, 'update'], ['can:settings.manage']);
        $r->group('/team', ['can:admins.manage'], function ($r) {
            $r->get('', [Admin\TeamController::class, 'index']);
            $r->get('/admins/create', [Admin\TeamController::class, 'createAdmin']);
            $r->post('/admins', [Admin\TeamController::class, 'storeAdmin']);
            $r->get('/admins/{id:\d+}/edit', [Admin\TeamController::class, 'editAdmin']);
            $r->post('/admins/{id:\d+}', [Admin\TeamController::class, 'updateAdmin']);
            $r->get('/roles/create', [Admin\TeamController::class, 'createRole']);
            $r->post('/roles', [Admin\TeamController::class, 'storeRole']);
            $r->get('/roles/{id:\d+}/edit', [Admin\TeamController::class, 'editRole']);
            $r->post('/roles/{id:\d+}', [Admin\TeamController::class, 'updateRole']);
        });
        $r->get('/audit', [Admin\AuditController::class, 'index'], ['can:audit.view']);
    });
});
