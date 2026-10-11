<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Order lifecycle (state machine):
 *
 *   pending_payment ──paid──▶ confirmed ─▶ packed ─▶ shipped ─▶ out_for_delivery ─▶ delivered ─▶ return_requested ─▶ returned
 *         │  ▲                    │           │         │              │                               │
 *         ▼  │ (late payment)     └───────────┴─────────┴──────────────┴──▶ cancelled                  └─▶ delivered (return rejected)
 *   payment_failed ─▶ cancelled
 *
 * COD orders start at "confirmed" with payment_status = cod_pending and become "paid" on delivery.
 */
final class OrderService
{
    public const TRANSITIONS = [
        'pending_payment'  => ['confirmed', 'payment_failed', 'cancelled'],
        'payment_failed'   => ['confirmed', 'cancelled'],
        'confirmed'        => ['packed', 'shipped', 'cancelled'],
        'packed'           => ['shipped', 'cancelled'],
        'shipped'          => ['out_for_delivery', 'delivered', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered'        => ['return_requested'],
        'return_requested' => ['returned', 'delivered'],
        'returned'         => [],
        'cancelled'        => [],
    ];

    public const CUSTOMER_CANCELLABLE = ['pending_payment', 'payment_failed', 'confirmed', 'packed'];

    public const STATUS_BADGE = [
        'pending_payment' => 'warning', 'payment_failed' => 'danger', 'confirmed' => 'info', 'packed' => 'info',
        'shipped' => 'primary', 'out_for_delivery' => 'primary', 'delivered' => 'success', 'cancelled' => 'muted',
        'return_requested' => 'warning', 'returned' => 'muted',
    ];

    public static function find(int $id): ?array
    {
        return db()->first('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
    }

    public static function findByNumber(string $number, ?int $userId = null): ?array
    {
        return $userId
            ? db()->first('SELECT * FROM orders WHERE order_number = :n AND user_id = :u', ['n' => $number, 'u' => $userId])
            : db()->first('SELECT * FROM orders WHERE order_number = :n', ['n' => $number]);
    }

    public static function items(int $orderId): array
    {
        return db()->select('SELECT * FROM order_items WHERE order_id = :id ORDER BY id', ['id' => $orderId]);
    }

    public static function history(int $orderId): array
    {
        return db()->select('SELECT * FROM order_status_history WHERE order_id = :id ORDER BY created_at, id', ['id' => $orderId]);
    }

    /**
     * Converts the current bag into an order. Stock is reserved (decremented) atomically under
     * row locks so two customers can never buy the last unit twice.
     */
    public static function place(int $userId, array $address, string $method, ?string $notes = null): array
    {
        $user = auth()->user();
        $cartSvc = new CartService();
        $summary = $cartSvc->summary($method);
        $items = $summary['items'];
        $t = $summary['totals'];

        if (!$items) {
            throw new RuntimeException('Your bag is empty.');
        }
        if ($summary['has_issue']) {
            throw new RuntimeException('Some items in your bag are unavailable. Please review your bag.');
        }
        if (!array_key_exists($method, PaymentService::availableMethods($t['total']))) {
            throw new RuntimeException('Selected payment method is not available for this order.');
        }

        $order = db()->transaction(function () use ($userId, $user, $address, $method, $notes, $items, $t, $summary, $cartSvc) {
            // 1. Lock & re-check stock
            foreach ($items as $it) {
                $stock = (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id FOR UPDATE', ['id' => $it['variant_id']]);
                if ($stock < $it['qty']) {
                    throw new RuntimeException($it['name'] . ' (' . $it['size'] . ') just went out of stock.');
                }
            }
            // 2. Lock & re-check coupon usage cap
            $coupon = $t['coupon'];
            if ($coupon) {
                $c = db()->first('SELECT * FROM coupons WHERE id = :id FOR UPDATE', ['id' => $coupon['id']]);
                if ($c['usage_limit'] !== null && $c['used_count'] >= $c['usage_limit']) {
                    throw new RuntimeException('Coupon ' . $c['code'] . ' has just been fully redeemed.');
                }
            }

            $isCod = $method === 'cod';
            $orderNumber = self::newOrderNumber();
            $orderId = db()->insert('orders', [
                'order_number'     => $orderNumber,
                'user_id'          => $userId,
                'status'           => $isCod ? 'confirmed' : 'pending_payment',
                'payment_status'   => $isCod ? 'cod_pending' : 'pending',
                'payment_method'   => $method,
                'subtotal'         => rupees($t['subtotal']),
                'discount'         => rupees($t['discount']),
                'shipping_fee'     => rupees($t['shipping']),
                'cod_fee'          => rupees($t['cod_fee']),
                'tax_total'        => rupees($t['tax']),
                'grand_total'      => rupees($t['total']),
                'coupon_id'        => $coupon['id'] ?? null,
                'coupon_code'      => $coupon['code'] ?? null,
                'customer_name'    => $address['name'],
                'customer_email'   => $user['email'],
                'customer_phone'   => $address['phone'],
                'shipping_address' => json_encode(array_intersect_key($address, array_flip(['name', 'phone', 'line1', 'line2', 'landmark', 'city', 'state', 'pincode'])), JSON_UNESCAPED_UNICODE),
                'notes'            => $notes ? mb_substr($notes, 0, 500) : null,
                'ip_address'       => request()->ip(),
                'placed_at'        => $isCod ? now() : null,
            ]);

            // 3. Lines + stock reservation (ledgered)
            foreach ($items as $it) {
                db()->insert('order_items', [
                    'order_id'      => $orderId,
                    'product_id'    => $it['product_id'],
                    'variant_id'    => $it['variant_id'],
                    'product_name'  => $it['name'],
                    'product_slug'  => $it['slug'],
                    'image'         => $it['image'],
                    'sku'           => $it['sku'],
                    'size'          => $it['size'],
                    'qty'           => $it['qty'],
                    'unit_price'    => rupees($it['unit_paise']),
                    'mrp'           => rupees($it['mrp_paise']),
                    'tax_rate'      => $it['tax_rate'],
                    'hsn_code'      => $it['hsn_code'],
                    'custom_name'   => $it['custom_name'],
                    'custom_number' => $it['custom_number'],
                    'line_total'    => rupees($it['unit_paise'] * $it['qty']),
                ]);
                InventoryService::adjust((int) $it['variant_id'], -$it['qty'], 'order', $orderNumber);
            }

            // 4. Coupon redemption
            if ($coupon) {
                db()->insert('coupon_redemptions', ['coupon_id' => $coupon['id'], 'user_id' => $userId, 'order_id' => $orderId, 'discount' => rupees($t['discount'])]);
                db()->execute('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id', ['id' => $coupon['id']]);
            }

            if ($isCod) {
                db()->insert('payments', ['order_id' => $orderId, 'gateway' => 'cod', 'gateway_order_id' => $orderNumber, 'amount' => rupees($t['total']), 'status' => 'pending', 'method' => 'cod']);
                self::bumpSoldCount($orderId);
            }
            self::log($orderId, null, $isCod ? 'confirmed' : 'pending_payment', $isCod ? 'Order placed (Cash on Delivery)' : 'Order created, awaiting payment', 'customer', $userId);

            $cartSvc->clear((int) $summary['cart']['id']);
            return self::find($orderId);
        });

        if ($method === 'cod') {
            NotificationService::orderPlaced($order);
        }
        return $order;
    }

    /** Validated status change + history + customer notification. */
    public static function transition(array $order, string $to, ?string $note = null, string $actorType = 'admin', ?int $actorId = null, array $notifyExtra = []): array
    {
        $from = $order['status'];
        if ($from === $to) {
            return $order;
        }
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new RuntimeException('Cannot move order from "' . label($from) . '" to "' . label($to) . '".');
        }
        $data = ['status' => $to];
        if ($to === 'delivered') {
            $data['delivered_at'] = $order['delivered_at'] ?? now();
            if ($order['payment_status'] === 'cod_pending') {
                $data['payment_status'] = 'paid'; // cash collected by courier
                db()->execute("UPDATE payments SET status = 'captured', paid_at = NOW() WHERE order_id = :id AND gateway = 'cod'", ['id' => $order['id']]);
            }
        }
        if ($to === 'confirmed' && !$order['placed_at']) {
            $data['placed_at'] = now();
        }
        db()->update('orders', $data, 'id = :id', ['id' => $order['id']]);
        self::log((int) $order['id'], $from, $to, $note, $actorType, $actorId);

        $order = array_merge($order, $data);
        NotificationService::orderStatus($order, $to, $notifyExtra);
        return $order;
    }

    /**
     * Cancel an order: restore stock, release coupon, and (if prepaid & paid) auto-refund in full.
     * Customers can cancel until packed; admins can also cancel shipped orders (e.g. RTO).
     */
    public static function cancel(array $order, string $reason, string $actorType, ?int $actorId = null): array
    {
        $allowed = $actorType === 'customer' ? self::CUSTOMER_CANCELLABLE : array_keys(array_filter(self::TRANSITIONS, fn ($to) => in_array('cancelled', $to, true)));
        if (!in_array($order['status'], $allowed, true)) {
            throw new RuntimeException('This order can no longer be cancelled.');
        }

        db()->transaction(function () use (&$order, $reason, $actorType, $actorId) {
            $order = db()->first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $order['id']]);
            if ($order['status'] === 'cancelled') {
                return;
            }
            self::restoreStock($order, 'order_cancel');
            if ($order['coupon_id']) {
                db()->execute('DELETE FROM coupon_redemptions WHERE order_id = :id', ['id' => $order['id']]);
                db()->execute('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = :id', ['id' => $order['coupon_id']]);
            }
            // payment_status is left as-is: unpaid stays pending; paid becomes refunded via RefundService.
            db()->update('orders', [
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 255),
            ], 'id = :id', ['id' => $order['id']]);
            db()->execute("UPDATE payments SET status = 'failed', error_message = 'Order cancelled' WHERE order_id = :id AND status IN ('created','pending')", ['id' => $order['id']]);
            self::log((int) $order['id'], $order['status'], 'cancelled', $reason, $actorType, $actorId);
        });

