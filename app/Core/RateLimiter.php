<?php

declare(strict_types=1);

namespace App\Core;

/** Fixed-window rate limiter backed by the `rate_limits` table (works across PHP workers). */
final class RateLimiter
{
    /** Records a hit; returns false when the key has exceeded $max hits within $decaySeconds. */
    public static function hit(string $key, int $max, int $decaySeconds): bool
    {
        $key = substr(hash('sha256', $key), 0, 64);
        $now = time();
        db()->execute(
            'INSERT INTO rate_limits (`key`, hits, reset_at) VALUES (:k, 1, :r)
             ON DUPLICATE KEY UPDATE
               hits = IF(reset_at < :now1, 1, hits + 1),
               reset_at = IF(reset_at < :now2, :r2, reset_at)',
            ['k' => $key, 'r' => $now + $decaySeconds, 'now1' => $now, 'now2' => $now, 'r2' => $now + $decaySeconds]
        );
        $hits = (int) db()->scalar('SELECT hits FROM rate_limits WHERE `key` = :k', ['k' => $key]);
        return $hits <= $max;
    }

    public static function clear(string $key): void
    {
        db()->execute('DELETE FROM rate_limits WHERE `key` = :k', ['k' => substr(hash('sha256', $key), 0, 64)]);
    }
}
