<?php
use App\Services\OrderService;

/** @var App\Core\Paginator $orders @var array $counts */
$rq = request();
$cur = (string) $rq->query('status', '');
$tabs = ['' => 'All', 'confirmed' => 'New', 'packed' => 'Packed', 'shipped' => 'Shipped', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered',
         'return_requested' => 'Returns', 'pending_payment' => 'Awaiting payment', 'cancelled' => 'Cancelled'];
$qs = $rq->queryAll(); unset($qs['page']);
?>
<div class="page-head">
    <div><h1>Orders</h1><p><?= number_format($orders->total) ?> orders</p></div>
    <a class="btn btn--outline" href="/admin/orders/export?<?= e(http_build_query($qs)) ?>">Export CSV</a>
</div>
<nav class="tabs">
    <?php foreach ($tabs as $k => $lbl): ?>
        <a href="/admin/orders<?= $k ? '?status=' . $k : '' ?>" class="<?= $cur === $k ? 'is-active' : '' ?>"><?= e($lbl) ?><?php if ($k && !empty($counts[$k])): ?><span><?= (int) $counts[$k] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<form class="filters" method="get">
    <?php if ($cur): ?><input type="hidden" name="status" value="<?= e($cur) ?>"><?php endif; ?>
    <input class="input" name="q" value="<?= e($rq->query('q')) ?>" placeholder="Order #, name, email, phone">
    <select class="select" name="payment"><option value="">All payments</option><?php foreach (['razorpay' => 'Razorpay', 'cashfree' => 'Cashfree', 'cod' => 'COD'] as $k => $lbl): ?><option value="<?= $k ?>" <?= $rq->query('payment') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select>
    <input class="input" type="date" name="from" value="<?= e($rq->query('from')) ?>" aria-label="From date">
    <input class="input" type="date" name="to" value="<?= e($rq->query('to')) ?>" aria-label="To date">
    <button class="btn btn--dark" type="submit">Filter</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th class="num">Items</th><th>Payment</th><th>Status</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($orders->items as $o): ?>
            <tr>
                <td><a class="link" href="/admin/orders/<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></a></td>
                <td><?= e(format_date($o['created_at'], 'd M Y, h:i A')) ?></td>
                <td><?= e($o['customer_name']) ?><br><span class="muted"><?= e($o['customer_phone']) ?></span></td>
                <td class="num"><?= (int) $o['items'] ?></td>
                <td><?= e(strtoupper($o['payment_method'])) ?><br><span class="muted"><?= e(label($o['payment_status'])) ?></span></td>
                <td><?= partial('admin/partials/status', ['s' => $o['status']]) ?></td>
                <td class="num"><?= e(money($o['grand_total'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders->items): ?><tr><td colspan="7" class="empty-row">No orders match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $orders->links() ?>
</div>