        $order = self::find((int) $order['id']);
        // Refund AFTER commit – never hold DB locks while calling an external API.
        if ($order['payment_status'] === 'paid' && $order['payment_method'] !== 'cod') {
            try {
                RefundService::create($order, paise($order['grand_total']) - paise($order['refunded_total']), 'Order cancelled: ' . $reason, null, 'gateway', null, $actorType === 'admin' ? $actorId : null);
            } catch (RuntimeException $e) {
                logger()->error('Auto-refund on cancel failed', ['order' => $order['order_number'], 'error' => $e->getMessage()]);
            }
        }
        NotificationService::orderStatus($order, 'cancelled');
        return self::find((int) $order['id']);
    }

    /** Cron: cancel online orders that were never paid, releasing reserved stock. */
    public static function expirePendingPayments(): int
    {
        $minutes = max(10, (int) setting('pending_payment_expiry_minutes'));
        $rows = db()->select(
            "SELECT * FROM orders WHERE status IN ('pending_payment','payment_failed') AND created_at < (NOW() - INTERVAL $minutes MINUTE) LIMIT 200"
        );
        foreach ($rows as $o) {
            try {
                self::cancel($o, 'Payment not completed in time', 'system');
            } catch (\Throwable $e) {
                logger()->error('Expire order failed', ['order' => $o['order_number'], 'error' => $e->getMessage()]);
            }
        }
        return count($rows);
    }

    public static function restoreStock(array $order, string $reason): void
    {
        foreach (self::items((int) $order['id']) as $it) {
            InventoryService::adjust((int) $it['variant_id'], (int) $it['qty'], $reason, $order['order_number']);
        }
    }

    public static function bumpSoldCount(int $orderId): void
    {
        db()->execute(
            'UPDATE products p JOIN (SELECT product_id, SUM(qty) q FROM order_items WHERE order_id = :id GROUP BY product_id) x
                 ON x.product_id = p.id SET p.sold_count = p.sold_count + x.q',
            ['id' => $orderId]
        );
    }

    public static function log(int $orderId, ?string $from, string $to, ?string $note, string $actorType = 'system', ?int $actorId = null): void
    {
        db()->insert('order_status_history', [
            'order_id' => $orderId, 'from_status' => $from, 'to_status' => $to,
            'note' => $note ? mb_substr($note, 0, 500) : null, 'actor_type' => $actorType, 'actor_id' => $actorId,
        ]);
    }

    private static function newOrderNumber(): string
    {
        // JM + yymmdd + 6 random base-32 chars → e.g. JM261011K7Q2XD (unguessable, sortable by day)
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $rand = '';
            for ($i = 0; $i < 6; $i++) {
                $rand .= $alphabet[random_int(0, 31)];
            }
            $number = 'JM' . date('ymd') . $rand;
        } while (db()->scalar('SELECT 1 FROM orders WHERE order_number = :n', ['n' => $number]));
        return $number;
    }

    public static function address(array $order): array
    {
        return json_decode((string) $order['shipping_address'], true) ?: [];
    }
}
