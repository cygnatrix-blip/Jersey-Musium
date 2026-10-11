<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Services\Payment\CashfreeGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\RazorpayGateway;
use RuntimeException;
use Throwable;

/**
 * Orchestrates online payments independent of the provider.
 * Paid-state changes are idempotent and guarded by row locks, because the browser callback
 * and the webhook for the same payment can arrive at the same time.
 */
final class PaymentService
{
    public static function gateway(string $name): PaymentGateway
    {
        return match ($name) {
            'razorpay' => new RazorpayGateway(),
            'cashfree' => new CashfreeGateway(),
            default    => throw new RuntimeException("Unknown payment gateway [$name]"),
        };
    }

    /** Payment options available at checkout for an order total (in paise). */
    public static function availableMethods(int $totalPaise): array
    {
        $m = [];
        if (setting('razorpay_enabled') === '1' && self::gateway('razorpay')->isConfigured()) {
            $m['razorpay'] = ['label' => 'UPI / Cards / NetBanking / Wallets', 'sub' => 'Secured by Razorpay'];
        }
        if (setting('cashfree_enabled') === '1' && self::gateway('cashfree')->isConfigured()) {
            $m['cashfree'] = ['label' => 'Pay Online (UPI, Cards, EMI)', 'sub' => 'Secured by Cashfree'];
        }
        if (setting('cod_enabled') === '1' && $totalPaise <= paise(setting('cod_max_amount'))) {
            $m['cod'] = ['label' => 'Cash on Delivery', 'sub' => (float) setting('cod_fee') > 0 ? '+' . money(setting('cod_fee')) . ' COD handling fee' : 'Pay when it arrives'];
        }
        return $m;
    }

    /** Create a fresh gateway order (each retry is a new attempt with a unique id). */
    public static function start(array $order): array
    {
        if (!in_array($order['status'], ['pending_payment', 'payment_failed'], true)) {
            throw new RuntimeException('This order does not need payment.');
        }
        $gw = self::gateway($order['payment_method']);
        $attempt = (int) db()->scalar('SELECT COUNT(*) FROM payments WHERE order_id = :id', ['id' => $order['id']]) + 1;
        $attemptId = $order['order_number'] . '-' . $attempt;

        $res = $gw->createOrder($order, [], $attemptId);
        $id = db()->insert('payments', [
            'order_id'           => $order['id'],
            'gateway'            => $gw->name(),
            'gateway_order_id'   => $res['gateway_order_id'],
            'payment_session_id' => $res['payment_session_id'] ?? null,
            'amount'             => $order['grand_total'],
            'status'             => 'created',
            'raw_response'       => json_encode($res['raw']),
        ]);
        return db()->first('SELECT * FROM payments WHERE id = :id', ['id' => $id]);
    }

    /** Handle the customer's return from the gateway. Returns the (refreshed) order. */
    public static function confirmFromBrowser(array $payment, Request $request): array
    {
        $result = self::gateway($payment['gateway'])->verifyPayment($payment, $request);
        if ($result['paid']) {
            self::markPaid($payment, $result['gateway_payment_id'], $result['method'], $result['raw'], 'customer');
        } else {
            self::markFailed($payment, (string) $result['error'], $result['gateway_payment_id']);
        }
        return OrderService::find((int) $payment['order_id']);
    }

