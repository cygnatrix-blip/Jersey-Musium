<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;
use App\Services\PaymentService;
use RuntimeException;

final class PaymentController extends Controller
{
    /** Payment page: creates/reuses a gateway order and opens the gateway checkout. */
    public function pay(Request $request, string $number): Response
    {
        $order = OrderService::findByNumber($number, auth()->id()) ?? throw new HttpException(404);
        if (!in_array($order['status'], ['pending_payment', 'payment_failed'], true)) {
            return redirect('/account/orders/' . $number);
        }
        seo()->title('Complete payment')->noindex();

        // Reuse a fresh unpaid attempt (<15 min) to avoid creating gateway orders on every refresh.
        $payment = db()->first(
            "SELECT * FROM payments WHERE order_id = :id AND gateway = :g AND status = 'created' AND created_at > (NOW() - INTERVAL 15 MINUTE) ORDER BY id DESC LIMIT 1",
            ['id' => $order['id'], 'g' => $order['payment_method']]
        );
        try {
            $payment ??= PaymentService::start($order);
        } catch (RuntimeException $e) {
            logger()->error('Payment start failed', ['order' => $number, 'error' => $e->getMessage()]);
            return view('store/pay', ['order' => $order, 'checkout' => null, 'error' => 'We could not reach the payment gateway. Please try again in a moment.']);
        }

        return view('store/pay', [
            'order'    => $order,
            'checkout' => PaymentService::gateway($payment['gateway'])->checkoutData($order, $payment),
            'error'    => $order['status'] === 'payment_failed' ? 'Your last payment attempt failed. Please try again.' : null,
        ]);
    }

    /** Razorpay checkout.js success handler posts here (AJAX). */
    public function razorpayVerify(Request $request): Response
    {
        $payment = db()->first(
            "SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id
              WHERE p.gateway = 'razorpay' AND p.gateway_order_id = :g AND o.user_id = :u",
            ['g' => (string) $request->input('razorpay_order_id'), 'u' => auth()->id()]
        ) ?? throw new HttpException(404);

        $order = PaymentService::confirmFromBrowser($payment, $request);
        $ok = in_array($order['payment_status'], ['paid'], true);
        return json([
            'ok'       => $ok,
            'redirect' => $ok ? '/order/success/' . $order['order_number'] : '/checkout/pay/' . $order['order_number'],
            'message'  => $ok ? 'Payment successful' : 'Payment could not be verified. If money was debited it will be auto-updated or refunded.',
        ], $ok ? 200 : 422);
    }

    /** Cashfree redirects the customer back here with ?order_id=<attempt id>. */
    public function cashfreeReturn(Request $request): Response
    {
        $payment = db()->first(
            "SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id
              WHERE p.gateway = 'cashfree' AND p.gateway_order_id = :g AND o.user_id = :u",
            ['g' => (string) $request->query('order_id'), 'u' => auth()->id()]
        ) ?? throw new HttpException(404);

        $order = PaymentService::confirmFromBrowser($payment, $request);
        return $order['payment_status'] === 'paid'
            ? redirect('/order/success/' . $order['order_number'])
            : redirect('/checkout/pay/' . $order['order_number'])->with('error', 'Payment was not completed. You can try again.');
    }

    public function success(Request $request, string $number): Response
    {
        $order = OrderService::findByNumber($number, auth()->id()) ?? throw new HttpException(404);
        seo()->title('Order placed')->noindex();
        return view('store/order-success', ['order' => $order, 'items' => OrderService::items((int) $order['id'])]);
    }

    public function webhookRazorpay(Request $request): Response
    {
        return new Response('', PaymentService::handleWebhook('razorpay', $request));
    }

    public function webhookCashfree(Request $request): Response
    {
        return new Response('', PaymentService::handleWebhook('cashfree', $request));
    }
}
