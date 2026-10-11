<?php

declare(strict_types=1);

namespace App\Core;

/** Dot-notation access to config/*.php files: Config::get('payment.razorpay.key_id'). */
final class Config
{
    private static array $items = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!array_key_exists($file, self::$items)) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            self::$items[$file] = is_file($path) ? require $path : [];
        }
        $value = self::$items[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
