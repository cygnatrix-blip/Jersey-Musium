<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

final class AdminAuthenticate
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if (!admin_auth()->check()) {
            if ($request->method() === 'GET') {
                session()->set('admin_intended', $request->fullUrl());
            }
            return redirect('/admin/login')->with('info', 'Please sign in.');
        }
        return $next($request);
    }
}
