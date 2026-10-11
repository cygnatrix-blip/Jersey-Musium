<?php
/** @var array $menu */
$pages = [];
try {
    $pages = db()->select('SELECT title, slug FROM pages WHERE is_active = 1 AND show_in_footer = 1 ORDER BY title');
} catch (Throwable) {
}
$socials = array_filter([
    'instagram' => setting('social_instagram'),
    'facebook'  => setting('social_facebook'),
    'x'         => setting('social_x'),
    'youtube'   => setting('social_youtube'),
]);
?>
<footer class="footer">
    <div class="container">
        <section class="trust" aria-label="Why shop with us" style="margin-bottom:36px">
            <div><?= icon('shield', 28) ?><span><strong>Quality checked</strong>Every jersey inspected</span></div>
            <div><?= icon('truck', 28) ?><span><strong>Fast delivery</strong>Free above <?= e(money(setting('free_shipping_threshold'))) ?></span></div>
            <div><?= icon('return', 28) ?><span><strong>Easy returns</strong><?= (int) setting('return_window_days') ?>-day return window</span></div>
            <div><?= icon('lock', 28) ?><span><strong>Secure payments</strong>UPI · Cards · COD</span></div>
        </section>

        <div class="footer__grid">
            <div>
                <h2>Shop</h2>
                <ul>
                    <?php foreach (array_slice($menu, 0, 7) as $c): ?>
                        <li><a href="/c/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="/shop?sort=new">New Arrivals</a></li>
                </ul>
            </div>
            <div>
                <h2>Customer care</h2>
                <ul>
                    <li><a href="/track-order">Track order</a></li>
                    <li><a href="/account/orders">Returns &amp; exchanges</a></li>
                    <li><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li>
                    <li><a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li>
                </ul>
            </div>
            <div>
                <h2>Policies</h2>
                <ul>
                    <?php foreach ($pages as $pg): ?>
                        <li><a href="/page/<?= e($pg['slug']) ?>"><?= e($pg['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="footer__news">
                <h2>Join the squad</h2>
                <p>Kit drops, restocks and members-only offers. No spam.</p>
                <form method="post" action="/newsletter">
                    <?= csrf_field() ?>
                    <label class="sr-only" for="nl-email">Email</label>
                    <input class="input" id="nl-email" type="email" name="email" placeholder="you@example.com" required>
                    <button class="btn btn--dark" type="submit">Subscribe</button>
                </form>
                <?php if ($socials): ?>
                    <div class="socials">
                        <?php foreach ($socials as $net => $link): ?>
                            <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($net)) ?>"><?= icon($net, 18) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer__bottom">
            <span>&copy; <?= date('Y') ?> <?= e(setting('store_name')) ?>. All rights reserved. <?= e(setting('store_address')) ?></span>
            <div class="paylogos" aria-label="Payment methods"><span>UPI</span><span>VISA</span><span>MASTERCARD</span><span>RUPAY</span><span>NETBANKING</span><span>COD</span></div>
        </div>
    </div>
</footer>
