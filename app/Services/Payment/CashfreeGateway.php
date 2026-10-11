<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\HttpClient;
use App\Core\Request;
use RuntimeException;

/**
 * Cashfree Payment Gateway (PG API version 2023-08-01).
 * Flow: server creates Cashfree order → gets payment_session_id → cashfree.js v3 checkout
 * (redirect) → customer returns to /payment/cashfree/return?order_id=… → we GET the order
 * from the API to confirm PAID. Webhooks confirm asynchronously.
 * Docs: https://www.cashfree.com/docs/api-reference/payments/latest/orders/create
 */
final class CashfreeGateway implements PaymentGateway
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('payment.cashfree');
    }

    public function name(): string
    {
        return 'cashfree';
    }

    public function isConfigured(): bool
    {
        return $this->cfg['app_id'] !== '' && $this->cfg['secret_key'] !== '';
    }

    private function api(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $res = HttpClient::request($method, $this->cfg['base_url'] . $path, [
            'x-client-id'     => $this->cfg['app_id'],
            'x-client-secret' => $this->cfg['secret_key'],
            'x-api-version'   => $this->cfg['api_version'],
        ] + $extraHeaders, $body);
        logger()->payment("cashfree $method $path", ['status' => $res['status'], 'body' => $res['body']]);
        return $res;
    }

    public function createOrder(array $order, array $payment, string $attemptId): array
    {
        $res = $this->api('POST', '/orders', [
            'order_id'         => $attemptId,
            'order_amount'     => round((float) $order['grand_total'], 2),
            'order_currency'   => 'INR',
            'customer_details' => [
                'customer_id'    => 'CUST' . $order['user_id'],
                'customer_name'  => $order['customer_name'],
                'customer_email' => $order['customer_email'],
                'customer_phone' => $order['customer_phone'],
            ],
            'order_meta' => [
                'return_url' => url('/payment/cashfree/return') . '?order_id={order_id}',
                'notify_url' => url('/webhooks/cashfree'),
            ],
            'order_note' => 'Order ' . $order['order_number'],
        ], ['x-idempotency-key' => $attemptId]);

        if ($res['status'] >= 300 || empty($res['body']['payment_session_id'])) {
            throw new RuntimeException('Could not start Cashfree payment: ' . ($res['body']['message'] ?? 'gateway error'));
        }
        return [
            'gateway_order_id'   => $res['body']['order_id'],
            'payment_session_id' => $res['body']['payment_session_id'],
            'raw'                => $res['body'],
        ];
    }

    public function checkoutData(array $order, array $payment): array
    {
        return [
            'gateway' => 'cashfree',
            'script'  => $this->cfg['checkout_js'],
            'options' => [
                'mode'             => $this->cfg['env'] === 'production' ? 'production' : 'sandbox',
                'paymentSessionId' => $payment['payment_session_id'],
            ],
        ];
    }

    /** Called on the return URL – we ask Cashfree for the truth instead of trusting query params. */
    public function verifyPayment(array $payment, Request $request): array
    {
        $res = $this->api('GET', '/orders/' . rawurlencode((string) $payment['gateway_order_id']));
        $o = $res['body'];
        $paid = ($o['order_status'] ?? '') === 'PAID' && paise($o['order_amount'] ?? 0) === paise($payment['amount']);

        $paymentId = null;
        $method = null;
        if ($paid) {
            $p = $this->api('GET', '/orders/' . rawurlencode((string) $payment['gateway_order_id']) . '/payments');
            foreach ((array) $p['body'] as $attempt) {
                if (($attempt['payment_status'] ?? '') === 'SUCCESS') {
                    $paymentId = (string) $attempt['cf_payment_id'];
                    $method = $attempt['payment_group'] ?? null;
                    break;
                }
            }
        }
        return [
            'paid'               => $paid,
            'gateway_payment_id' => $paymentId,
            'method'             => $method,
            'raw'                => $o,
            'error'              => $paid ? null : 'Payment status: ' . ($o['order_status'] ?? 'unknown'),
        ];
    }

    /** signature = base64( HMAC_SHA256( timestamp + rawBody, secretKey ) ) */
    public function verifyWebhookSignature(string $rawBody, Request $request): bool
    {
        $sig = (string) $request->header('x-webhook-signature');
        $ts = (string) $request->header('x-webhook-timestamp');
        if ($sig === '' || $ts === '' || $this->cfg['secret_key'] === '') {
            return false;
        }
        // Reject replays older than 10 minutes (timestamp is in ms).
        if (abs(time() - (int) ((int) $ts / 1000)) > 600) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $ts . $rawBody, $this->cfg['secret_key'], true));
        return hash_equals($expected, $sig);
    }

    public function parseWebhook(array $payload, string $rawBody, Request $request): ?array
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];
        $base = ['event_id' => hash('sha256', $rawBody), 'gateway_order_id' => $data['order']['order_id'] ?? ($data['refund']['order_id'] ?? null),
                 'gateway_payment_id' => isset($data['payment']['cf_payment_id']) ? (string) $data['payment']['cf_payment_id'] : null,
                 'gateway_refund_id' => null, 'refund_number' => null, 'amount_paise' => null, 'method' => $data['payment']['payment_group'] ?? null, 'error' => null];

        if ($type === 'PAYMENT_SUCCESS_WEBHOOK') {
            return ['type' => 'payment.success', 'amount_paise' => paise($data['payment']['payment_amount'] ?? 0)] + $base;
        }
        if (in_array($type, ['PAYMENT_FAILED_WEBHOOK', 'PAYMENT_USER_DROPPED_WEBHOOK'], true)) {
            return ['type' => 'payment.failed', 'error' => $data['payment']['payment_message'] ?? 'Payment failed'] + $base;
        }
        if ($type === 'REFUND_STATUS_WEBHOOK') {
            $r = $data['refund'] ?? [];
            $status = $r['refund_status'] ?? '';
            return [
                'type'              => $status === 'SUCCESS' ? 'refund.processed' : (in_array($status, ['FAILED', 'CANCELLED'], true) ? 'refund.failed' : 'ignore'),
                'gateway_refund_id' => isset($r['cf_refund_id']) ? (string) $r['cf_refund_id'] : null,
                'refund_number'     => $r['refund_id'] ?? null,
                'amount_paise'      => isset($r['refund_amount']) ? paise($r['refund_amount']) : null,
                'error'             => $r['status_description'] ?? null,
            ] + $base;
        }
        return ['type' => 'ignore'] + $base;
    }

    public function refund(array $payment, int $amountPaise, string $refundNumber, string $note): array
    {
        $res = $this->api('POST', '/orders/' . rawurlencode((string) $payment['gateway_order_id']) . '/refunds', [
            'refund_amount' => round($amountPaise / 100, 2),
            'refund_id'     => $refundNumber,
            'refund_note'   => mb_substr($note, 0, 100),
            'refund_speed'  => 'STANDARD',
        ]);
        $b = $res['body'];
        if ($res['status'] >= 300 || empty($b['refund_id'])) {
            return ['status' => 'failed', 'gateway_refund_id' => null, 'raw' => $b, 'error' => $b['message'] ?? 'Refund request failed'];
        }
        return [
            'status'            => ($b['refund_status'] ?? '') === 'SUCCESS' ? 'processed' : 'processing',
            'gateway_refund_id' => isset($b['cf_refund_id']) ? (string) $b['cf_refund_id'] : null,
            'raw'               => $b,
            'error'             => null,
        ];
    }
}
