<?php
/** @var array $kpi @var array $series @var array $recent @var array $lowStock @var array $topProducts */

// ---- single-series bar chart: revenue per day (server-rendered SVG) ----
$W = 720; $H = 240; $padL = 56; $padB = 28; $padT = 12; $padR = 8;
$max = max(1, max(array_column($series, 'revenue')));
// nice round axis max
$mag = 10 ** floor(log10($max));
$niceMax = ceil($max / $mag) * $mag;
$plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
$slot = $plotW / count($series);
$barW = max(6, $slot - 2 * 6); // generous gap between bars
$total14 = array_sum(array_column($series, 'revenue'));
?>
<div class="page-head">
    <div><h1>Dashboard</h1><p><?= e(date('l, d M Y')) ?></p></div>
    <a class="btn btn--outline" href="/admin/orders?status=confirmed">Orders to process (<?= (int) $kpi['to_ship'] ?>)</a>
</div>

<div class="kpis">
    <div class="kpi"><div class="kpi__label">Today's revenue</div><div class="kpi__value"><?= e(money($kpi['today_revenue'])) ?></div><div class="kpi__sub"><?= (int) $kpi['today_orders'] ?> orders today</div></div>
    <div class="kpi"><div class="kpi__label">Revenue · 30 days</div><div class="kpi__value"><?= e(money($kpi['revenue_30'])) ?></div><div class="kpi__sub"><?= (int) $kpi['orders_30'] ?> orders · AOV <?= e(money($kpi['aov'])) ?></div></div>
    <div class="kpi <?= $kpi['to_ship'] ? 'kpi--alert' : '' ?>"><div class="kpi__label">To pack &amp; ship</div><div class="kpi__value"><?= (int) $kpi['to_ship'] ?></div><div class="kpi__sub"><?= (int) $kpi['returns'] ?> open returns · <?= (int) $kpi['refunds_pending'] ?> refunds pending</div></div>
    <div class="kpi"><div class="kpi__label">Customers</div><div class="kpi__value"><?= number_format($kpi['customers']) ?></div><div class="kpi__sub">+<?= (int) $kpi['new_customers'] ?> in 30 days</div></div>
</div>

