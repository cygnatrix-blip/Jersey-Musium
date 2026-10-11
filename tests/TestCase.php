<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base
{
    /** Create a product with one variant per size; returns [productId, [size => variantId]]. */
    protected function makeProduct(array $overrides = [], array $stock = ['M' => 10]): array
    {
        static $n = 0;
        $n++;
        $catId = (int) db()->scalar("SELECT id FROM categories WHERE slug = 'club-jerseys'");
        $data = $overrides + [
            'category_id' => $catId, 'name' => "Test Jersey $n", 'slug' => 'test-jersey-' . $n . '-' . bin2hex(random_bytes(2)),
            'sku' => 'TST' . $n . strtoupper(bin2hex(random_bytes(2))), 'mrp' => 1999, 'price' => 999, 'tax_rate' => 5,
            'team' => 'Test FC', 'allow_customization' => 1, 'customization_price' => 249,
        ];
        $pid = db()->insert('products', $data);
        $variants = [];
        foreach ($stock as $size => $qty) {
            $variants[$size] = db()->insert('product_variants', ['product_id' => $pid, 'size' => $size, 'sku' => $data['sku'] . "-$size", 'stock' => $qty]);
        }
        return [$pid, $variants];
    }

    protected function makeUser(): int
    {
        return db()->insert('users', [
            'name' => 'Unit Tester', 'email' => 'u' . bin2hex(random_bytes(4)) . '@example.com', 'phone' => '9830012345',
            'password_hash' => password_hash('Football123', PASSWORD_DEFAULT),
        ]);
    }

    /** Cart line in the shape PricingService expects. */
    protected function line(int $unitRupees, int $qty, float $rate = 5, int $mrpRupees = 0, ?int $categoryId = null): array
    {
        return [
            'unit_paise' => $unitRupees * 100, 'mrp_paise' => ($mrpRupees ?: $unitRupees) * 100, 'qty' => $qty,
            'tax_rate' => $rate, 'category_id' => $categoryId ?? (int) db()->scalar("SELECT id FROM categories WHERE slug = 'club-jerseys'"),
        ];
    }
}
