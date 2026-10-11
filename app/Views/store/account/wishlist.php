<?php /** @var array $items */ ?>
<div class="container" style="padding-top:20px;padding-bottom:40px">
    <h1 style="font-size:20px">My wishlist <span class="muted" style="font-weight:400"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span></h1>
    <?php if (!$items): ?>
        <div class="empty"><?= icon('heart', 64) ?><h2>Your wishlist is empty</h2><p>Save the jerseys you love and come back to them anytime.</p><a class="btn" href="/shop">Explore jerseys</a></div>
    <?php else: ?>
        <div class="pgrid pgrid--wide" data-wishlist-page>
            <?php foreach ($items as $p): ?><?= partial('store/partials/product-card', ['p' => $p]) ?><?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
