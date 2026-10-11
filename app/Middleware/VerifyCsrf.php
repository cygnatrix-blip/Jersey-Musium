<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class VerifyCsrf
{
    /** Server-to-server endpoints authenticated by HMAC signature instead of CSRF tokens. */
    private const EXCEPT = ['/webhooks/'];

    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if ($request->isPost()) {
            foreach (self::EXCEPT as $prefix) {
                if (str_starts_with($request->path(), $prefix)) {
                    return $next($request);
                }
            }
            if (!Csrf::verify($request)) {
                throw new HttpException(419);
            }
        }
        return $next($request);
    }
}
