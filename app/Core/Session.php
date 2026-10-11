<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened native PHP session: HttpOnly, SameSite=Lax, Secure (on HTTPS),
 * strict mode, ID regeneration on privilege change, and one-request flash data.
 */
final class Session
{
    private static ?Session $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cfg = config('app.session');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) ($cfg['lifetime'] * 60));
        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $cfg['secure'] && request()->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Age flash data: what was "new" last request becomes readable "old" now.
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public function hasNewFlash(string $key): bool
    {
        return isset($_SESSION['_flash_new'][$key]);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_new'][$key] ?? $_SESSION['_flash_old'][$key] ?? $default;
    }

    /** Keep current flash for one more request (used when an AJAX call sneaks in). */
    public function reflash(): void
    {
        $_SESSION['_flash_new'] = array_merge($_SESSION['_flash_old'] ?? [], $_SESSION['_flash_new'] ?? []);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public function id(): string
    {
        return session_id();
    }
}
