<?php
use App\Services\CouponService;

/** @var array $p @var array $related @var array $reviews @var array $breakdown @var array $offers @var bool $inWishlist @var bool $canReview */
$off = discount_percent($p['mrp'], $p['price']);
$images = $p['images'] ?: [['path' => null, 'alt' => $p['name']]];
$kit = config('app.kit_types')[$p['kit_type']] ?? $p['kit_type'];
$totalReviews = array_sum($breakdown);
?>
<div class="container">
    <?= partial('store/partials/crumbs') ?>

    <div class="pdp">
        <div class="gallery" data-gallery>
            <div class="gallery__main" data-zoom>
                <img src="<?= e(upload_url($images[0]['path'])) ?>" alt="<?= e($images[0]['alt'] ?: $p['name']) ?>" width="900" height="1125" fetchpriority="high" data-main>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="gallery__thumbs" role="tablist" aria-label="Product images">
                    <?php foreach ($images as $i => $img): ?>
                        <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-src="<?= e(upload_url($img['path'])) ?>" aria-label="Image <?= $i + 1 ?>">
                            <img src="<?= e(upload_url($img['path'])) ?>" alt="" width="72" height="90" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="buybox">
            <h1><?= e($p['name']) ?></h1>
            <p class="buybox__team"><?= e(implode(' · ', array_filter([$p['team'], $p['league'], $p['season'], $kit]))) ?></p>
            <?php if ((int) $p['review_count'] > 0): ?>
                <a class="ratingpill" href="#reviews"><?= number_format((float) $p['avg_rating'], 1) ?> <?= icon('star', 14) ?><span><?= (int) $p['review_count'] ?> Ratings</span></a>
            <?php endif; ?>
            <hr>
            <div class="bigprice">
                <strong><?= e(money($p['price'])) ?></strong>
                <?php if ($off > 0): ?><span class="muted" style="font-size:16px">MRP <s><?= e(money($p['mrp'])) ?></s></span><span class="off">(<?= $off ?>% OFF)</span><?php endif; ?>
            </div>
            <p class="taxnote">inclusive of all taxes</p>

            <form id="buy-form" method="post" action="/cart/add" data-add-to-cart>
                <?= csrf_field() ?>
                <div class="sizes-head">
                    <h2>Select size</h2>
                    <button type="button" class="btn btn--ghost" data-open-sizechart>Size chart &rsaquo;</button>
                </div>
                <div class="sizes" role="radiogroup" aria-label="Size">
                    <?php foreach ($p['variants'] as $v): $stock = (int) $v['stock']; ?>
                        <label class="size">
                            <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" <?= $stock <= 0 ? 'disabled' : '' ?> aria-label="Size <?= e($v['size']) ?><?= $stock <= 0 ? ' (out of stock)' : '' ?>">
                            <span><?= e($v['size']) ?></span>
                            <?php if ($stock > 0 && $stock <= 3): ?><small><?= $stock ?> left</small><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="size-error" id="size-error" role="alert">Please select a size</p>

                <?php if ($p['allow_customization']): ?>
                    <details class="customize">
                        <summary><span><?= icon('shirt', 18) ?> Add your name &amp; number</span><span class="badge badge--warning">+<?= e(money($p['customization_price'])) ?></span></summary>
                        <div class="grid-2">
                            <label class="field"><span>Name (max 12)</span><input class="input" name="custom_name" maxlength="12" pattern="[A-Za-z .'-]*" placeholder="e.g. SAYAN" data-preview-name></label>
                            <label class="field"><span>Number (0-99)</span><input class="input" name="custom_number" inputmode="numeric" maxlength="2" pattern="\d{1,2}" placeholder="10" data-preview-num></label>
                        </div>
                        <div class="jersey-preview" aria-hidden="true">
                            <svg viewBox="0 0 64 64"><path fill="#e11d48" d="M22 6h20l4 4 12 6-5 13-7-3v32H18V26l-7 3-5-13 12-6z"/><path fill="#0b1020" d="M26 6h12c0 4-2.7 7-6 7s-6-3-6-7z"/></svg>
                            <span class="jersey-preview__name" data-out-name>YOUR NAME</span>
                            <span class="jersey-preview__num" data-out-num>10</span>
                        </div>
                        <p class="muted mb-0" style="font-size:12px">Personalised jerseys are made for you and can't be returned (exchange only for defects).</p>
                    </details>
                <?php endif; ?>

                <div class="buy-actions buy-actions--sticky">
                    <?php if ($p['stock'] > 0): ?>
                        <button class="btn btn--lg" type="submit"><?= icon('bag', 18) ?> Add to bag</button>
                    <?php else: ?>
                        <button class="btn btn--lg" type="button" disabled>Sold out</button>
                    <?php endif; ?>
                    <button class="btn btn--lg wish <?= $inWishlist ? 'is-on' : '' ?>" type="button" data-wishlist="<?= (int) $p['id'] ?>"><?= icon('heart', 18) ?> Wishlist</button>
                </div>
            </form>

            <hr>
            <h2 style="font-size:15px;text-transform:uppercase">Delivery options <?= icon('truck', 18) ?></h2>
            <form class="pin" data-pincode>
                <label class="sr-only" for="pincode">Pincode</label>
                <input class="input" id="pincode" inputmode="numeric" maxlength="6" placeholder="Enter pincode" pattern="[1-9][0-9]{5}">
                <button class="btn btn--outline" type="submit">Check</button>
            </form>
            <p class="pin-result" data-pincode-result aria-live="polite"></p>
            <ul class="perks mt-1">
                <li><?= icon('check', 16) ?> Free delivery on orders above <?= e(money(setting('free_shipping_threshold'))) ?></li>
                <?php if (setting('cod_enabled') === '1'): ?><li><?= icon('check', 16) ?> Pay on delivery available</li><?php endif; ?>
                <li><?= icon('check', 16) ?> Easy <?= (int) setting('return_window_days') ?> days return &amp; exchange</li>
            </ul>

            <?php if ($offers): ?>
                <hr>
                <h2 style="font-size:15px;text-transform:uppercase">Best offers <?= icon('tag', 18) ?></h2>
                <ul class="offers">
                    <?php foreach ($offers as $o): ?>
                        <li><?= icon('tag', 16) ?><span><?= e(CouponService::describe($o)) ?> – use code <code><?= e($o['code']) ?></code><?php if ($o['description']): ?><br><span class="muted"><?= e($o['description']) ?></span><?php endif; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <hr>
            <div class="acc">
                <details open>
                    <summary>Product details</summary>
                    <div class="acc__body">
                        <?php if ($p['short_description']): ?><p><?= e($p['short_description']) ?></p><?php endif; ?>
                        <?= nl2br(e($p['description'])) ?>
                    </div>
                </details>
                <details>
                    <summary>Specifications</summary>
                    <div class="acc__body">
                        <dl class="specs">
                            <?php foreach (['Team' => $p['team'], 'League' => $p['league'], 'Season' => $p['season'], 'Kit' => $kit, 'Brand' => $p['brand'], 'Fit' => config('app.genders')[$p['gender']] ?? '', 'SKU' => $p['sku']] as $k => $v): if (!$v) continue; ?>
                                <div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </details>
                <details id="sizechart">
                    <summary>Size chart</summary>
                    <div class="acc__body">
                        <table class="sizechart">
                            <thead><tr><th>Size</th><th>Chest (in)</th><th>Length (in)</th><th>Shoulder (in)</th></tr></thead>
                            <tbody>
                            <tr><td>S</td><td>38</td><td>27</td><td>17</td></tr>
                            <tr><td>M</td><td>40</td><td>28</td><td>17.5</td></tr>
                            <tr><td>L</td><td>42</td><td>29</td><td>18</td></tr>
                            <tr><td>XL</td><td>44</td><td>30</td><td>18.5</td></tr>
                            <tr><td>XXL</td><td>46</td><td>31</td><td>19</td></tr>
                            </tbody>
                        </table>
                        <p class="muted mt-1 mb-0" style="font-size:12px">Fan-version fit. If you are between sizes, choose the larger one.</p>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <section class="section" id="reviews" style="border-top:1px solid var(--line)">
        <div class="section__head"><h2 class="section__title">Ratings &amp; <span>reviews</span></h2></div>
        <div class="reviews">
            <div>
                <?php if ($totalReviews): ?>
                    <div class="rsummary__big"><?= number_format((float) $p['avg_rating'], 1) ?> <?= icon('star', 30) ?></div>
                    <p class="muted"><?= $totalReviews ?> verified ratings</p>
                    <div class="rbars">
                        <?php foreach ($breakdown as $star => $count): ?>
                            <div class="rbar"><span><?= $star ?> ★</span><div><i style="width:<?= $totalReviews ? round($count / $totalReviews * 100) : 0 ?>%"></i></div><span><?= $count ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No reviews yet. Be the first to rate this jersey.</p>
                <?php endif; ?>

                <?php if ($canReview): ?>
                    <form class="card mt-2" method="post" action="/p/<?= e($p['slug']) ?>/review">
                        <?= csrf_field() ?>
                        <p class="card__title">Rate this product</p>
                        <div class="stars-input" role="radiogroup" aria-label="Rating">
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                                <input type="radio" id="r<?= $s ?>" name="rating" value="<?= $s ?>" required><label for="r<?= $s ?>" title="<?= $s ?> stars">★</label>
                            <?php endfor; ?>
                        </div>
                        <label class="field mt-1"><span>Title</span><input class="input" name="title" maxlength="120"></label>
                        <label class="field"><span>Review</span><textarea class="textarea" name="body" maxlength="2000"></textarea></label>
                        <button class="btn btn--dark btn--block" type="submit">Submit review</button>
                    </form>
                <?php elseif (!auth()->check()): ?>
                    <a class="btn btn--outline mt-2" href="/login">Login to write a review</a>
                <?php endif; ?>
            </div>
            <div>
                <?php foreach ($reviews as $r): ?>
                    <article class="review">
                        <span class="review__stars"><?= (int) $r['rating'] ?> ★</span>
                        <?php if ($r['title']): ?><strong style="margin-left:.5rem"><?= e($r['title']) ?></strong><?php endif; ?>
                        <?php if ($r['body']): ?><p class="mt-1 mb-0"><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
                        <p class="review__meta"><?= e($r['user_name']) ?> · <?= e(format_date($r['created_at'])) ?><?= $r['is_verified_purchase'] ? ' · <span class="text-success">✔ Verified buyer</span>' : '' ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<?= partial('store/partials/rail', ['title' => 'You may also <span>like</span>', 'products' => $related, 'link' => $p['team'] ? '/search?q=' . rawurlencode($p['team']) : null]) ?>
