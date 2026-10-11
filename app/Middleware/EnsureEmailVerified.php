<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/** Customer must have verified their email (6-digit OTP) before ordering, reviewing or returning. */
final class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if (auth()->check() && !auth()->verified()) {
            if ($request->wantsJson()) {
                return json(['ok' => false, 'message' => 'Please verify your email to continue.', 'verify' => '/verify-email'], 403);
            }
            if ($request->method() === 'GET') {
                session()->set('intended', $request->fullUrl());
            }
            return redirect('/verify-email')->with('info', 'Please verify your email address to continue.');
        }
        return $next($request);
    }
}
