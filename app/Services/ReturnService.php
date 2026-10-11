<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Return / exchange (RMA) lifecycle:
 *   requested → approved → pickup_scheduled → picked_up → received → refunded | exchanged → closed
 *            ↘ rejected
 */
final class ReturnService
{
    public const REASONS = [
        'size_issue'      => 'Size doesn\'t fit',
        'damaged'         => 'Product damaged / defective',
        'wrong_item'      => 'Received wrong item',
        'quality'         => 'Quality not as expected',
        'not_as_shown'    => 'Looks different from the photos',
        'other'           => 'Other',
    ];

    public const TRANSITIONS = [
        'requested'        => ['approved', 'rejected'],
        'approved'         => ['pickup_scheduled', 'picked_up', 'received', 'rejected'],
        'pickup_scheduled' => ['picked_up', 'received'],
        'picked_up'        => ['received'],
        'received'         => ['exchanged', 'closed'],   // "refunded" is set by RefundService
        'refunded'         => ['closed'],
        'exchanged'        => ['closed'],
        'rejected'         => [],
        'closed'           => [],
    ];

    private const OPEN = ['requested', 'approved', 'pickup_scheduled', 'picked_up', 'received'];

    public static function windowOpen(array $order): bool
    {
        if (!in_array($order['status'], ['delivered', 'return_requested'], true) || !$order['delivered_at']) {
            return false;
        }
        return strtotime($order['delivered_at']) + (int) setting('return_window_days') * 86400 >= time();
    }

    /** Order items that still have returnable quantity. Customised (printed) jerseys are excluded. */
    public static function returnableItems(array $order): array
    {
        if (!self::windowOpen($order)) {
            return [];
        }
        $items = OrderService::items((int) $order['id']);
        $out = [];
        foreach ($items as $it) {
            if ($it['custom_name'] || $it['custom_number']) {
                continue;
            }
            $used = (int) db()->scalar("SELECT COALESCE(SUM(qty), 0) FROM returns WHERE order_item_id = :i AND status <> 'rejected'", ['i' => $it['id']]);
            if ($it['qty'] - $used > 0) {
                $it['returnable_qty'] = $it['qty'] - $used;
                $out[] = $it;
            }
        }
        return $out;
    }

    public static function request(array $order, int $itemId, int $qty, string $type, string $reason, ?string $details, ?string $exchangeSize, ?string $refundUpi): array
    {
        $item = null;
        foreach (self::returnableItems($order) as $it) {
            if ((int) $it['id'] === $itemId) {
                $item = $it;
            }
        }
        if (!$item) {
            throw new RuntimeException('This item is not eligible for return.');
        }
        if ($qty < 1 || $qty > $item['returnable_qty']) {
            throw new RuntimeException('Invalid quantity.');
        }
        if (!isset(self::REASONS[$reason])) {
            throw new RuntimeException('Please choose a reason.');
        }
        if ($order['payment_method'] === 'cod' && $type === 'return' && !$refundUpi) {
            throw new RuntimeException('Please enter a UPI ID for your refund (Cash on Delivery order).');
        }

        $id = db()->transaction(function () use ($order, $item, $qty, $type, $reason, $details, $exchangeSize, $refundUpi) {
            $id = db()->insert('returns', [
                'rma_number'    => 'RMA' . strtoupper(bin2hex(random_bytes(4))),
                'order_id'      => $order['id'],
                'order_item_id' => $item['id'],
                'user_id'       => $order['user_id'],
                'type'          => $type === 'exchange' ? 'exchange' : 'return',
                'qty'           => $qty,
                'reason'        => $reason,
                'details'       => $details ? mb_substr($details, 0, 1000) : null,
                'exchange_size' => $type === 'exchange' ? $exchangeSize : null,
                'refund_upi'    => $refundUpi ? mb_substr($refundUpi, 0, 80) : null,
            ]);
            if ($order['status'] === 'delivered') {
                OrderService::transition($order, 'return_requested', ucfirst($type) . ' requested for ' . $item['product_name'], 'customer', (int) $order['user_id']);
            }
            return $id;
        });
        return db()->first('SELECT * FROM returns WHERE id = :id', ['id' => $id]);
    }

    public static function updateStatus(array $return, string $to, ?string $note, ?int $adminId): void
    {
        if (!in_array($to, self::TRANSITIONS[$return['status']] ?? [], true)) {
            throw new RuntimeException('Cannot move return from "' . label($return['status']) . '" to "' . label($to) . '".');
        }
        db()->transaction(function () use ($return, $to, $note, $adminId) {
            db()->update('returns', ['status' => $to, 'admin_note' => $note ?: $return['admin_note']], 'id = :id', ['id' => $return['id']]);
            if ($to === 'received') {
                $item = db()->first('SELECT * FROM order_items WHERE id = :id', ['id' => $return['order_item_id']]);
                InventoryService::adjust((int) $item['variant_id'], (int) $return['qty'], 'return', $return['rma_number'], $adminId);
            }
            $order = OrderService::find((int) $return['order_id']);
            OrderService::log((int) $order['id'], $order['status'], $order['status'], "Return {$return['rma_number']} → " . label($to) . ($note ? ": $note" : ''), 'admin', $adminId);
            self::syncOrderStatus((int) $return['order_id']);
        });
        $return = db()->first('SELECT * FROM returns WHERE id = :id', ['id' => $return['id']]);
        NotificationService::returnUpdate($return, OrderService::find((int) $return['order_id']));
    }

    /** Keep the order status consistent with its returns. */
    public static function syncOrderStatus(int $orderId): void
    {
        $order = OrderService::find($orderId);
        if (!in_array($order['status'], ['delivered', 'return_requested', 'returned'], true)) {
            return;
        }
        $returns = db()->select('SELECT * FROM returns WHERE order_id = :id', ['id' => $orderId]);
        $open = array_filter($returns, fn ($r) => in_array($r['status'], self::OPEN, true));
        $returnedQty = array_sum(array_map(fn ($r) => in_array($r['status'], ['refunded', 'closed'], true) && $r['type'] === 'return' ? (int) $r['qty'] : 0, $returns));
        $totalQty = (int) db()->scalar('SELECT SUM(qty) FROM order_items WHERE order_id = :id', ['id' => $orderId]);

        $target = $open ? 'return_requested' : ($returnedQty >= $totalQty ? 'returned' : 'delivered');
        if ($target !== $order['status']) {
            db()->update('orders', ['status' => $target], 'id = :id', ['id' => $orderId]);
            OrderService::log($orderId, $order['status'], $target, 'Updated from return status', 'system');
        }
    }
}
