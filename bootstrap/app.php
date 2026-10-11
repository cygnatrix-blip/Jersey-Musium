<?php

declare(strict_types=1);

/*
 * Application bootstrap: shared by the web front controller (public/index.php)
 * and the CLI (bin/console).
 */

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // Fallback PSR-4 autoloader so the app also runs on hosts without Composer.
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require BASE_PATH . '/app/helpers.php';
}

// Secrets file: prefer one OUTSIDE the web root (shared hosting: the project sits in public_html),
// e.g. /home/u123/domains/example.com/jersey-musium.env, then fall back to the project's .env.
$envFile = getenv('JM_ENV_FILE') ?: (is_file(dirname(BASE_PATH) . '/jersey-musium.env') ? dirname(BASE_PATH) . '/jersey-musium.env' : BASE_PATH . '/.env');
define('ENV_FILE', $envFile);
App\Core\Env::load($envFile);

date_default_timezone_set((string) config('app.timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
