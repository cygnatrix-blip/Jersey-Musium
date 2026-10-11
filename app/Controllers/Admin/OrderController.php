<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\ShipmentService;
use RuntimeException;

final class OrderController extends Controller
{
    private function filters(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($s = $request->query('status')) && isset(OrderService::TRANSITIONS[$s])) {
            $where[] = 'o.status = :st';
            $params['st'] = $s;
        }
        if (($pm = $request->query('payment')) && in_array($pm, ['razorpay', 'cashfree', 'cod'], true)) {
            $where[] = 'o.payment_method = :pm';
            $params['pm'] = $pm;
        }
        if ($q = $request->query('q')) {
            $where[] = '(o.order_number LIKE :q1 OR o.customer_email LIKE :q2 OR o.customer_phone LIKE :q3 OR o.customer_name LIKE :q4)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        if ($from = $request->query('from')) {
            $where[] = 'o.created_at >= :from';
            $params['from'] = date('Y-m-d 00:00:00', strtotime((string) $from));
        }
        if ($to = $request->query('to')) {
            $where[] = 'o.created_at <= :to';
            $params['to'] = date('Y-m-d 23:59:59', strtotime((string) $to));
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(Request $request): Response
    {
        [$where, $params] = $this->filters($request);
        $counts = array_column(db()->select('SELECT status, COUNT(*) c FROM orders GROUP BY status'), 'c', 'status');
        return $this->admin('orders/index', [
            'title'  => 'Orders',
            'orders' => Paginator::query(
                "SELECT o.*, (SELECT SUM(qty) FROM order_items oi WHERE oi.order_id = o.id) AS items FROM orders o WHERE $where ORDER BY o.id DESC",
                $params,
                (int) config('app.admin_per_page'),
                null,
                "SELECT COUNT(*) FROM orders o WHERE $where"
            ),
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $order = OrderService::find((int) $id) ?? throw new HttpException(404);
        return $this->admin('orders/show', [
            'title'      => 'Order ' . $order['order_number'],
            'order'      => $order,
            'items'      => OrderService::items((int) $id),
            'history'    => OrderService::history((int) $id),
            'shipments'  => ShipmentService::forOrder((int) $id),
            'payments'   => db()->select('SELECT * FROM payments WHERE order_id = :id ORDER BY id DESC', ['id' => $id]),
            'refunds'    => db()->select('SELECT * FROM refunds WHERE order_id = :id ORDER BY id DESC', ['id' => $id]),
            'returns'    => db()->select('SELECT r.*, oi.product_name, oi.size FROM returns r JOIN order_items oi ON oi.id = r.order_item_id WHERE r.order_id = :id', ['id' => $id]),
            'customer'   => db()->first('SELECT id, name, email, phone, created_at, (SELECT COUNT(*) FROM orders WHERE user_id = users.id) AS orders FROM users WHERE id = :u', ['u' => $order['user_id']]),
            'address'    => OrderService::address($order),
            'next'       => OrderService::TRANSITIONS[$order['status']] ?? [],
            'refundable' => RefundService::refundable($order),
        ]);
    }

    public function status(Request $request, string $id): Response
    {
        $order = OrderService::find((int) $id) ?? throw new HttpException(404);
        $to = (string) $request->input('status');
        if ($to === 'cancelled') {
            return $this->cancel($request, $id);
        }
        if (in_array($to, ['shipped'], true)) {
            return back()->with('error', 'Use "Create shipment" to mark the order as shipped.');
        }
        try {
            OrderService::transition($order, $to, (string) $request->input('note') ?: null, 'admin', admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('order.status', 'order', (int) $id, ['from' => $order['status'], 'to' => $to]);
        return back()->with('success', 'Order moved to ' . label($to) . '.');
    }

    public function ship(Request $request, string $id): Response
    {
        $order = OrderService::find((int) $id) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'courier'      => 'required|max:80',
            'awb_number'   => 'required|max:80',
            'tracking_url' => 'nullable|url|max:255',
            'weight_grams' => 'nullable|integer|min:1',
        ], ['awb_number' => 'AWB / tracking number']);
        try {
            $sid = ShipmentService::create($order, $data['courier'], $data['awb_number'], $data['tracking_url'], $data['weight_grams'] ? (int) $data['weight_grams'] : null, admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('order.ship', 'order', (int) $id, ['shipment' => $sid, 'awb' => $data['awb_number']]);
        return back()->with('success', 'Shipment created and customer notified.');
    }

    public function shipmentEvent(Request $request, string $id, string $shipmentId): Response
    {
        if (!db()->scalar('SELECT 1 FROM shipments WHERE id = :s AND order_id = :o', ['s' => (int) $shipmentId, 'o' => (int) $id])) {
            throw new HttpException(404);
        }
        try {
            ShipmentService::addEvent((int) $shipmentId, (string) $request->input('status'), $request->input('location') ?: null, $request->input('note') ?: null, admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('shipment.event', 'order', (int) $id, ['status' => $request->input('status')]);
        return back()->with('success', 'Tracking updated.');
    }

    public function cancel(Request $request, string $id): Response
    {
        $order = OrderService::find((int) $id) ?? throw new HttpException(404);
        $reason = trim((string) $request->input('reason', '')) ?: 'Cancelled by store';
        try {
            OrderService::cancel($order, $reason, 'admin', admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('order.cancel', 'order', (int) $id, ['reason' => $reason]);
        return back()->with('success', 'Order cancelled, stock restored' . ($order['payment_status'] === 'paid' ? ' and refund initiated.' : '.'));
    }

    public function note(Request $request, string $id): Response
    {
        db()->update('orders', ['admin_notes' => mb_substr((string) $request->input('admin_notes'), 0, 5000)], 'id = :id', ['id' => (int) $id]);
        return back()->with('success', 'Note saved.');
    }

    public function invoice(Request $request, string $id): Response
    {
        return \App\Services\InvoiceService::render(OrderService::find((int) $id) ?? throw new HttpException(404));
    }

    /** CSV export of the filtered order list (for accounting / GST filing). */
    public function export(Request $request): Response
    {
        [$where, $params] = $this->filters($request);
        $rows = db()->select("SELECT o.* FROM orders o WHERE $where ORDER BY o.id DESC LIMIT 20000", $params);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Order', 'Date', 'Status', 'Payment', 'Payment status', 'Customer', 'Email', 'Phone', 'State', 'Pincode', 'Subtotal', 'Discount', 'Coupon', 'Shipping', 'COD fee', 'GST', 'Total', 'Refunded'], ',', '"', '');
        foreach ($rows as $o) {
            $a = OrderService::address($o);
            $line = [$o['order_number'], $o['created_at'], $o['status'], $o['payment_method'], $o['payment_status'], $o['customer_name'], $o['customer_email'],
                $o['customer_phone'], $a['state'] ?? '', $a['pincode'] ?? '', $o['subtotal'], $o['discount'], $o['coupon_code'], $o['shipping_fee'], $o['cod_fee'], $o['tax_total'], $o['grand_total'], $o['refunded_total']];
            // Neutralise spreadsheet formula injection
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $line), ',', '"', '');
        }
        rewind($out);
        AuditService::log('order.export', null, null, ['rows' => count($rows)]);
        return new Response((string) stream_get_contents($out), 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="orders-' . date('Ymd-His') . '.csv"',
        ]);
    }
}
