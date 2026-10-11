<?php /** @var array $pages */ ?>
<div class="page-head"><div><h1>Pages</h1><p>Razorpay &amp; Cashfree require Privacy, Terms, Refund, Shipping and Contact pages before going live.</p></div><a class="btn" href="/admin/pages/create">+ New page</a></div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Title</th><th>URL</th><th>Footer</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
        <?php foreach ($pages as $pg): ?>
            <tr>
                <td><a class="link" href="/admin/pages/<?= (int) $pg['id'] ?>/edit"><?= e($pg['title']) ?></a></td>
                <td><a class="muted" href="/page/<?= e($pg['slug']) ?>" target="_blank" rel="noopener">/page/<?= e($pg['slug']) ?> ↗</a></td>
                <td><?= $pg['show_in_footer'] ? 'Yes' : 'No' ?></td>
                <td><?= $pg['is_active'] ? '<span class="badge badge--success">Published</span>' : '<span class="badge badge--muted">Draft</span>' ?></td>
                <td><?= e(format_date($pg['updated_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
