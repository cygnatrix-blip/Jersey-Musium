<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

final class Guest
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if ($param === 'admin' ? admin_auth()->check() : auth()->check()) {
            return redirect($param === 'admin' ? '/admin' : '/account');
        }
        return $next($request);
    }
}
