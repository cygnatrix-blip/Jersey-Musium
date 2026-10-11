<?php
/** @var array $p  product card row from CatalogService::cardSelect() */
$off = discount_percent($p['mrp'], $p['price']);
$stock = (int) ($p['stock'] ?? 1);
$lazy = $lazy ?? true;
?>
<article class="pcard">
    <a class="pcard__media" href="/p/<?= e($p['slug']) ?>">
        <img src="<?= e(upload_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" width="600" height="750"
             <?= $lazy ? 'loading="lazy" decoding="async"' : 'fetchpriority="high"' ?>>
        <?php if (!empty($p['image_alt'])): ?>
            <img class="pcard__alt" src="<?= e(upload_url($p['image_alt'])) ?>" alt="" width="600" height="750" loading="lazy" decoding="async">
        <?php endif; ?>
        <span class="pcard__tags">
            <?php if (!empty($p['is_new_arrival'])): ?><span class="tag tag--new">New</span><?php endif; ?>
            <?php if ($off >= 40): ?><span class="tag tag--hot"><?= $off ?>% off</span><?php endif; ?>
            <?php if (!empty($p['allow_customization'])): ?><span class="tag tag--custom">Name + No.</span><?php endif; ?>
            <?php if ($stock > 0 && $stock <= 5): ?><span class="tag">Only <?= $stock ?> left</span><?php endif; ?>
        </span>
        <?php if ((int) $p['review_count'] > 0): ?>
            <span class="pcard__rating"><?= number_format((float) $p['avg_rating'], 1) ?> <?= icon('star', 11) ?><em><?= (int) $p['review_count'] ?></em></span>
        <?php endif; ?>
        <?php if ($stock <= 0): ?><span class="pcard__soldout"><span>Sold out</span></span><?php endif; ?>
    </a>
    <button class="wish-btn" type="button" data-wishlist="<?= (int) $p['id'] ?>" aria-label="Add <?= e($p['name']) ?> to wishlist"><?= icon('heart', 18) ?></button>
    <div class="pcard__body">
        <h3 class="pcard__brand"><?= e($p['team'] ?: $p['brand']) ?></h3>
        <p class="pcard__name"><a href="/p/<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></p>
        <div class="price">
            <strong><?= e(money($p['price'])) ?></strong>
            <?php if ($off > 0): ?><s><?= e(money($p['mrp'])) ?></s><span class="off">(<?= $off ?>% OFF)</span><?php endif; ?>
        </div>
    </div>
</article>
