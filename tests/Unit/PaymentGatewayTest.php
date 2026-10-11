<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Services\Payment\CashfreeGateway;
use App\Services\Payment\RazorpayGateway;
use Tests\TestCase;

/** Signature verification & webhook normalisation – the security core of the payment integration. */
final class PaymentGatewayTest extends TestCase
{
    private function req(array $headers, string $body = '', array $post = []): Request
    {
        $server = [];
        foreach ($headers as $k => $v) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
        return new Request('POST', '/webhooks/x', [], $post, [], $server, $body);
    }

    public function testRazorpayWebhookSignature(): void
    {
        $gw = new RazorpayGateway();
        $body = '{"event":"payment.captured"}';
        $good = hash_hmac('sha256', $body, 'whsec_unit');
        $this->assertTrue($gw->verifyWebhookSignature($body, $this->req(['X-Razorpay-Signature' => $good])));
        $this->assertFalse($gw->verifyWebhookSignature($body . ' ', $this->req(['X-Razorpay-Signature' => $good])), 'tampered body');
        $this->assertFalse($gw->verifyWebhookSignature($body, $this->req(['X-Razorpay-Signature' => 'deadbeef'])), 'forged signature');
        $this->assertFalse($gw->verifyWebhookSignature($body, $this->req([])), 'missing signature');
    }

    public function testRazorpayWebhookNormalisation(): void
    {
        $gw = new RazorpayGateway();
        $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_1', 'order_id' => 'order_1', 'amount' => 149900, 'method' => 'upi']]]];
        $e = $gw->parseWebhook($payload, json_encode($payload), $this->req(['X-Razorpay-Event-Id' => 'evt_9']));
        $this->assertSame(['payment.success', 'evt_9', 'order_1', 'pay_1', 149900, 'upi'], [$e['type'], $e['event_id'], $e['gateway_order_id'], $e['gateway_payment_id'], $e['amount_paise'], $e['method']]);

        $refund = ['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => ['id' => 'rfnd_1', 'amount' => 5000, 'receipt' => 'RF123']]]];
        $e = $gw->parseWebhook($refund, json_encode($refund), $this->req([]));
        $this->assertSame(['refund.processed', 'rfnd_1', 'RF123'], [$e['type'], $e['gateway_refund_id'], $e['refund_number']]);
        $this->assertSame('ignore', $gw->parseWebhook(['event' => 'invoice.paid'], '{}', $this->req([]))['type']);
    }

    public function testRazorpayCheckoutSignatureMismatchIsRejectedWithoutNetwork(): void
    {
        $gw = new RazorpayGateway();
        $res = $gw->verifyPayment(['gateway_order_id' => 'order_A', 'amount' => '10.00'],
            $this->req([], '', ['razorpay_order_id' => 'order_A', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => 'forged']));
        $this->assertFalse($res['paid']);
        $this->assertSame('Signature verification failed', $res['error']);

        $res = $gw->verifyPayment(['gateway_order_id' => 'order_A', 'amount' => '10.00'],
            $this->req([], '', ['razorpay_order_id' => 'order_OTHER', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => 'x']));
        $this->assertFalse($res['paid'], 'a payment for a different order must never confirm this one');
    }

    public function testCashfreeSignatureAndReplayWindow(): void
    {
        $gw = new CashfreeGateway();
        $body = '{"type":"PAYMENT_SUCCESS_WEBHOOK"}';
        $ts = (string) (time() * 1000);
        $sig = base64_encode(hash_hmac('sha256', $ts . $body, 'cf_unit_secret', true));
        $this->assertTrue($gw->verifyWebhookSignature($body, $this->req(['x-webhook-signature' => $sig, 'x-webhook-timestamp' => $ts])));

        $old = (string) ((time() - 3600) * 1000);
        $oldSig = base64_encode(hash_hmac('sha256', $old . $body, 'cf_unit_secret', true));
        $this->assertFalse($gw->verifyWebhookSignature($body, $this->req(['x-webhook-signature' => $oldSig, 'x-webhook-timestamp' => $old])), 'replayed (1h old) webhook');
        $this->assertFalse($gw->verifyWebhookSignature($body, $this->req(['x-webhook-signature' => $sig, 'x-webhook-timestamp' => (string) ((int) $ts + 1)])), 'timestamp tampered');
    }

    public function testCashfreeWebhookNormalisation(): void
    {
        $gw = new CashfreeGateway();
        $p = ['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => ['order' => ['order_id' => 'JM1-1'], 'payment' => ['cf_payment_id' => 77, 'payment_amount' => 1499.5, 'payment_group' => 'upi']]];
        $e = $gw->parseWebhook($p, json_encode($p), $this->req([]));
        $this->assertSame(['payment.success', 'JM1-1', '77', 149950], [$e['type'], $e['gateway_order_id'], $e['gateway_payment_id'], $e['amount_paise']]);
        $drop = ['type' => 'PAYMENT_USER_DROPPED_WEBHOOK', 'data' => ['order' => ['order_id' => 'JM1-1'], 'payment' => []]];
        $this->assertSame('payment.failed', $gw->parseWebhook($drop, json_encode($drop), $this->req([]))['type']);
    }
}
