<?php /** @var App\Core\Paginator $payments @var array $summary */ $rq = request(); ?>
<div class="page-head"><div><h1>Transactions</h1><p>Every payment attempt across Razorpay, Cashfree and COD.</p></div></div>
<div class="kpis">
    <div class="kpi"><div class="kpi__label">Captured · 30 days</div><div class="kpi__value"><?= e(money($summary['captured'])) ?></div></div>
    <div class="kpi"><div class="kpi__label">Failed attempts · 30 days</div><div class="kpi__value"><?= (int) $summary['failed'] ?></div></div>
    <div class="kpi"><div class="kpi__label">Abandoned / pending</div><div class="kpi__value"><?= (int) $summary['pending'] ?></div></div>
</div>
<form class="filters" method="get">
    <input class="input" name="q" value="<?= e($rq->query('q')) ?>" placeholder="Order # or gateway id">
    <select class="select" name="gateway"><option value="">All gateways</option><?php foreach (['razorpay', 'cashfree', 'cod'] as $g): ?><option value="<?= $g ?>" <?= $rq->query('gateway') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option><?php endforeach; ?></select>
    <select class="select" name="status"><option value="">All statuses</option><?php foreach (['created', 'captured', 'failed', 'refunded', 'partially_refunded'] as $s): ?><option value="<?= $s ?>" <?= $rq->query('status') === $s ? 'selected' : '' ?>><?= label($s) ?></option><?php endforeach; ?></select>
    <button class="btn btn--dark" type="submit">Filter</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Order</th><th>Gateway</th><th>Gateway reference</th><th>Method</th><th>Status</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($payments->items as $p): ?>
            <tr>
                <td><?= e(format_date($p['created_at'], 'd M Y, h:i A')) ?></td>
                <td><a class="link" href="/admin/orders/<?= (int) $p['order_id'] ?>"><?= e($p['order_number']) ?></a><br><span class="muted"><?= e($p['customer_name']) ?></span></td>
                <td><?= e(ucfirst($p['gateway'])) ?></td>
                <td class="muted" style="font-size:12px"><?= e($p['gateway_payment_id'] ?: '—') ?><br><?= e($p['gateway_order_id']) ?></td>
                <td><?= e($p['method'] ?: '—') ?></td>
                <td><span class="badge badge--<?= match ($p['status']) { 'captured' => 'success', 'failed' => 'danger', 'refunded', 'partially_refunded' => 'warning', default => 'muted' } ?>"><?= e(label($p['status'])) ?></span>
                    <?= $p['error_message'] ? '<br><span class="text-danger" style="font-size:12px">' . e(str_limit($p['error_message'], 40)) . '</span>' : '' ?></td>
                <td class="num"><?= e(money($p['amount'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$payments->items): ?><tr><td colspan="7" class="empty-row">No transactions.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $payments->links() ?>
</div>
