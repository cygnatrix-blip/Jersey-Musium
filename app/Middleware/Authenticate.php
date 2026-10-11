<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/** Customer must be logged in. Remembers the intended URL for after login. */
final class Authenticate
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if (!auth()->check()) {
            if ($request->wantsJson()) {
                return json(['ok' => false, 'message' => 'Please log in to continue.', 'login' => '/login'], 401);
            }
            if ($request->method() === 'GET') {
                session()->set('intended', $request->fullUrl());
            }
            return redirect('/login')->with('info', 'Please log in to continue.');
        }
        return $next($request);
    }
}
