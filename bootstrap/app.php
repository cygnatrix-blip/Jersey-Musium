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

// Secrets file: "jersey-musium.env" in one of the 4 folders ABOVE the project, preferring a copy that is
// outside every public_html (i.e. not downloadable). Covers both layouts on shared hosting:
//   main domain : /home/u1/domains/example.com/public_html            → …/example.com/jersey-musium.env
//   subdomain   : /home/u1/domains/example.com/public_html/shop       → …/example.com/jersey-musium.env
// Falls back to the project's own .env (local development). JM_ENV_FILE overrides everything.
$envFile = getenv('JM_ENV_FILE') ?: App\Core\Env::locate(BASE_PATH);
define('ENV_FILE', $envFile);
App\Core\Env::load($envFile);

date_default_timezone_set((string) config('app.timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
