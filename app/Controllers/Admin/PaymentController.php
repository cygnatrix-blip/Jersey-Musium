<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;

/** Transactions ledger – every gateway attempt (created / captured / failed / refunded). */
final class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['1 = 1'];
        $params = [];
        if (($g = $request->query('gateway')) && in_array($g, ['razorpay', 'cashfree', 'cod'], true)) {
            $where[] = 'p.gateway = :g';
            $params['g'] = $g;
        }
        if (($s = $request->query('status')) && in_array($s, ['created', 'pending', 'captured', 'failed', 'refunded', 'partially_refunded'], true)) {
            $where[] = 'p.status = :s';
            $params['s'] = $s;
        }
        if ($q = $request->query('q')) {
            $where[] = '(o.order_number LIKE :q1 OR p.gateway_payment_id LIKE :q2 OR p.gateway_order_id LIKE :q3)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        $w = implode(' AND ', $where);
        $summary = db()->first(
            "SELECT COALESCE(SUM(CASE WHEN status IN ('captured','partially_refunded','refunded') THEN amount END), 0) captured,
                    SUM(status = 'failed') failed, SUM(status IN ('created','pending')) pending
               FROM payments WHERE created_at >= CURDATE() - INTERVAL 29 DAY"
        );
        return $this->admin('payments/index', [
            'title'    => 'Transactions',
            'summary'  => $summary,
            'payments' => Paginator::query(
                "SELECT p.*, o.order_number, o.customer_name FROM payments p JOIN orders o ON o.id = p.order_id WHERE $w ORDER BY p.id DESC",
                $params,
                (int) config('app.admin_per_page'),
                null,
                "SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE $w"
            ),
        ]);
    }
}
