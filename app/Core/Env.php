<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Real environment variables always win over the file,
 * so production hosts can inject secrets without a .env file.
 */
final class Env
{
    private static array $values = [];

    /** Secrets file names, in order of preference ("jersey-musium.env" = name used before the brand spelling fix). */
    public const FILE_NAMES = ['jersey-museum.env', 'jersey-musium.env'];

    /**
     * Find the secrets file: "jersey-museum.env" (or the legacy "jersey-musium.env") in one of the 4 folders
     * above $basePath, preferring a copy that is outside every public_html (not downloadable), then the
     * current name over the legacy one. Falls back to "$basePath/.env".
     */
    public static function locate(string $basePath, array $names = self::FILE_NAMES): string
    {
        $found = [];
        foreach ($names as $name) {
            $dir = dirname($basePath);
            for ($i = 0; $i < 4 && $dir !== dirname($dir); $i++, $dir = dirname($dir)) {
                if (@is_file($dir . '/' . $name)) { // @: folders outside open_basedir simply don't count
                    $found[] = $dir . '/' . $name;
                }
            }
        }
        $private = array_values(array_filter($found, static fn ($f) => !preg_match('#/public_html/#', str_replace('\\', '/', $f))));
        return $private[0] ?? $found[0] ?? $basePath . '/.env';
    }

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strpos($value, $quote, 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = self::$values[$key] ?? null;
        }
        if ($value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            ''      => $default ?? '',
            default => $value,
        };
    }
}
