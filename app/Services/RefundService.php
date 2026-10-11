<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Refunds:
 *  - gateway: refunded back to the original Razorpay/Cashfree payment (status confirmed by webhook)
 *  - manual : bank/UPI transfer done by the team (COD orders); admin records the UTR
 * An order can never be refunded more than it was paid.
 */
final class RefundService
{
    /** Remaining refundable amount (paise) – counts pending/processing refunds as committed. */
    public static function refundable(array $order): int
    {
        $committed = (float) db()->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE order_id = :id AND status IN ('pending','processing','processed')",
            ['id' => $order['id']]
        );
        $paid = in_array($order['payment_status'], ['paid', 'partially_refunded', 'refunded'], true) ? paise($order['grand_total']) : 0;
        return max(0, $paid - paise($committed));
    }

    public static function create(array $order, int $amountPaise, string $reason, ?int $returnId = null, string $mode = 'gateway', ?string $manualRef = null, ?int $adminId = null): array
    {
        if ($amountPaise <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }
        $payment = null;
        if ($mode === 'gateway') {
            if ($order['payment_method'] === 'cod') {
                throw new RuntimeException('COD orders must be refunded manually (bank / UPI transfer).');
            }
            $payment = db()->first(
                "SELECT * FROM payments WHERE order_id = :id AND status IN ('captured','partially_refunded') AND gateway <> 'cod' ORDER BY id DESC LIMIT 1",
                ['id' => $order['id']]
            ) ?? throw new RuntimeException('No captured online payment found for this order.');
        }

        $refundId = db()->transaction(function () use ($order, $amountPaise, $reason, $returnId, $mode, $manualRef, $adminId, $payment) {
            db()->first('SELECT id FROM orders WHERE id = :id FOR UPDATE', ['id' => $order['id']]); // serialise refunds per order
            if ($amountPaise > self::refundable($order)) {
                throw new RuntimeException('Refund exceeds the refundable amount of ' . money(self::refundable($order) / 100, true) . '.');
            }
            return db()->insert('refunds', [
                'refund_number'    => 'RF' . $order['order_number'] . '-' . strtoupper(bin2hex(random_bytes(2))),
                'order_id'         => $order['id'],
                'payment_id'       => $payment['id'] ?? null,
                'return_id'        => $returnId,
                'amount'           => rupees($amountPaise),
                'reason'           => mb_substr($reason, 0, 255),
                'mode'             => $mode,
                'status'           => 'pending',
                'manual_reference' => $manualRef,
                'admin_id'         => $adminId,
            ]);
        });

        $refund = db()->first('SELECT * FROM refunds WHERE id = :id', ['id' => $refundId]);

        if ($mode === 'manual') {
            self::finalize($refundId, null);
        } else {
            $res = PaymentService::gateway($payment['gateway'])->refund($payment, $amountPaise, $refund['refund_number'], $reason);
            db()->update('refunds', [
                'status'            => $res['status'] === 'failed' ? 'failed' : 'processing',
                'gateway_refund_id' => $res['gateway_refund_id'],
                'error_message'     => $res['error'],
                'raw_response'      => json_encode($res['raw']),
            ], 'id = :id', ['id' => $refundId]);
            if ($res['status'] === 'processed') {
                self::finalize($refundId, $res['gateway_refund_id']);
            }
            if ($res['status'] === 'failed') {
                throw new RuntimeException('Gateway rejected the refund: ' . $res['error']);
            }
        }
        OrderService::log((int) $order['id'], $order['status'], $order['status'], 'Refund of ' . money($amountPaise / 100, true) . " initiated ($mode)", $adminId ? 'admin' : 'system', $adminId);
        return db()->first('SELECT * FROM refunds WHERE id = :id', ['id' => $refundId]);
    }

    /** Mark refund as completed and roll totals up to order/payment/return. Idempotent. */
    public static function finalize(int $refundId, ?string $gatewayRefundId): void
    {
        $done = db()->transaction(function () use ($refundId, $gatewayRefundId) {
            $r = db()->first('SELECT * FROM refunds WHERE id = :id FOR UPDATE', ['id' => $refundId]);
            if (!$r || $r['status'] === 'processed') {
                return null;
            }
            db()->update('refunds', ['status' => 'processed', 'processed_at' => now(), 'gateway_refund_id' => $gatewayRefundId ?? $r['gateway_refund_id'], 'error_message' => null], 'id = :id', ['id' => $refundId]);

            $order = db()->first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $r['order_id']]);
            $refunded = paise($order['refunded_total']) + paise($r['amount']);
            $full = $refunded >= paise($order['grand_total']);
            db()->update('orders', [
                'refunded_total' => rupees($refunded),
                'payment_status' => $full ? 'refunded' : 'partially_refunded',
            ], 'id = :id', ['id' => $order['id']]);
            if ($r['payment_id']) {
                db()->update('payments', ['status' => $full ? 'refunded' : 'partially_refunded'], 'id = :id', ['id' => $r['payment_id']]);
            }
            if ($r['return_id']) {
                db()->update('returns', ['status' => 'refunded'], 'id = :id', ['id' => $r['return_id']]);
                ReturnService::syncOrderStatus((int) $order['id']);
            }
            return [$order, $r['amount']];
        });

        if ($done) {
            NotificationService::refundProcessed($done[0], (string) $done[1]);
        }
    }

    public static function markFailed(int $refundId, string $error): void
    {
        db()->execute("UPDATE refunds SET status = 'failed', error_message = :e WHERE id = :id AND status <> 'processed'", ['e' => mb_substr($error, 0, 255), 'id' => $refundId]);
    }
}
