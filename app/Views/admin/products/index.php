<?php /** @var App\Core\Paginator $products @var array $categories */ $rq = request(); ?>
<div class="page-head">
    <div><h1>Products</h1><p><?= number_format($products->total) ?> products</p></div>
    <a class="btn" href="/admin/products/create">+ Add product</a>
</div>
<form class="filters" method="get">
    <input class="input" name="q" value="<?= e($rq->query('q')) ?>" placeholder="Search name, SKU, team">
    <select class="select" name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $rq->query('category') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select class="select" name="status"><option value="">Any status</option><option value="active" <?= $rq->query('status') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $rq->query('status') === 'inactive' ? 'selected' : '' ?>>Hidden</option></select>
    <select class="select" name="stock"><option value="">Any stock</option><option value="low" <?= $rq->query('stock') === 'low' ? 'selected' : '' ?>>Low (≤5)</option><option value="out" <?= $rq->query('stock') === 'out' ? 'selected' : '' ?>>Out of stock</option></select>
    <button class="btn btn--dark" type="submit">Filter</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products->items as $p): ?>
            <tr>
                <td><img class="thumb" src="<?= e(upload_url($p['image'])) ?>" alt="" width="40" height="50" loading="lazy"></td>
                <td><a class="link" href="/admin/products/<?= (int) $p['id'] ?>/edit"><?= e($p['name']) ?></a><br><span class="muted"><?= e($p['sku']) ?> · <?= e($p['team']) ?></span></td>
                <td><?= e($p['category_name']) ?></td>
                <td class="num"><?= e(money($p['price'])) ?><br><s class="muted"><?= e(money($p['mrp'])) ?></s></td>
                <td class="num"><span class="badge badge--<?= $p['stock'] == 0 ? 'danger' : ($p['stock'] <= 5 ? 'warning' : 'success') ?>"><?= (int) $p['stock'] ?></span></td>
                <td><?= $p['is_active'] ? '<span class="badge badge--success">Live</span>' : '<span class="badge badge--muted">Hidden</span>' ?><?= $p['is_featured'] ? ' <span class="badge badge--info">Featured</span>' : '' ?></td>
                <td class="num"><a class="btn btn--outline btn--sm" href="/p/<?= e($p['slug']) ?>" target="_blank" rel="noopener">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products->items): ?><tr><td colspan="7" class="empty-row">No products found. <a class="link" href="/admin/products/create">Add your first product</a></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $products->links() ?>
</div>
