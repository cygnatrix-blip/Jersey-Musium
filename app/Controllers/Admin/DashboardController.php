<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\InventoryService;

final class DashboardController extends Controller
{
    /** Orders that count as revenue: confirmed onwards, not cancelled / unpaid. */
    private const SOLD = "status NOT IN ('pending_payment','payment_failed','cancelled')";

    public function index(Request $request): Response
    {
        $kpi = db()->first(
            'SELECT
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN grand_total END), 0) AS today_revenue,
                SUM(DATE(created_at) = CURDATE()) AS today_orders,
                COALESCE(SUM(CASE WHEN created_at >= CURDATE() - INTERVAL 29 DAY THEN grand_total - refunded_total END), 0) AS revenue_30,
                SUM(created_at >= CURDATE() - INTERVAL 29 DAY) AS orders_30
               FROM orders WHERE ' . self::SOLD
        );
        $kpi['aov'] = $kpi['orders_30'] ? $kpi['revenue_30'] / $kpi['orders_30'] : 0;
        $kpi['to_ship'] = (int) db()->scalar("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','packed')");
        $kpi['returns'] = (int) db()->scalar("SELECT COUNT(*) FROM returns WHERE status IN ('requested','approved','pickup_scheduled','picked_up','received')");
        $kpi['refunds_pending'] = (int) db()->scalar("SELECT COUNT(*) FROM refunds WHERE status IN ('pending','processing','failed')");
        $kpi['customers'] = (int) db()->scalar('SELECT COUNT(*) FROM users');
        $kpi['new_customers'] = (int) db()->scalar('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE() - INTERVAL 29 DAY');

        // Revenue per day, last 14 days (fill gaps with 0 so the bars line up with dates)
        $rows = db()->select(
            'SELECT DATE(created_at) d, SUM(grand_total) revenue, COUNT(*) orders FROM orders
              WHERE ' . self::SOLD . ' AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)'
        );
        $byDay = array_column($rows, null, 'd');
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $series[] = ['date' => $d, 'revenue' => (float) ($byDay[$d]['revenue'] ?? 0), 'orders' => (int) ($byDay[$d]['orders'] ?? 0)];
        }

        $topProducts = db()->select(
            'SELECT oi.product_name, SUM(oi.qty) qty, SUM(oi.line_total) revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.' . self::SOLD . ' AND o.created_at >= CURDATE() - INTERVAL 29 DAY
              GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 6'
        );

        return $this->admin('dashboard', [
            'title'       => 'Dashboard',
            'kpi'         => $kpi,
            'series'      => $series,
            'recent'      => db()->select('SELECT * FROM orders ORDER BY id DESC LIMIT 8'),
            'lowStock'    => InventoryService::lowStock(8),
            'topProducts' => $topProducts,
        ]);
    }
}
