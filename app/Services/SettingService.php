<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Key/value store settings editable from Admin → Settings.
 * Loaded once per request; DEFAULTS apply when a key has never been saved.
 * Secrets (API keys, DB passwords) are NOT stored here – they live in .env.
 */
final class SettingService
{
    public const DEFAULTS = [
        // Store identity
        'store_name'              => 'Jersey Museum',
        'store_tagline'           => 'Authentic Football Jerseys Online',
        'store_email'             => 'support@jerseymuseum.com',
        'store_phone'             => '+91 90000 00000',
        'store_address'           => 'Kolkata, West Bengal, India',
        'store_gstin'             => '',
        'store_state'             => 'West Bengal',
        'announcement'            => 'FREE shipping on orders above ₹999 · Easy 7-day returns',
        'social_instagram'        => '',
        'social_facebook'         => '',
        'social_x'                => '',
        'social_youtube'          => '',
        // Shipping & checkout
        'free_shipping_threshold' => '999',
        'shipping_fee'            => '79',
        'delivery_days_min'       => '3',
        'delivery_days_max'       => '7',
        'cod_enabled'             => '1',
        'cod_fee'                 => '49',
        'cod_max_amount'          => '5000',
        'razorpay_enabled'        => '1',
        'cashfree_enabled'        => '0',
        'pending_payment_expiry_minutes' => '30',
        // Returns
        'return_window_days'      => '7',
        // Experience
        'loader_enabled'          => '1',
        'loader_frequency'        => 'session',   // session | always
        'low_stock_threshold'     => '5',
        // SEO
        'home_meta_title'         => 'Buy Football Jerseys Online in India',
        'home_meta_description'   => 'Shop club & national team football jerseys, retro kits and custom name-number jerseys. Fast delivery across India, COD & easy returns.',
        'google_site_verification' => '',
        'ga4_measurement_id'      => '',
    ];

    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$cache[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function all(): array
    {
        self::load();
        return array_merge(self::DEFAULTS, self::$cache);
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue; // whitelist only known keys
            }
            db()->execute(
                'INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2',
                ['k' => $key, 'v' => (string) $value, 'v2' => (string) $value]
            );
        }
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        try {
            $rows = db()->select('SELECT `key`, `value` FROM settings');
            self::$cache = array_column($rows, 'value', 'key');
        } catch (Throwable) {
            self::$cache = []; // DB not ready (e.g. during install) – fall back to defaults
        }
    }
}
