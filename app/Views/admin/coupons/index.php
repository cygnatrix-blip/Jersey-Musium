<?php use App\Services\CouponService; /** @var array $coupons */ ?>
<div class="page-head"><div><h1>Promo codes</h1><p>Create discount codes for campaigns, influencers and first orders.</p></div><a class="btn" href="/admin/coupons/create">+ Create promo code</a></div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Code</th><th>Offer</th><th>Validity</th><th class="num">Used</th><th class="num">Discount given</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($coupons as $c):
            $expired = $c['ends_at'] && strtotime($c['ends_at']) < time();
            $scheduled = $c['starts_at'] && strtotime($c['starts_at']) > time(); ?>
            <tr>
                <td><a class="link" href="/admin/coupons/<?= (int) $c['id'] ?>/edit"><strong><?= e($c['code']) ?></strong></a><br><span class="muted"><?= e(str_limit($c['description'], 40)) ?></span></td>
                <td><?= e(CouponService::describe($c)) ?><?= $c['category_name'] ? '<br><span class="muted">Only ' . e($c['category_name']) . '</span>' : '' ?><?= $c['first_order_only'] ? '<br><span class="badge">First order</span>' : '' ?></td>
                <td><?= $c['starts_at'] ? e(format_date($c['starts_at'])) : 'Now' ?> → <?= $c['ends_at'] ? e(format_date($c['ends_at'])) : 'No end' ?></td>
                <td class="num"><?= (int) $c['used_count'] ?><?= $c['usage_limit'] ? ' / ' . (int) $c['usage_limit'] : '' ?></td>
                <td class="num"><?= e(money($c['total_discount'])) ?></td>
                <td><?php if (!$c['is_active']): ?><span class="badge badge--muted">Off</span><?php elseif ($expired): ?><span class="badge badge--danger">Expired</span><?php elseif ($scheduled): ?><span class="badge badge--info">Scheduled</span><?php else: ?><span class="badge badge--success">Live</span><?php endif; ?><?= $c['is_public'] ? '' : ' <span class="badge">Hidden</span>' ?></td>
                <td class="num"><form class="inline" method="post" action="/admin/coupons/<?= (int) $c['id'] ?>/delete" data-confirm="Delete <?= e($c['code']) ?>?"><?= csrf_field() ?><button class="btn btn--danger btn--sm">Delete</button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$coupons): ?><tr><td colspan="7" class="empty-row">No promo codes yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
