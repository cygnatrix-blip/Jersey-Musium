<?php

declare(strict_types=1);

namespace App\Core;

/** JSON-lines daily log files in storage/logs. */
final class Logger
{
    private static ?Logger $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** Separate channel for payment gateway traffic (useful for reconciliation & disputes). */
    public function payment(string $message, array $context = []): void
    {
        $this->write('PAYMENT', $message, $context, 'payment');
    }

    private function write(string $level, string $message, array $context, string $channel = 'app'): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode([
            'time'    => date('c'),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        @file_put_contents("$dir/$channel-" . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
