<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Every stock change goes through here so the inventory_movements ledger is always complete. */
final class InventoryService
{
    public static function adjust(int $variantId, int $delta, string $reason, ?string $reference = null, ?int $adminId = null): void
    {
        if ($delta === 0) {
            return;
        }
        $affected = db()->execute(
            'UPDATE product_variants SET stock = stock + :d WHERE id = :id AND stock + :d2 >= 0',
            ['d' => $delta, 'id' => $variantId, 'd2' => $delta]
        );
        if ($affected === 0) {
            throw new RuntimeException('Insufficient stock for this operation.');
        }
        db()->insert('inventory_movements', [
            'variant_id' => $variantId, 'delta' => $delta, 'reason' => $reason, 'reference' => $reference, 'admin_id' => $adminId,
        ]);
    }

    /** Admin sets absolute stock – recorded as a manual delta. */
    public static function setStock(int $variantId, int $newStock, ?int $adminId): void
    {
        $current = (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $variantId]);
        self::adjust($variantId, max(0, $newStock) - $current, 'manual', 'admin', $adminId);
    }

    public static function lowStock(int $limit = 10): array
    {
        return db()->select(
            "SELECT v.id, v.size, v.stock, v.sku, p.id AS product_id, p.name FROM product_variants v
               JOIN products p ON p.id = v.product_id
              WHERE v.is_active = 1 AND p.is_active = 1 AND p.deleted_at IS NULL AND v.stock <= v.low_stock_threshold
              ORDER BY v.stock ASC LIMIT $limit"
        );
    }
}
