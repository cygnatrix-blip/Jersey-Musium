<?php /** @var array $categories */ ?>
<div class="page-head">
    <div><h1>Categories</h1><p>Top-level categories appear in the main menu; sub-categories in the mega menu.</p></div>
    <a class="btn" href="/admin/categories/create">+ Add category</a>
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Slug</th><th class="num">Products</th><th class="num">Order</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= $c['parent_id'] ? '<span class="muted">' . e($c['parent_name']) . ' › </span>' : '' ?><a class="link" href="/admin/categories/<?= (int) $c['id'] ?>/edit"><?= e($c['name']) ?></a></td>
                <td class="muted">/c/<?= e($c['slug']) ?></td>
                <td class="num"><?= (int) $c['product_count'] ?></td>
                <td class="num"><?= (int) $c['sort_order'] ?></td>
                <td><?= $c['is_active'] ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--muted">Hidden</span>' ?><?= $c['show_in_menu'] ? '' : ' <span class="badge">Not in menu</span>' ?></td>
                <td class="num">
                    <form class="inline" method="post" action="/admin/categories/<?= (int) $c['id'] ?>/delete" data-confirm="Delete category <?= e($c['name']) ?>?"><?= csrf_field() ?><button class="btn btn--danger btn--sm">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?><tr><td colspan="6" class="empty-row">No categories yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
