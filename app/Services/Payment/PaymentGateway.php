<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Request;

/**
 * Strategy interface implemented by every online payment provider.
 * Adding a new gateway (PhonePe, Stripe, ...) = implement this + register it in PaymentService::gateway().
 */
interface PaymentGateway
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Create the order on the gateway side.
     * @return array{gateway_order_id:string, payment_session_id?:string, raw:array}
     */
    public function createOrder(array $order, array $payment, string $attemptId): array;

    /**
     * Verify the customer's browser callback against the gateway (never trust the browser alone).
     * @return array{paid:bool, gateway_payment_id:?string, method:?string, raw:array, error:?string}
     */
    public function verifyPayment(array $payment, Request $request): array;

    /** Authenticate an incoming webhook (HMAC signature). */
    public function verifyWebhookSignature(string $rawBody, Request $request): bool;

    /**
     * Normalise a webhook payload.
     * @return array{event_id:string, type:string, gateway_order_id:?string, gateway_payment_id:?string,
     *               gateway_refund_id:?string, refund_number:?string, amount_paise:?int, method:?string, error:?string}|null
     *   type ∈ payment.success | payment.failed | refund.processed | refund.failed | ignore
     */
    public function parseWebhook(array $payload, string $rawBody, Request $request): ?array;

    /**
     * Initiate a refund.
     * @return array{status:string, gateway_refund_id:?string, raw:array, error:?string}  status ∈ processing|processed|failed
     */
    public function refund(array $payment, int $amountPaise, string $refundNumber, string $note): array;

    /** Data the checkout page needs to open the gateway's JS checkout. */
    public function checkoutData(array $order, array $payment): array;
}
