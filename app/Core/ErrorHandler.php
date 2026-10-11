<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', config('app.debug') ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            // Deprecations must never take production down after a PHP upgrade – log them instead.
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                logger()->warning('Deprecated: ' . $message, ['file' => "$file:$line"]);
                return true;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::render($e)->send();
        });
    }

    public static function render(Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return Request::current()->wantsJson()
                ? Response::json(['ok' => false, 'message' => reset($e->errors)[0] ?? 'Invalid data', 'errors' => $e->errors], 422)
                : back()->withErrors($e->errors)->withInput();
        }

        $status = $e instanceof HttpException ? $e->status : 500;

        if ($status >= 500) {
            try {
                logger()->error($e->getMessage(), [
                    'exception' => $e::class,
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                    'url'       => $_SERVER['REQUEST_URI'] ?? 'cli',
                    'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 15),
                ]);
            } catch (Throwable) {
                error_log((string) $e);
            }
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            return new Response('', $status);
        }

        $message = $status >= 500 && !config('app.debug') ? 'Something went wrong on our side. Please try again.' : $e->getMessage();

        if (Request::current()->wantsJson()) {
            return Response::json(['ok' => false, 'message' => $message], $status);
        }

        if ($status === 419) {
            return back()->with('error', $message);
        }

        try {
            $layout = str_starts_with(Request::current()->path(), '/admin') ? null : 'layouts/store';
            seo()->title($status === 404 ? 'Page not found' : 'Error')->noindex();
            return view('errors/error', [
                'status'  => $status,
                'message' => $message,
                'debug'   => config('app.debug') ? (string) $e : null,
            ], $layout, $status);
        } catch (Throwable $inner) {
            // Layout itself failed (e.g. database down) – fall back to bare HTML.
            $body = '<!doctype html><meta charset="utf-8"><title>Error</title><div style="font-family:sans-serif;text-align:center;padding:10vh 1rem">'
                . '<h1>' . $status . '</h1><p>' . e($message) . '</p>'
                . (config('app.debug') ? '<pre style="text-align:left;white-space:pre-wrap">' . e((string) $e . "\n\n" . $inner) . '</pre>' : '')
                . '</div>';
            return new Response($body, $status);
        }
    }
}