    public static function markPaid(array $payment, ?string $gatewayPaymentId, ?string $method, array $raw, string $actor): void
    {
        $sendMail = false;
        $refundAfter = false;

        db()->transaction(function () use ($payment, $gatewayPaymentId, $method, $raw, $actor, &$sendMail, &$refundAfter) {
            $p = db()->first('SELECT * FROM payments WHERE id = :id FOR UPDATE', ['id' => $payment['id']]);
            if ($p['status'] === 'captured') {
                return; // already processed (webhook + browser race) – idempotent no-op
            }
            db()->update('payments', [
                'status' => 'captured', 'gateway_payment_id' => $gatewayPaymentId, 'method' => $method,
                'paid_at' => now(), 'error_message' => null, 'raw_response' => json_encode($raw),
            ], 'id = :id', ['id' => $p['id']]);

            $order = db()->first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $p['order_id']]);
            if (in_array($order['status'], ['pending_payment', 'payment_failed'], true)) {
                db()->update('orders', ['payment_status' => 'paid'], 'id = :id', ['id' => $order['id']]);
                $order['payment_status'] = 'paid';
                OrderService::transition($order, 'confirmed', 'Payment received via ' . ucfirst($p['gateway']) . ($method ? " ($method)" : ''), $actor === 'gateway' ? 'gateway' : 'customer', null);
                OrderService::bumpSoldCount((int) $order['id']);
                $sendMail = true;
            } elseif ($order['status'] === 'cancelled') {
                // Money arrived after the order expired → refund automatically.
                db()->update('orders', ['payment_status' => 'paid'], 'id = :id', ['id' => $order['id']]);
                OrderService::log((int) $order['id'], 'cancelled', 'cancelled', 'Late payment received on cancelled order – auto refund', 'system');
                $refundAfter = true;
            }
        });

        $order = OrderService::find((int) $payment['order_id']);
        if ($sendMail) {
            NotificationService::orderPlaced($order);
        }
        if ($refundAfter) {
            RefundService::create($order, paise($payment['amount']), 'Late payment on cancelled order', null, 'gateway');
        }
    }

    public static function markFailed(array $payment, string $error, ?string $gatewayPaymentId = null): void
    {
        db()->execute(
            "UPDATE payments SET status = 'failed', error_message = :e, gateway_payment_id = COALESCE(:gp, gateway_payment_id)
              WHERE id = :id AND status IN ('created','pending')",
            ['e' => mb_substr($error, 0, 255), 'gp' => $gatewayPaymentId, 'id' => $payment['id']]
        );
        $order = OrderService::find((int) $payment['order_id']);
        if ($order && $order['status'] === 'pending_payment') {
            OrderService::transition($order, 'payment_failed', $error, 'gateway');
        }
    }

    /**
     * Webhook entry point. Returns HTTP status to send back to the gateway.
     * 1) verify HMAC  2) store event (dedupe by event id)  3) apply  4) mark processed
     */
    public static function handleWebhook(string $gatewayName, Request $request): int
    {
        $gw = self::gateway($gatewayName);
        $raw = $request->rawBody();
        if (!$gw->verifyWebhookSignature($raw, $request)) {
            logger()->warning("Webhook signature rejected ($gatewayName)", ['ip' => $request->ip()]);
            return 401;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return 400;
        }
        $evt = $gw->parseWebhook($payload, $raw, $request);
        if (!$evt) {
            return 200;
        }

        $inserted = db()->execute(
            'INSERT IGNORE INTO webhook_events (gateway, event_id, event_type, payload) VALUES (:g, :e, :t, :p)',
            ['g' => $gatewayName, 'e' => $evt['event_id'], 't' => $payload['event'] ?? ($payload['type'] ?? $evt['type']), 'p' => $raw]
        );
        if ($inserted === 0) {
            return 200; // duplicate delivery
        }

        try {
            $status = self::applyWebhook($gatewayName, $evt);
            db()->execute('UPDATE webhook_events SET status = :s, processed_at = NOW() WHERE gateway = :g AND event_id = :e',
                ['s' => $status, 'g' => $gatewayName, 'e' => $evt['event_id']]);
            return 200;
        } catch (Throwable $e) {
            // Remove the dedupe marker so the gateway's automatic retry gets processed.
            db()->execute('DELETE FROM webhook_events WHERE gateway = :g AND event_id = :e', ['g' => $gatewayName, 'e' => $evt['event_id']]);
            logger()->error("Webhook processing failed ($gatewayName)", ['error' => $e->getMessage(), 'event' => $evt]);
            return 500;
        }
    }

    private static function applyWebhook(string $gatewayName, array $evt): string
    {
        switch ($evt['type']) {
            case 'payment.success':
            case 'payment.failed':
                $payment = db()->first('SELECT * FROM payments WHERE gateway = :g AND gateway_order_id = :o', ['g' => $gatewayName, 'o' => $evt['gateway_order_id']]);
                if (!$payment) {
                    return 'ignored';
                }
                if ($evt['type'] === 'payment.success') {
                    if ($evt['amount_paise'] !== null && $evt['amount_paise'] !== paise($payment['amount'])) {
                        throw new RuntimeException('Webhook amount mismatch for ' . $payment['gateway_order_id']);
                    }
                    self::markPaid($payment, $evt['gateway_payment_id'], $evt['method'], $evt, 'gateway');
                } else {
                    self::markFailed($payment, (string) $evt['error'], $evt['gateway_payment_id']);
                }
                return 'processed';

            case 'refund.processed':
            case 'refund.failed':
                $refund = null;
                if ($evt['refund_number']) {
                    $refund = db()->first('SELECT * FROM refunds WHERE refund_number = :n', ['n' => $evt['refund_number']]);
                }
                if (!$refund && $evt['gateway_refund_id']) {
                    $refund = db()->first('SELECT * FROM refunds WHERE gateway_refund_id = :r', ['r' => $evt['gateway_refund_id']]);
                }
                if (!$refund) {
                    return 'ignored';
                }
                $evt['type'] === 'refund.processed'
                    ? RefundService::finalize((int) $refund['id'], $evt['gateway_refund_id'])
                    : RefundService::markFailed((int) $refund['id'], (string) $evt['error']);
                return 'processed';
        }
        return 'ignored';
    }
}
