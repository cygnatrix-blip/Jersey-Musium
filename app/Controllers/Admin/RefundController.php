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
use App\Services\PaymentService;
use App\Services\RefundService;
use RuntimeException;

final class RefundController extends Controller
{
    public function index(Request $request): Response
    {
        $params = [];
        $where = '1 = 1';
        if (($s = $request->query('status')) && in_array($s, ['pending', 'processing', 'processed', 'failed'], true)) {
            $where = 'r.status = :s';
            $params['s'] = $s;
        }
        return $this->admin('refunds/index', [
            'title'   => 'Refunds',
            'refunds' => Paginator::query(
                "SELECT r.*, o.order_number, o.customer_name, o.payment_method, a.name AS admin_name
                   FROM refunds r JOIN orders o ON o.id = r.order_id LEFT JOIN admins a ON a.id = r.admin_id
                  WHERE $where ORDER BY r.id DESC",
                $params,
                (int) config('app.admin_per_page')
            ),
        ]);
    }

    /** Manual refund from the order page (partial or full). */
    public function store(Request $request, string $id): Response
    {
        $order = OrderService::find((int) $id) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'amount'           => 'required|numeric|min:1',
            'reason'           => 'required|max:255',
            'mode'             => 'required|in:gateway,manual',
            'manual_reference' => 'nullable|max:120',
        ]);
        if ($data['mode'] === 'manual' && !$data['manual_reference']) {
            return back()->with('error', 'Enter the UTR / transaction reference for a manual refund.');
        }
        try {
            $refund = RefundService::create($order, paise($data['amount']), $data['reason'], null, $data['mode'], $data['manual_reference'], admin_auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditService::log('refund.create', 'order', (int) $id, ['amount' => $data['amount'], 'mode' => $data['mode'], 'refund' => $refund['refund_number']]);
        return back()->with('success', 'Refund ' . $refund['refund_number'] . ' ' . ($refund['status'] === 'processed' ? 'completed.' : 'initiated – status will update automatically.'));
    }

    /** Retry a failed gateway refund. */
    public function retry(Request $request, string $id): Response
    {
        $r = db()->first("SELECT * FROM refunds WHERE id = :id AND status = 'failed' AND mode = 'gateway'", ['id' => (int) $id]) ?? throw new HttpException(404);
        $payment = db()->first('SELECT * FROM payments WHERE id = :id', ['id' => $r['payment_id']]);
        $res = PaymentService::gateway($payment['gateway'])->refund($payment, paise($r['amount']), $r['refund_number'], $r['reason']);
        db()->update('refunds', [
            'status' => $res['status'] === 'failed' ? 'failed' : 'processing',
            'gateway_refund_id' => $res['gateway_refund_id'], 'error_message' => $res['error'], 'raw_response' => json_encode($res['raw']),
        ], 'id = :id', ['id' => $r['id']]);
        if ($res['status'] === 'processed') {
            RefundService::finalize((int) $r['id'], $res['gateway_refund_id']);
        }
        AuditService::log('refund.retry', 'refund', (int) $id, ['result' => $res['status']]);
        return back()->with($res['status'] === 'failed' ? 'error' : 'success', $res['status'] === 'failed' ? 'Retry failed: ' . $res['error'] : 'Refund re-submitted.');
    }
}
