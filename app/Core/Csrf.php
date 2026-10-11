<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = session()->get('_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            session()->set('_csrf', $token);
        }
        return $token;
    }

    public static function verify(Request $request): bool
    {
        $sent = (string) ($request->input('_token') ?? $request->header('X-CSRF-TOKEN') ?? '');
        $token = (string) session()->get('_csrf', '');
        return $token !== '' && $sent !== '' && hash_equals($token, $sent);
    }
}
