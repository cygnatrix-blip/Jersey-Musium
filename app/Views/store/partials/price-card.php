<?php
/** @var array $t totals (paise) @var ?array $cart @var int $count @var string $from cart|checkout */
$freeAt = paise(setting('free_shipping_threshold'));
$after = $t['subtotal'] - $t['discount'];
$saved = ($t['mrp_total'] - $t['subtotal']) + $t['discount'];
?>
<div class="card pricecard">
    <p class="card__title">Coupons</p>
    <?php if ($t['coupon']): ?>
        <div class="coupon-applied">
            <span><?= icon('check', 16) ?> <?= e($t['coupon']['code']) ?> applied</span>
            <form method="post" action="/cart/coupon/remove"><?= csrf_field() ?><input type="hidden" name="from" value="<?= e($from) ?>"><button class="btn btn--ghost" type="submit">Remove</button></form>
        </div>
    <?php else: ?>
        <form class="coupon-form" method="post" action="/cart/coupon">
            <?= csrf_field() ?>
            <input type="hidden" name="from" value="<?= e($from) ?>">
            <label class="sr-only" for="coupon">Coupon code</label>
            <input class="input" id="coupon" name="code" placeholder="Enter coupon code" maxlength="40" autocomplete="off">
            <button class="btn btn--outline" type="submit">Apply</button>
        </form>
    <?php endif; ?>
</div>

<div class="card pricecard">
    <p class="card__title">Price details (<?= (int) $count ?> item<?= $count === 1 ? '' : 's' ?>)</p>
    <?php if ($t['shipping'] > 0 && $after < $freeAt): ?>
        <div class="free-ship">
            Add <strong><?= e(money(($freeAt - $after) / 100)) ?></strong> more for <strong>FREE delivery</strong>
            <div class="free-ship__bar"><i style="width:<?= min(100, (int) round($after / max(1, $freeAt) * 100)) ?>%"></i></div>
        </div>
    <?php endif; ?>
    <dl>
        <dt>Total MRP</dt><dd><?= e(money($t['mrp_total'] / 100)) ?></dd>
        <?php if ($t['mrp_total'] > $t['subtotal']): ?><dt>Discount on MRP</dt><dd class="save">-<?= e(money(($t['mrp_total'] - $t['subtotal']) / 100)) ?></dd><?php endif; ?>
        <?php if ($t['discount'] > 0): ?><dt>Coupon discount</dt><dd class="save">-<?= e(money($t['discount'] / 100)) ?></dd><?php endif; ?>
        <dt>Delivery fee</dt><dd><?= $t['shipping'] > 0 ? e(money($t['shipping'] / 100)) : '<span class="save">FREE</span>' ?></dd>
        <?php if ($t['cod_fee'] > 0): ?><dt>COD fee</dt><dd><?= e(money($t['cod_fee'] / 100)) ?></dd><?php endif; ?>
        <dt class="total">Total amount</dt><dd class="total" data-grand-total><?= e(money($t['total'] / 100)) ?></dd>
    </dl>
    <?php if ($saved > 0): ?><div class="savings-note mb-0">You save <?= e(money($saved / 100)) ?> on this order 🎉</div><?php endif; ?>
    <p class="muted mt-1 mb-0" style="font-size:12px">Prices include GST (<?= e(money($t['tax'] / 100, true)) ?>).</p>
</div>
