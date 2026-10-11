<?php /** @var App\Core\Paginator $reviews @var string $status */ ?>
<div class="page-head"><div><h1>Reviews</h1><p>Approved reviews appear on product pages and in Google rich results.</p></div></div>
<nav class="tabs"><?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $lbl): ?><a href="/admin/reviews?status=<?= $k ?>" class="<?= $status === $k ? 'is-active' : '' ?>"><?= $lbl ?></a><?php endforeach; ?></nav>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Product</th><th>Rating</th><th>Review</th><th>Customer</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($reviews->items as $r): ?>
            <tr>
                <td><a class="link" href="/p/<?= e($r['slug']) ?>" target="_blank" rel="noopener"><?= e(str_limit($r['product_name'], 34)) ?></a></td>
                <td><strong><?= (int) $r['rating'] ?> ★</strong></td>
                <td style="max-width:420px"><strong><?= e($r['title']) ?></strong><br><?= e(str_limit($r['body'], 220)) ?></td>
                <td><?= e($r['user_name']) ?><?= $r['is_verified_purchase'] ? '<br><span class="badge badge--success">Verified</span>' : '' ?><br><span class="muted"><?= e(format_date($r['created_at'])) ?></span></td>
                <td class="num">
                    <?php if ($r['status'] !== 'approved'): ?><form class="inline" method="post" action="/admin/reviews/<?= (int) $r['id'] ?>"><?= csrf_field() ?><input type="hidden" name="status" value="approved"><button class="btn btn--sm">Approve</button></form><?php endif; ?>
                    <?php if ($r['status'] !== 'rejected'): ?><form class="inline" method="post" action="/admin/reviews/<?= (int) $r['id'] ?>"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn--danger btn--sm">Reject</button></form><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$reviews->items): ?><tr><td colspan="5" class="empty-row">Nothing here.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?= $reviews->links() ?>
</div>
