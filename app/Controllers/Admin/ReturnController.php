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
use App\Services\ReturnService;
use RuntimeException;

final class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $params = [];
        $where = '1 = 1';
        if (($s = $request->query('status')) && isset(ReturnService::TRANSITIONS[$s])) {
            $where = 'r.status = :s';
            $params['s'] = $s;
        }
        return $this->admin('returns/index', [
            'title'   => 'Returns & exchanges',
            'counts'  => array_column(db()->select('SELECT status, COUNT(*) c FROM returns GROUP BY status'), 'c', 'status'),
            'returns' => Paginator::query(
                "SELECT r.*, o.order_number, o.customer_name, oi.product_name, oi.size, oi.unit_price
                   FROM returns r JOIN orders o ON o.id = r.order_id JOIN order_items oi ON oi.id = r.order_item_id
                  WHERE $where ORDER BY r.id DESC",
                $params,
                (int) config('app.admin_per_page')
            ),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $r = $this->find((int) $id);
        $order = OrderService::find((int) $r['order_id']);
        $item = db()->first('SELECT * FROM order_items WHERE id = :id', ['id' => $r['order_item_id']]);
        // Suggested refund: item value × qty, proportionally reduced by any coupon discount.
        $share = (float) $order['subtotal'] > 0 ? 1 - (float) $order['discount'] / (float) $order['subtotal'] : 1;
        return $this->admin('returns/show', [
            'title'      => 'Return ' . $r['rma_number'],
            'r'          => $r,
            'order'      => $order,
            'item'       => $item,
            'next'       => ReturnService::TRANSITIONS[$r['status']] ?? [],
            'suggested'  => round((float) $item['unit_price'] * (int) $r['qty'] * $share, 2),
            'refundable' => RefundService::refundable($order),
            'reasons'    => ReturnService::REASONS,
        ]);
    }

    public function status(Request $request, string $id): Response
    {
        $r = $this->find((int) $id);
        try {
            ReturnService::updateStatus($r, (string) $request->input('status'), trim((string) $request->input('note')) ?: null, admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('return.status', 'return', (int) $id, ['to' => $request->input('status')]);
        return back()->with('success', 'Return updated and customer notified.');
    }

    public function refund(Request $request, string $id): Response
    {
        $r = $this->find((int) $id);
        if ($r['status'] !== 'received') {
            return back()->with('error', 'Mark the item as received before refunding.');
        }
        $order = OrderService::find((int) $r['order_id']);
        $amount = paise($request->input('amount'));
        $mode = $order['payment_method'] === 'cod' ? 'manual' : 'gateway';
        $ref = trim((string) $request->input('manual_reference'));
        if ($mode === 'manual' && $ref === '') {
            return back()->with('error', 'COD order: transfer the amount to the customer\'s UPI (' . ($r['refund_upi'] ?: 'not provided') . ') and enter the UTR.');
        }
        try {
            RefundService::create($order, $amount, 'Return ' . $r['rma_number'], (int) $r['id'], $mode, $ref ?: null, admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('return.refund', 'return', (int) $id, ['amount' => $amount / 100]);
        return back()->with('success', 'Refund initiated for ' . $r['rma_number'] . '.');
    }

    private function find(int $id): array
    {
        return db()->first('SELECT * FROM returns WHERE id = :id', ['id' => $id]) ?? throw new HttpException(404);
    }
}
