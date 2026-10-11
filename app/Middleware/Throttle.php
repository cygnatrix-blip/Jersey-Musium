<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use Closure;

/** Usage: 'throttle:5,60' = max 5 POST requests per 60 seconds per IP + route. */
final class Throttle
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if ($request->isPost()) {
            [$max, $decay] = array_map('intval', explode(',', $param ?: '10,60'));
            if (!RateLimiter::hit('throttle|' . $request->ip() . '|' . $request->path(), $max, $decay)) {
                throw new HttpException(429);
            }
        }
        return $next($request);
    }
}
