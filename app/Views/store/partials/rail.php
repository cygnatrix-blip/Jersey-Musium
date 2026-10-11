<?php
/** @var string $title @var array $products @var ?string $link @var ?string $eyebrow */
if (!$products) {
    return;
}
?>
<section class="section" data-reveal>
    <div class="container">
        <div class="section__head">
            <div>
                <?php if (!empty($eyebrow)): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
                <h2 class="section__title"><?= $title /* trusted markup from controller view */ ?></h2>
            </div>
            <?php if (!empty($link)): ?><a class="section__link" href="<?= e($link) ?>">View all &rarr;</a><?php endif; ?>
        </div>
        <div class="rail">
            <button class="rail__btn rail__btn--prev" type="button" data-rail="-1" aria-label="Scroll left"><?= icon('left') ?></button>
            <div class="rail__track">
                <?php foreach ($products as $p): ?><?= partial('store/partials/product-card', ['p' => $p]) ?><?php endforeach; ?>
            </div>
            <button class="rail__btn rail__btn--next" type="button" data-rail="1" aria-label="Scroll right"><?= icon('right') ?></button>
        </div>
    </div>
</section>
