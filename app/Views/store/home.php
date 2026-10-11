<?php
/** @var array $hero @var array $strip @var array $grid @var array $categories @var array $teams
 *  @var array $featured @var array $newArrivals @var array $deals @var array $bestsellers @var string $h1 */
?>
<h1 class="sr-only"><?= e($h1) ?></h1>

<?php if ($hero): ?>
    <section class="hero" data-slider aria-roledescription="carousel" aria-label="Offers">
        <div class="hero__track">
            <?php foreach ($hero as $i => $b): ?>
                <a class="hero__slide <?= $b['theme'] === 'light' ? 'hero__slide--light' : '' ?>" href="<?= e($b['link_url'] ?: '/shop') ?>"
                   aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($hero) ?>">
                    <picture>
                        <?php if ($b['image_mobile']): ?><source media="(max-width: 767px)" srcset="<?= e(upload_url($b['image_mobile'])) ?>"><?php endif; ?>
                        <img src="<?= e(upload_url($b['image_desktop'])) ?>" alt="<?= e($b['title']) ?>" width="1600" height="610"
                             <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                    </picture>
                    <div class="hero__copy">
                        <h2 class="hero__title"><?= e($b['title']) ?></h2>
                        <?php if ($b['subtitle']): ?><p class="hero__sub"><?= e($b['subtitle']) ?></p><?php endif; ?>
                        <?php if ($b['cta_label']): ?><span class="btn"><?= e($b['cta_label']) ?></span><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($hero) > 1): ?>
            <button class="hero__nav hero__nav--prev" type="button" data-slide="-1" aria-label="Previous slide"><?= icon('left') ?></button>
            <button class="hero__nav hero__nav--next" type="button" data-slide="1" aria-label="Next slide"><?= icon('right') ?></button>
            <div class="hero__dots"><?php foreach ($hero as $i => $b): ?><button type="button" aria-label="Go to slide <?= $i + 1 ?>"></button><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="hero hero--brand">
        <div class="hero__slide" style="aspect-ratio:auto;min-height:clamp(420px,62vh,620px)">
            <div class="hero__copy">
                <p class="eyebrow" style="color:#ffd166">Season 26/27 kits are here</p>
                <h2 class="hero__title" style="color:#fff">Wear the<br><span style="color:var(--brand)">legend.</span></h2>
                <p class="hero__sub" style="color:#fff">Club, country &amp; retro football jerseys. Add your name and number.</p>
                <a class="btn btn--lg" href="/shop">Shop jerseys</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($categories): ?>
    <section class="section" data-reveal>
        <div class="container">
            <div class="section__head"><h2 class="section__title">Shop by <span>category</span></h2></div>
            <div class="cats">
                <?php foreach ($categories as $c): ?>
                    <a class="cat" href="/c/<?= e($c['slug']) ?>">
                        <div class="cat__img">
                            <?php if ($c['image']): ?>
                                <img src="<?= e(upload_url($c['image'])) ?>" alt="" width="160" height="160" loading="lazy">
                            <?php else: ?>
                                <?= icon('shirt', 44) ?>
                            <?php endif; ?>
                        </div>
                        <div class="cat__name"><?= e($c['name']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($teams): ?>
    <section class="section" style="padding-top:0">
        <div class="container">
            <p class="eyebrow">Shop by team</p>
            <div class="chips">
                <?php foreach ($teams as $t): ?>
                    <a class="chip" href="/shop?team[]=<?= e(rawurlencode($t['team'])) ?>"><?= e($t['team']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?= partial('store/partials/rail', ['title' => 'Deals of the <span>day</span>', 'eyebrow' => 'Biggest discounts', 'products' => $deals, 'link' => '/shop?sort=discount']) ?>

<?php if ($strip): ?>
    <section class="section" data-reveal>
        <div class="container promo-grid">
            <?php foreach (array_slice($strip, 0, 3) as $b): ?>
                <a class="promo" href="<?= e($b['link_url'] ?: '/shop') ?>">
                    <img src="<?= e(upload_url($b['image_desktop'])) ?>" alt="<?= e($b['title']) ?>" width="800" height="450" loading="lazy">
                    <span class="promo__copy"><strong><?= e($b['title']) ?></strong><?= e($b['subtitle']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?= partial('store/partials/rail', ['title' => 'Fan <span>favourites</span>', 'eyebrow' => 'Featured', 'products' => $featured, 'link' => '/shop']) ?>
<?= partial('store/partials/rail', ['title' => 'Fresh <span>drops</span>', 'eyebrow' => 'New arrivals', 'products' => $newArrivals, 'link' => '/shop?sort=new']) ?>

<?php if ($grid): ?>
    <section class="section" data-reveal>
        <div class="container promo-grid">
            <?php foreach ($grid as $b): ?>
                <a class="promo" href="<?= e($b['link_url'] ?: '/shop') ?>">
                    <img src="<?= e(upload_url($b['image_desktop'])) ?>" alt="<?= e($b['title']) ?>" width="800" height="450" loading="lazy">
                    <span class="promo__copy"><strong><?= e($b['title']) ?></strong><?= e($b['subtitle']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?= partial('store/partials/rail', ['title' => 'Best <span>sellers</span>', 'eyebrow' => 'Most loved', 'products' => $bestsellers, 'link' => '/shop']) ?>

<section class="section">
    <div class="container prose" style="padding:0;max-width:none">
        <h2>India's home for football jerseys</h2>
        <p><?= e(setting('store_name')) ?> brings you jerseys from the clubs and countries you love – Premier League, La Liga, Serie A, Bundesliga,
            Indian Super League and international teams – plus timeless retro kits. Personalise your shirt with your name and number,
            pay with UPI, cards or Cash on Delivery, and enjoy easy <?= (int) setting('return_window_days') ?>-day returns.</p>
    </div>
</section>
