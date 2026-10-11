<?php
/** @var array $u @var array $orders @var array $addresses @var array $stats */
$badge = ['active' => 'success', 'blocked' => 'danger', 'deleted' => 'muted'][$u['status']] ?? 'muted';
$deleted = $u['status'] === 'deleted';
?>
<div class="page-head">
    <div>
        <h1><?= e($u['name']) ?> <span class="badge badge--<?= $badge ?>"><?= e(label($u['status'])) ?></span></h1>
        <p><?= e($u['email']) ?> <?= $u['email_verified_at'] ? '<span class="badge badge--success">verified</span>' : '<span class="badge badge--warning">unverified</span>' ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?> · joined <?= e(format_date($u['created_at'])) ?> · last login <?= e(format_date($u['last_login_at'], 'd M Y, h:i A')) ?></p>
    </div>
    <a class="btn btn--outline" href="/admin/customers">Back</a>
</div>

<div class="kpis">
    <div class="kpi"><div class="kpi__label">Orders</div><div class="kpi__value"><?= count($orders) ?></div></div>
    <div class="kpi"><div class="kpi__label">Lifetime value</div><div class="kpi__value"><?= e(money($stats['ltv'])) ?></div></div>
    <div class="kpi"><div class="kpi__label">Returns</div><div class="kpi__value"><?= (int) $stats['returns_count'] ?></div></div>
    <div class="kpi"><div class="kpi__label">Wishlist · Reviews</div><div class="kpi__value"><?= (int) $stats['wishlist'] ?> · <?= (int) $stats['reviews'] ?></div></div>
</div>

<div class="cols cols--2-1">
    <div>
        <section class="card">
            <h2>Orders</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr><td><a class="link" href="/admin/orders/<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></a></td><td><?= e(format_date($o['created_at'])) ?></td><td><?= partial('admin/partials/status', ['s' => $o['status']]) ?></td><td class="num"><?= e(money($o['grand_total'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="4" class="empty-row">No orders yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </section>
        <section class="card">
            <h2>Addresses</h2>
            <?php foreach ($addresses as $a): ?>
                <p><?= e($a['name']) ?> · <?= e($a['phone']) ?><?= $a['is_default'] ? ' <span class="badge badge--success">Default</span>' : '' ?><br><span class="muted"><?= e(implode(', ', array_filter([$a['line1'], $a['line2'], $a['city'], $a['state'], $a['pincode']]))) ?></span></p>
            <?php endforeach; ?>
            <?php if (!$addresses): ?><p class="muted">None saved.</p><?php endif; ?>
        </section>
    </div>

    <div>
        <?php if (!$deleted): ?>
            <section class="card">
                <h2>Edit details</h2>
                <form method="post" action="/admin/customers/<?= (int) $u['id'] ?>">
                    <?= csrf_field() ?>
                    <?= partial('admin/partials/f', ['name' => 'name', 'label' => 'Name', 'value' => $u['name'], 'attrs' => 'required']) ?>
                    <?= partial('admin/partials/f', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $u['email'], 'attrs' => 'required']) ?>
                    <?= partial('admin/partials/f', ['name' => 'phone', 'label' => 'Mobile', 'value' => $u['phone'], 'attrs' => 'inputmode="numeric" maxlength="10"']) ?>
                    <?= partial('admin/partials/f', ['name' => 'admin_note', 'label' => 'Internal note (not visible to customer)', 'type' => 'textarea', 'value' => $u['admin_note'], 'attrs' => 'maxlength="500" style="min-height:70px"']) ?>
                    <button class="btn btn--block" type="submit">Save</button>
                </form>
            </section>
            <section class="card">
                <h2>Account actions</h2>
                <form method="post" action="/admin/customers/<?= (int) $u['id'] ?>/reset-link" class="mb-2"><?= csrf_field() ?>
                    <button class="btn btn--outline btn--block" type="submit" <?= $u['status'] !== 'active' ? 'disabled' : '' ?>>Email password-reset link</button>
                </form>
                <form method="post" action="/admin/customers/<?= (int) $u['id'] ?>/toggle" class="mb-2" data-confirm="<?= $u['status'] === 'active' ? 'Block this customer? They will be signed out and unable to order.' : 'Unblock this customer?' ?>"><?= csrf_field() ?>
                    <button class="btn <?= $u['status'] === 'active' ? 'btn--danger' : 'btn--outline' ?> btn--block" type="submit"><?= $u['status'] === 'active' ? 'Block customer' : 'Unblock customer' ?></button>
                </form>
                <details>
                    <summary class="muted" style="cursor:pointer">Delete personal data (right to erasure)…</summary>
                    <form method="post" action="/admin/customers/<?= (int) $u['id'] ?>/anonymise" class="mt-1">
                        <?= csrf_field() ?>
                        <p class="muted" style="font-size:12px">Erases name, email, phone, addresses, wishlist and bag. Orders &amp; GST invoices are kept (legal retention). This cannot be undone.</p>
                        <?= partial('admin/partials/f', ['name' => 'confirm', 'label' => 'Type DELETE to confirm', 'attrs' => 'autocomplete="off" required']) ?>
                        <button class="btn btn--danger btn--block" type="submit">Anonymise account</button>
                    </form>
                </details>
            </section>
        <?php else: ?>
            <section class="card"><h2>Anonymised</h2><p class="muted"><?= e($u['admin_note']) ?></p></section>
        <?php endif; ?>
    </div>
</div>
