<?php
use App\Services\ReturnService;

/** @var App\Core\Paginator $returns @var array $counts */
$cur = (string) request()->query('status', '');
?>
<div class="page-head"><div><h1>Returns &amp; exchanges</h1><p>Approve, schedule pickup, receive, then refund or exchange.</p></div></div>
<nav class="tabs">
    <a href="/admin/returns" class="<?= $cur === '' ? 'is-active' : '' ?>">All</a>
    <?php foreach (array_keys(ReturnService::TRANSITIONS) as $s): ?>
        <a href="/admin/returns?status=<?= $s ?>" class="<?= $cur === $s ? 'is-active' : '' ?>"><?= e(label($s)) ?><?php if (!empty($counts[$s])): ?><span><?= (int) $counts[$s] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>RMA</th><th>Order</th><th>Item</th><th>Type</th><th>Reason</th><th>Status</th><th>Requested</th></tr></thead>
        <tbody>
        <?php foreach ($returns->items as $r): ?>
            <tr>
                <td><a class="link" href="/admin/returns/<?= (int) $r['id'] ?>"><?= e($r['rma_number']) ?></a></td>
                <td><?= e($r['order_number']) ?><br><span class="muted"><?= e($r['customer_name']) ?></span></td>
                <td><?= e(str_limit($r['product_name'], 34)) ?><br><span class="muted">Size <?= e($r['size']) ?> × <?= (int) $r['qty'] ?></span></td>
                <td><?= e(ucfirst($r['type'])) ?><?= $r['exchange_size'] ? ' → ' . e($r['exchange_size']) : '' ?></td>
                <td><?= e(ReturnService::REASONS[$r['reason']] ?? $r['reason']) ?></td>
                <td><span class="badge badge--<?= match ($r['status']) { 'requested' => 'warning', 'rejected' => 'danger', 'refunded', 'exchanged', 'closed' => 'success', default => 'info' } ?>"><?= e(label($r['status'])) ?></span></td>
                <td><?= e(format_date($r['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$returns->items): ?><tr><td colspan="7" class="empty-row">No return requests.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $returns->links() ?>
</div>
