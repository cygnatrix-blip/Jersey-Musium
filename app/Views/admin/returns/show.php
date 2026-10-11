<?php /** @var array $r @var array $order @var array $item @var array $next @var float $suggested @var int $refundable @var array $reasons */ ?>
<div class="page-head">
    <div><h1>Return <?= e($r['rma_number']) ?> <span class="badge badge--info"><?= e(label($r['status'])) ?></span></h1>
        <p>Order <a class="link" href="/admin/orders/<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?></a> · requested <?= e(format_date($r['created_at'], 'd M Y, h:i A')) ?></p></div>
    <a class="btn btn--outline" href="/admin/returns">Back</a>
</div>
<div class="cols cols--2-1">
    <section class="card">
        <h2>Request</h2>
        <dl class="kv">
            <dt>Item</dt><dd><?= e($item['product_name']) ?> (<?= e($item['sku']) ?>)</dd>
            <dt>Size / qty</dt><dd><?= e($item['size']) ?> × <?= (int) $r['qty'] ?></dd>
            <dt>Type</dt><dd><?= e(ucfirst($r['type'])) ?><?= $r['exchange_size'] ? ' → size ' . e($r['exchange_size']) : '' ?></dd>
            <dt>Reason</dt><dd><?= e($reasons[$r['reason']] ?? $r['reason']) ?></dd>
            <dt>Details</dt><dd><?= nl2br(e($r['details'] ?: '—')) ?></dd>
            <dt>Payment</dt><dd><?= e(strtoupper($order['payment_method'])) ?><?= $r['refund_upi'] ? ' · Refund UPI: <strong>' . e($r['refund_upi']) . '</strong>' : '' ?></dd>
            <dt>Admin note</dt><dd><?= e($r['admin_note'] ?: '—') ?></dd>
        </dl>
    </section>
    <div>
        <?php if ($next): ?>
            <section class="card">
                <h2>Update status</h2>
                <form method="post" action="/admin/returns/<?= (int) $r['id'] ?>/status">
                    <?= csrf_field() ?>
                    <?= partial('admin/partials/f', ['name' => 'status', 'label' => 'Move to', 'type' => 'select', 'value' => $next[0], 'options' => array_combine($next, array_map('label', $next))]) ?>
                    <?= partial('admin/partials/f', ['name' => 'note', 'label' => 'Note to customer (optional)', 'type' => 'textarea', 'attrs' => 'style="min-height:70px"']) ?>
                    <button class="btn btn--block" type="submit">Update &amp; notify customer</button>
                </form>
                <p class="muted mb-0 mt-1" style="font-size:12px">"Received" adds the item back to stock automatically.</p>
            </section>
        <?php endif; ?>
        <?php if ($r['status'] === 'received' && $r['type'] === 'return' && $refundable > 0): ?>
            <section class="card">
                <h2>Refund</h2>
                <form method="post" action="/admin/returns/<?= (int) $r['id'] ?>/refund" data-confirm="Issue this refund?">
                    <?= csrf_field() ?>
                    <?= partial('admin/partials/f', ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number', 'value' => min($suggested, $refundable / 100), 'attrs' => 'step="0.01" min="1" max="' . rupees($refundable) . '"', 'hint' => 'Suggested: item value after coupon share']) ?>
                    <?php if ($order['payment_method'] === 'cod'): ?>
                        <?= partial('admin/partials/f', ['name' => 'manual_reference', 'label' => 'UPI transfer UTR', 'attrs' => 'required']) ?>
                    <?php endif; ?>
                    <button class="btn btn--danger btn--block" type="submit">Refund <?= $order['payment_method'] === 'cod' ? '(record manual transfer)' : 'to original payment method' ?></button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
