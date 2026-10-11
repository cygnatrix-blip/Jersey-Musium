<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\HttpClient;
use App\Core\Request;
use RuntimeException;

/**
 * Razorpay Standard Checkout.
 * Flow: server creates Razorpay Order → checkout.js collects payment → browser posts
 * razorpay_payment_id/order_id/signature → we verify HMAC + fetch payment from API → mark paid.
 * Webhooks (payment.captured / payment.failed / refund.processed / refund.failed) are the
 * source of truth if the browser never returns.
 * Docs: https://razorpay.com/docs/payments/server-integration/php/
 */
final class RazorpayGateway implements PaymentGateway
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('payment.razorpay');
    }

    public function name(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return $this->cfg['key_id'] !== '' && $this->cfg['key_secret'] !== '';
    }

    private function api(string $method, string $path, ?array $body = null): array
    {
        $res = HttpClient::request($method, $this->cfg['base_url'] . $path, [], $body, $this->cfg['key_id'] . ':' . $this->cfg['key_secret']);
        logger()->payment("razorpay $method $path", ['status' => $res['status'], 'body' => $res['body']]);
        return $res;
    }

    public function createOrder(array $order, array $payment, string $attemptId): array
    {
        $res = $this->api('POST', '/orders', [
            'amount'   => paise($order['grand_total']),
            'currency' => 'INR',
            'receipt'  => $attemptId,
            'notes'    => ['order_number' => $order['order_number']],
        ]);
        if ($res['status'] >= 300 || empty($res['body']['id'])) {
            throw new RuntimeException('Could not start Razorpay payment: ' . ($res['body']['error']['description'] ?? 'gateway error'));
        }
        return ['gateway_order_id' => $res['body']['id'], 'raw' => $res['body']];
    }

    public function checkoutData(array $order, array $payment): array
    {
        return [
            'gateway'  => 'razorpay',
            'script'   => $this->cfg['checkout_js'],
            'options'  => [
                'key'         => $this->cfg['key_id'],
                'amount'      => paise($payment['amount']),
                'currency'    => 'INR',
                'name'        => setting('store_name'),
                'description' => 'Order ' . $order['order_number'],
                'image'       => url('/assets/img/logo-mark.svg'),
                'order_id'    => $payment['gateway_order_id'],
                'prefill'     => ['name' => $order['customer_name'], 'email' => $order['customer_email'], 'contact' => $order['customer_phone']],
                'notes'       => ['order_number' => $order['order_number']],
                'theme'       => ['color' => '#E11D48'],
            ],
            'verify_url' => '/payment/razorpay/verify',
        ];
    }

    public function verifyPayment(array $payment, Request $request): array
    {
        $paymentId = (string) $request->input('razorpay_payment_id');
        $orderId = (string) $request->input('razorpay_order_id');
        $signature = (string) $request->input('razorpay_signature');

        if ($orderId !== $payment['gateway_order_id'] || $paymentId === '' || $signature === '') {
            return ['paid' => false, 'gateway_payment_id' => $paymentId ?: null, 'method' => null, 'raw' => [], 'error' => 'Payment response mismatch'];
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->cfg['key_secret']);
        if (!hash_equals($expected, $signature)) {
            return ['paid' => false, 'gateway_payment_id' => $paymentId, 'method' => null, 'raw' => [], 'error' => 'Signature verification failed'];
        }

        // Defence in depth: confirm status & amount with the API, capture if only authorized.
        $res = $this->api('GET', '/payments/' . rawurlencode($paymentId));
        $p = $res['body'];
        if (($p['order_id'] ?? null) !== $orderId || (int) ($p['amount'] ?? 0) !== paise($payment['amount'])) {
            return ['paid' => false, 'gateway_payment_id' => $paymentId, 'method' => $p['method'] ?? null, 'raw' => $p, 'error' => 'Amount/order mismatch'];
        }
        if (($p['status'] ?? '') === 'authorized') {
            $cap = $this->api('POST', '/payments/' . rawurlencode($paymentId) . '/capture', ['amount' => paise($payment['amount']), 'currency' => 'INR']);
            $p = $cap['body'] ?: $p;
        }
        $paid = ($p['status'] ?? '') === 'captured';
        return ['paid' => $paid, 'gateway_payment_id' => $paymentId, 'method' => $p['method'] ?? null, 'raw' => $p, 'error' => $paid ? null : ($p['error_description'] ?? 'Payment not captured')];
    }

    public function verifyWebhookSignature(string $rawBody, Request $request): bool
    {
        $sig = (string) $request->header('X-Razorpay-Signature');
        if ($this->cfg['webhook_secret'] === '' || $sig === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $rawBody, $this->cfg['webhook_secret']), $sig);
    }

    public function parseWebhook(array $payload, string $rawBody, Request $request): ?array
    {
        $event = $payload['event'] ?? '';
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $rawBody));
        $pay = $payload['payload']['payment']['entity'] ?? [];
        $ref = $payload['payload']['refund']['entity'] ?? [];

        $base = ['event_id' => $eventId, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'gateway_refund_id' => null,
                 'refund_number' => null, 'amount_paise' => null, 'method' => null, 'error' => null];

        return match ($event) {
            'payment.captured', 'order.paid' => ['type' => 'payment.success', 'gateway_order_id' => $pay['order_id'] ?? null,
                'gateway_payment_id' => $pay['id'] ?? null, 'amount_paise' => isset($pay['amount']) ? (int) $pay['amount'] : null, 'method' => $pay['method'] ?? null] + $base,
            'payment.failed' => ['type' => 'payment.failed', 'gateway_order_id' => $pay['order_id'] ?? null,
                'gateway_payment_id' => $pay['id'] ?? null, 'error' => $pay['error_description'] ?? 'Payment failed'] + $base,
            'refund.processed' => ['type' => 'refund.processed', 'gateway_refund_id' => $ref['id'] ?? null,
                'refund_number' => $ref['receipt'] ?? ($ref['notes']['refund_number'] ?? null), 'amount_paise' => isset($ref['amount']) ? (int) $ref['amount'] : null] + $base,
            'refund.failed' => ['type' => 'refund.failed', 'gateway_refund_id' => $ref['id'] ?? null,
                'refund_number' => $ref['receipt'] ?? ($ref['notes']['refund_number'] ?? null), 'error' => 'Refund failed at gateway'] + $base,
            default => ['type' => 'ignore'] + $base,
        };
    }

    public function refund(array $payment, int $amountPaise, string $refundNumber, string $note): array
    {
        $res = $this->api('POST', '/payments/' . rawurlencode((string) $payment['gateway_payment_id']) . '/refund', [
            'amount'  => $amountPaise,
            'speed'   => 'normal',
            'receipt' => $refundNumber,
            'notes'   => ['refund_number' => $refundNumber, 'reason' => mb_substr($note, 0, 200)],
        ]);
        $b = $res['body'];
        if ($res['status'] >= 300 || empty($b['id'])) {
            return ['status' => 'failed', 'gateway_refund_id' => null, 'raw' => $b, 'error' => $b['error']['description'] ?? 'Refund request failed'];
        }
        return [
            'status'            => ($b['status'] ?? '') === 'processed' ? 'processed' : 'processing',
            'gateway_refund_id' => $b['id'],
            'raw'               => $b,
            'error'             => null,
        ];
    }
}
