<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Adds security headers to every response, including a nonce-based Content-Security-Policy
 * that only whitelists the payment gateways' and Google Fonts' origins.
 */
final class SecurityHeaders
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Errors (404, 403, 500...) are rendered here so they still get security headers.
            $response = \App\Core\ErrorHandler::render($e);
        }
        $n = self::nonce();

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$n' https://checkout.razorpay.com https://cdn.razorpay.com https://sdk.cashfree.com https://www.googletagmanager.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://*.razorpay.com https://*.cashfree.com https://*.google-analytics.com https://*.analytics.google.com",
            "frame-src https://*.razorpay.com https://*.cashfree.com",
            "form-action 'self' https://*.cashfree.com https://*.razorpay.com",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
        ]);

        $response->header('Content-Security-Policy', $csp)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.razorpay.com")')
            // isolate our window from cross-origin openers, but keep bank 3-D Secure popups working
            ->header('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if ($request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Personal / transactional pages must never be stored by browsers or shared caches.
        foreach (['/admin', '/account', '/checkout', '/cart', '/order/', '/payment/', '/login', '/register', '/reset-password', '/forgot-password', '/wishlist', '/track-order', '/setup', '/verify-email'] as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                $response->header('Cache-Control', 'no-store, private');
                break;
            }
        }
        return $response;
    }
}
