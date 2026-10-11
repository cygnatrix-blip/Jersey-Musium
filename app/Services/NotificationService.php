<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;

/** Transactional customer emails. All use the single "notification" template. */
final class NotificationService
{
    public static function orderPlaced(array $order): void
    {
        $items = db()->select('SELECT * FROM order_items WHERE order_id = :id', ['id' => $order['id']]);
        Mailer::sendView($order['customer_email'], 'Order confirmed – ' . $order['order_number'], 'notification', [
            'heading' => 'Thanks for your order, ' . explode(' ', $order['customer_name'])[0] . '!',
            'lines'   => [
                'Your order <strong>' . e($order['order_number']) . '</strong> has been confirmed.',
                $order['payment_method'] === 'cod'
                    ? 'Please keep ' . e(money($order['grand_total'])) . ' ready at delivery (Cash on Delivery).'
                    : 'We have received your payment of ' . e(money($order['grand_total'])) . '.',
                'We will email you again as soon as it ships.',
            ],
            'order'   => $order,
            'items'   => $items,
            'cta'     => ['View order', url('/account/orders/' . $order['order_number'])],
        ]);
    }

    public static function orderStatus(array $order, string $status, array $extra = []): void
    {
        $map = [
            'shipped'          => ['Your order is on its way 🚚', 'Your order ' . $order['order_number'] . ' has been shipped.'],
            'out_for_delivery' => ['Out for delivery today', 'Your order ' . $order['order_number'] . ' is out for delivery. Keep your phone handy!'],
            'delivered'        => ['Delivered! Enjoy your jersey ⚽', 'Your order ' . $order['order_number'] . ' has been delivered. Wear it with pride.'],
            'cancelled'        => ['Order cancelled', 'Your order ' . $order['order_number'] . ' has been cancelled.' . ($order['payment_status'] === 'paid' ? ' Your refund has been initiated.' : '')],
        ];
        if (!isset($map[$status])) {
            return;
        }
        [$heading, $line] = $map[$status];
        $lines = [e($line)];
        if (!empty($extra['courier'])) {
            $lines[] = 'Courier: <strong>' . e($extra['courier']) . '</strong> · AWB: <strong>' . e($extra['awb']) . '</strong>';
        }
        if ($status === 'shipped' && !empty($order['invoice_number'])) {
            $lines[] = 'Your GST invoice <strong>' . e($order['invoice_number']) . '</strong>: <a href="'
                . e(url('/account/orders/' . $order['order_number'] . '/invoice')) . '" style="color:#e11d48">download invoice</a>.';
        }
        Mailer::sendView($order['customer_email'], $heading . ' – ' . $order['order_number'], 'notification', [
            'heading' => $heading,
            'lines'   => $lines,
            'cta'     => !empty($extra['tracking_url']) ? ['Track shipment', $extra['tracking_url']] : ['View order', url('/account/orders/' . $order['order_number'])],
        ]);
    }

    public static function refundProcessed(array $order, string $amount): void
    {
        Mailer::sendView($order['customer_email'], 'Refund processed – ' . $order['order_number'], 'notification', [
            'heading' => 'Your refund is on its way',
            'lines'   => [
                'We have processed a refund of <strong>' . e(money($amount, true)) . '</strong> for order ' . e($order['order_number']) . '.',
                'It usually reflects in 5–7 business days depending on your bank.',
            ],
            'cta'     => ['View order', url('/account/orders/' . $order['order_number'])],
        ]);
    }

    public static function returnUpdate(array $return, array $order): void
    {
        Mailer::sendView($order['customer_email'], 'Return ' . $return['rma_number'] . ' – ' . label($return['status']), 'notification', [
            'heading' => 'Return update: ' . label($return['status']),
            'lines'   => array_filter([
                'Your return request <strong>' . e($return['rma_number']) . '</strong> for order ' . e($order['order_number']) . ' is now <strong>' . e(label($return['status'])) . '</strong>.',
                $return['admin_note'] ? 'Note from our team: ' . e($return['admin_note']) : null,
            ]),
            'cta'     => ['View order', url('/account/orders/' . $order['order_number'])],
        ]);
    }

    public static function passwordReset(string $email, string $link): void
    {
        Mailer::sendView($email, 'Reset your Jersey Musium password', 'notification', [
            'heading' => 'Reset your password',
            'lines'   => ['We received a request to reset your password. This link is valid for 60 minutes.', 'If you did not request this, you can safely ignore this email.'],
            'cta'     => ['Reset password', $link],
        ]);
    }

    public static function welcome(array $user): void
    {
        Mailer::sendView($user['email'], 'Welcome to Jersey Musium', 'notification', [
            'heading' => 'Welcome to the club, ' . explode(' ', $user['name'])[0] . '!',
            'lines'   => ['Your account is ready. Explore club kits, national team jerseys and retro classics.'],
            'cta'     => ['Start shopping', url('/shop')],
        ]);
    }
}
