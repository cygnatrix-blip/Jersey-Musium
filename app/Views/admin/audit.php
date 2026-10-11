<?php /** @var App\Core\Paginator $logs */ ?>
<div class="page-head"><div><h1>Audit log</h1><p>Every admin action, who did it and from where.</p></div></div>
<form class="filters" method="get"><input class="input" name="q" value="<?= e(request()->query('q')) ?>" placeholder="Action or admin email"><button class="btn btn--dark">Search</button></form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs->items as $l): ?>
            <tr>
                <td><?= e(format_date($l['created_at'], 'd M Y, H:i:s')) ?></td>
                <td><?= e($l['admin_name'] ?? 'System') ?></td>
                <td><code><?= e($l['action']) ?></code></td>
                <td><?= $l['entity_type'] ? e($l['entity_type'] . ' #' . $l['entity_id']) : '—' ?></td>
                <td class="muted" style="font-size:12px;max-width:360px;word-break:break-word"><?= e(str_limit((string) $l['meta'], 160)) ?></td>
                <td class="muted"><?= e($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs->items): ?><tr><td colspan="6" class="empty-row">No activity yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $logs->links() ?>
</div>
