<?php /** @var App\Core\Paginator $customers @var array $counts */ $cur = (string) request()->query('status', ''); ?>
<div class="page-head"><div><h1>Customers</h1><p><?= number_format($customers->total) ?> registered customers</p></div></div>
<nav class="tabs"><?php foreach (['' => 'All', 'active' => 'Active', 'blocked' => 'Blocked', 'deleted' => 'Anonymised'] as $k => $lbl): ?><a href="/admin/customers<?= $k ? '?status=' . $k : '' ?>" class="<?= $cur === $k ? 'is-active' : '' ?>"><?= $lbl ?><?php if ($k && !empty($counts[$k])): ?><span><?= (int) $counts[$k] ?></span><?php endif; ?></a><?php endforeach; ?></nav>
<form class="filters" method="get"><?php if ($cur): ?><input type="hidden" name="status" value="<?= e($cur) ?>"><?php endif; ?><input class="input" name="q" value="<?= e(request()->query('q')) ?>" placeholder="Name, email or phone"><button class="btn btn--dark">Search</button></form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Customer</th><th>Phone</th><th>Joined</th><th class="num">Orders</th><th class="num">Lifetime value</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($customers->items as $u): ?>
            <tr>
                <td><a class="link" href="/admin/customers/<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a><br><span class="muted"><?= e($u['email']) ?></span></td>
                <td><?= e($u['phone']) ?></td>
                <td><?= e(format_date($u['created_at'])) ?></td>
                <td class="num"><?= (int) $u['orders'] ?></td>
                <td class="num"><?= e(money($u['ltv'])) ?></td>
                <td><span class="badge badge--<?= ['active' => 'success', 'blocked' => 'danger'][$u['status']] ?? 'muted' ?>"><?= e($u['status'] === 'deleted' ? 'Anonymised' : label($u['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$customers->items): ?><tr><td colspan="6" class="empty-row">No customers found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $customers->links() ?>
</div>
