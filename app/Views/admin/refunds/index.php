<?php /** @var App\Core\Paginator $refunds */ $cur = (string) request()->query('status', ''); ?>
<div class="page-head"><div><h1>Refunds</h1><p>Gateway refunds update automatically via webhooks. Manual refunds (COD) are recorded with a UTR.</p></div></div>
<nav class="tabs">
    <?php foreach (['' => 'All', 'processing' => 'Processing', 'failed' => 'Failed', 'processed' => 'Processed'] as $k => $lbl): ?>
        <a href="/admin/refunds<?= $k ? '?status=' . $k : '' ?>" class="<?= $cur === $k ? 'is-active' : '' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
</nav>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Refund</th><th>Order</th><th>Reason</th><th>Mode</th><th>Status</th><th>By</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($refunds->items as $r): ?>
            <tr>
                <td><?= e($r['refund_number']) ?><br><span class="muted"><?= e(format_date($r['created_at'], 'd M Y, h:i A')) ?></span></td>
                <td><a class="link" href="/admin/orders/<?= (int) $r['order_id'] ?>"><?= e($r['order_number']) ?></a><br><span class="muted"><?= e($r['customer_name']) ?></span></td>
                <td><?= e(str_limit($r['reason'], 40)) ?></td>
                <td><?= e($r['mode']) ?><?= $r['manual_reference'] ? '<br><span class="muted">UTR ' . e($r['manual_reference']) . '</span>' : '' ?></td>
                <td><span class="badge badge--<?= match ($r['status']) { 'processed' => 'success', 'failed' => 'danger', default => 'warning' } ?>"><?= e(label($r['status'])) ?></span>
                    <?= $r['error_message'] ? '<br><span class="text-danger" style="font-size:12px">' . e(str_limit($r['error_message'], 50)) . '</span>' : '' ?></td>
                <td><?= e($r['admin_name'] ?: 'System') ?></td>
                <td class="num"><?= e(money($r['amount'], true)) ?></td>
                <td class="num"><?php if ($r['status'] === 'failed' && $r['mode'] === 'gateway'): ?>
                    <form method="post" action="/admin/refunds/<?= (int) $r['id'] ?>/retry" data-confirm="Retry this refund with the gateway?"><?= csrf_field() ?><button class="btn btn--outline btn--sm">Retry</button></form>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$refunds->items): ?><tr><td colspan="8" class="empty-row">No refunds.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $refunds->links() ?>
</div>