<div class="cols cols--2-1">
    <section class="card">
        <div class="card__head">
            <h2>Revenue per day · last 14 days</h2>
            <span class="muted" style="font-size:13px">Total <?= e(money($total14)) ?></span>
        </div>
        <div class="chart">
            <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Bar chart of daily revenue for the last 14 days, total <?= e(money($total14)) ?>">
                <g class="grid">
                    <?php for ($g = 0; $g <= 4; $g++): $y = $padT + $plotH - $plotH * $g / 4; ?>
                        <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
                    <?php endfor; ?>
                </g>
                <g class="axis">
                    <?php for ($g = 0; $g <= 4; $g++): $y = $padT + $plotH - $plotH * $g / 4; $v = $niceMax * $g / 4; ?>
                        <text x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= $v >= 1000 ? '₹' . rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'k' : '₹' . (int) $v ?></text>
                    <?php endfor; ?>
                    <?php foreach ($series as $i => $d): if ($i % 2 && $i !== count($series) - 1) continue; ?>
                        <text x="<?= $padL + $slot * $i + $slot / 2 ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= e(date('d M', strtotime($d['date']))) ?></text>
                    <?php endforeach; ?>
                </g>
                <?php foreach ($series as $i => $d):
                    $h = $d['revenue'] > 0 ? max(2, $plotH * $d['revenue'] / $niceMax) : 0;
                    $x = $padL + $slot * $i + ($slot - $barW) / 2;
                    $y = $padT + $plotH - $h;
                    $r = min(4, $barW / 2, $h);
                ?>
                    <rect class="hit" x="<?= $padL + $slot * $i ?>" y="<?= $padT ?>" width="<?= $slot ?>" height="<?= $plotH ?>" tabindex="0"
                          data-label="<?= e(date('D, d M', strtotime($d['date']))) ?>" data-value="<?= e(money($d['revenue'])) ?>" data-orders="<?= (int) $d['orders'] ?>"
                          aria-label="<?= e(date('d M', strtotime($d['date'])) . ': ' . money($d['revenue']) . ', ' . $d['orders'] . ' orders') ?>"/>
                    <?php if ($h > 0): ?>
                        <path class="bar" d="M<?= $x ?>,<?= $padT + $plotH ?> V<?= $y + $r ?> Q<?= $x ?>,<?= $y ?> <?= $x + $r ?>,<?= $y ?> H<?= $x + $barW - $r ?> Q<?= $x + $barW ?>,<?= $y ?> <?= $x + $barW ?>,<?= $y + $r ?> V<?= $padT + $plotH ?> Z"/>
                    <?php else: ?>
                        <rect class="bar" x="<?= $x ?>" y="<?= $padT + $plotH - 1 ?>" width="<?= $barW ?>" height="1" opacity=".3"/>
                    <?php endif; ?>
                <?php endforeach; ?>
                <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $padT + $plotH ?>" y2="<?= $padT + $plotH ?>" stroke="#c9ccd4"/>
            </svg>
            <div class="chart-tip" aria-hidden="true"></div>
        </div>
        <details class="mt-1">
            <summary class="muted" style="cursor:pointer;font-size:12px">View as table</summary>
            <div class="table-wrap mt-1"><table class="table">
                <thead><tr><th>Date</th><th class="num">Orders</th><th class="num">Revenue</th></tr></thead>
                <tbody><?php foreach (array_reverse($series) as $d): ?><tr><td><?= e(date('D, d M', strtotime($d['date']))) ?></td><td class="num"><?= (int) $d['orders'] ?></td><td class="num"><?= e(money($d['revenue'])) ?></td></tr><?php endforeach; ?></tbody>
            </table></div>
        </details>
    </section>

    <section class="card">
        <div class="card__head"><h2>Low stock</h2><a class="muted" href="/admin/products?stock=low" style="font-size:13px">View all</a></div>
        <?php if (!$lowStock): ?><p class="muted">All sizes are well stocked. ✅</p><?php endif; ?>
        <div class="table-wrap"><table class="table">
            <tbody>
            <?php foreach ($lowStock as $v): ?>
                <tr>
                    <td><a class="link" href="/admin/products/<?= (int) $v['product_id'] ?>/edit"><?= e(str_limit($v['name'], 34)) ?></a><br><span class="muted">Size <?= e($v['size']) ?></span></td>
                    <td class="num"><span class="badge badge--<?= $v['stock'] == 0 ? 'danger' : 'warning' ?>"><?= (int) $v['stock'] ?> left</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>

<div class="cols cols--2-1 mt-2">
    <section class="card">
        <div class="card__head"><h2>Recent orders</h2><a class="muted" href="/admin/orders" style="font-size:13px">All orders</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $o): ?>
                <tr>
                    <td><a class="link" href="/admin/orders/<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></a><br><span class="muted"><?= e(format_date($o['created_at'], 'd M, h:i A')) ?></span></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td><?= partial('admin/partials/status', ['s' => $o['status']]) ?></td>
                    <td><?= e(strtoupper($o['payment_method'])) ?></td>
                    <td class="num"><?= e(money($o['grand_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="5" class="empty-row">No orders yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <section class="card">
        <h2>Top sellers · 30 days</h2>
        <div class="table-wrap"><table class="table">
            <tbody>
            <?php foreach ($topProducts as $tp): ?>
                <tr><td><?= e(str_limit($tp['product_name'], 36)) ?></td><td class="num"><?= (int) $tp['qty'] ?> sold</td></tr>
            <?php endforeach; ?>
            <?php if (!$topProducts): ?><tr><td class="empty-row">No sales yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div>
