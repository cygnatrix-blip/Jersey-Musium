<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/** Usage: 'can:orders.manage' */
final class Can
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        if (!$param || !admin_auth()->can($param)) {
            throw new HttpException(403, 'You do not have permission to access this section.');
        }
        return $next($request);
    }
}
