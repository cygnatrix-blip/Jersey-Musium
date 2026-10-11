<?php
use App\Services\CouponService;

/** @var ?array $cart @var array $items @var array $totals @var bool $has_issue @var int $count @var array $offers */
$t = $totals;
$freeAt = paise(setting('free_shipping_threshold'));
$afterDiscount = $t['subtotal'] - $t['discount'];
?>
<div class="container">
    <?php if (!$items): ?>
        <div class="empty" style="padding:80px 16px">
            <?= icon('bag', 72) ?>
            <h1 style="font-size:22px">Hey, it feels so light!</h1>
            <p>There is nothing in your bag. Let's add some jerseys.</p>
            <a class="btn btn--outline" href="/wishlist">Add items from wishlist</a>
            <a class="btn" href="/shop" style="margin-left:.5rem">Shop now</a>
        </div>
    <?php else: ?>
        <div class="steps" aria-hidden="true"><span class="is-active">Bag</span><i></i><span>Address &amp; Payment</span><i></i><span>Done</span></div>
        <div class="bag">
            <div>
                <?php if ($t['discount'] === 0 && $offers): ?>
                    <div class="card">
                        <p class="card__title"><?= icon('tag', 16) ?> Available offers</p>
                        <ul class="offers">
                            <?php foreach (array_slice($offers, 0, 3) as $o): ?>
                                <li><?= icon('tag', 14) ?><span><?= e(CouponService::describe($o)) ?> – code <code><?= e($o['code']) ?></code></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <p class="card__title"><?= (int) $count ?> item<?= $count === 1 ? '' : 's' ?> in your bag</p>
                    <?php foreach ($items as $it): ?>
                        <div class="line">
                            <a href="/p/<?= e($it['slug']) ?>"><img src="<?= e(upload_url($it['image'])) ?>" alt="<?= e($it['name']) ?>" width="96" height="120" loading="lazy"></a>
                            <div>
                                <p class="line__title"><?= e($it['team'] ?: $it['name']) ?></p>
                                <p class="line__sub"><a href="/p/<?= e($it['slug']) ?>"><?= e($it['name']) ?></a></p>
                                <div class="line__opts">
                                    <span class="badge">Size: <?= e($it['size']) ?></span>
                                    <form method="post" action="/cart/update">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                        <label class="sr-only" for="qty-<?= (int) $it['id'] ?>">Quantity</label>
                                        <select class="select" id="qty-<?= (int) $it['id'] ?>" name="qty" data-autosubmit-select>
                                            <?php for ($q = 1; $q <= min(10, max(1, (int) $it['stock'], $it['qty'])); $q++): ?>
                                                <option value="<?= $q ?>" <?= $q === $it['qty'] ? 'selected' : '' ?>>Qty: <?= $q ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </form>
                                    <?php if ($it['custom_name'] || $it['custom_number']): ?>
                                        <span class="line__custom">Print: <?= e(trim($it['custom_name'] . ' ' . $it['custom_number'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="price">
                                    <strong><?= e(money($it['unit_paise'] * $it['qty'] / 100)) ?></strong>
                                    <?php if ($it['mrp_paise'] > $it['unit_paise']): ?>
                                        <s><?= e(money($it['mrp_paise'] * $it['qty'] / 100)) ?></s>
                                        <span class="off"><?= discount_percent($it['mrp_paise'], $it['unit_paise']) ?>% OFF</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($it['issue']): ?><p class="line__issue mt-1 mb-0"><?= e($it['issue']) ?></p><?php endif; ?>
                                <div class="line__actions">
                                    <form method="post" action="/cart/remove">
                                        <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                        <button type="submit"><?= icon('trash', 14) ?> Remove</button>
                                    </form>
                                    <?php if (auth()->check()): ?>
                                        <button type="button" data-wishlist="<?= (int) $it['product_id'] ?>" data-move-to-wishlist="<?= (int) $it['id'] ?>">Move to wishlist</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside>
                <?= partial('store/partials/price-card', ['t' => $t, 'cart' => $cart, 'count' => $count, 'from' => 'cart']) ?>
                <?php if ($has_issue): ?>
                    <p class="text-danger mt-2" style="font-weight:600">Some items need your attention before checkout.</p>
                    <button class="btn btn--block btn--lg mt-1" disabled>Place order</button>
                <?php else: ?>
                    <a class="btn btn--block btn--lg mt-2" href="/checkout">Place order</a>
                <?php endif; ?>
                <p class="secure"><?= icon('lock', 14) ?> Safe &amp; secure payments · 100% authentic products</p>
            </aside>
        </div>
    <?php endif; ?>
</div>
