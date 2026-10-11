<?php use App\Controllers\Admin\BannerController; /** @var array $banners */ ?>
<div class="page-head"><div><h1>Offer banners</h1><p>Hero slider, promo strips and listing banners. Schedule them for sales &amp; match days.</p></div><a class="btn" href="/admin/banners/create">+ New banner</a></div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th></th><th>Title</th><th>Placement</th><th>Schedule</th><th class="num">Order</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($banners as $b):
            $live = $b['is_active'] && (!$b['starts_at'] || strtotime($b['starts_at']) <= time()) && (!$b['ends_at'] || strtotime($b['ends_at']) >= time()); ?>
            <tr>
                <td><img src="<?= e(upload_url($b['image_desktop'])) ?>" alt="" style="width:120px;height:46px;object-fit:cover;border-radius:6px"></td>
                <td><a class="link" href="/admin/banners/<?= (int) $b['id'] ?>/edit"><?= e($b['title']) ?></a><br><span class="muted"><?= e($b['link_url']) ?></span></td>
                <td><?= e(explode(' (', BannerController::PLACEMENTS[$b['placement']] ?? $b['placement'])[0]) ?></td>
                <td><?= $b['starts_at'] ? e(format_date($b['starts_at'], 'd M H:i')) : 'Now' ?> → <?= $b['ends_at'] ? e(format_date($b['ends_at'], 'd M H:i')) : '∞' ?></td>
                <td class="num"><?= (int) $b['sort_order'] ?></td>
                <td><?= $live ? '<span class="badge badge--success">Live</span>' : '<span class="badge badge--muted">Not live</span>' ?></td>
                <td class="num"><form class="inline" method="post" action="/admin/banners/<?= (int) $b['id'] ?>/delete" data-confirm="Delete this banner?"><?= csrf_field() ?><button class="btn btn--danger btn--sm">Delete</button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$banners): ?><tr><td colspan="7" class="empty-row">No banners yet – the homepage shows a default hero until you add one.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
