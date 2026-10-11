<?php
/** @var array $items @var array $totals @var int $count @var array $addresses @var array $methods @var int $codFee @var array $states @var ?array $cart */
$t = $totals;
$hasAddr = (bool) $addresses;
$user = auth()->user();
?>
<div class="container">
    <div class="steps" aria-hidden="true"><span>Bag</span><i></i><span class="is-active">Address &amp; Payment</span><i></i><span>Done</span></div>

    <div class="bag">
        <form method="post" action="/checkout/place" id="checkout-form" data-checkout data-cod-fee="<?= (int) $codFee ?>" data-total="<?= (int) $t['total'] ?>">
            <?= csrf_field() ?>
            <section class="card">
                <p class="card__title">1. Delivery address</p>
                <?php foreach ($addresses as $i => $a): ?>
                    <label class="addr">
                        <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <strong><?= e($a['name']) ?></strong><span class="badge"><?= e($a['type']) ?></span>
                        <p><?= e(implode(', ', array_filter([$a['line1'], $a['line2'], $a['landmark'], $a['city'], $a['state']]))) ?> – <strong><?= e($a['pincode']) ?></strong></p>
                        <p>Mobile: <strong><?= e($a['phone']) ?></strong></p>
                    </label>
                <?php endforeach; ?>

                <label class="addr" <?= $hasAddr ? '' : 'hidden' ?>>
                    <input type="radio" name="address_id" value="0" data-new-address <?= $hasAddr ? '' : 'checked' ?>>
                    <strong>+ Add a new address</strong>
                </label>

                <div id="new-address" <?= $hasAddr && !error_for('line1') ? 'hidden' : '' ?>>
                    <div class="grid-2">
                        <?= partial('store/partials/field', ['name' => 'name', 'label' => 'Full name', 'value' => old('name', $user['name'] ?? ''), 'attrs' => 'autocomplete="name"']) ?>
                        <?= partial('store/partials/field', ['name' => 'phone', 'label' => 'Mobile number', 'value' => old('phone', $user['phone'] ?? ''), 'attrs' => 'inputmode="numeric" maxlength="10" autocomplete="tel-national"']) ?>
                    </div>
                    <?= partial('store/partials/field', ['name' => 'line1', 'label' => 'Flat, house no., building, street', 'attrs' => 'autocomplete="address-line1"']) ?>
                    <?= partial('store/partials/field', ['name' => 'line2', 'label' => 'Area, locality (optional)', 'attrs' => 'autocomplete="address-line2"']) ?>
                    <div class="grid-2">
                        <?= partial('store/partials/field', ['name' => 'pincode', 'label' => 'Pincode', 'attrs' => 'inputmode="numeric" maxlength="6" autocomplete="postal-code"']) ?>
                        <?= partial('store/partials/field', ['name' => 'city', 'label' => 'City / District', 'attrs' => 'autocomplete="address-level2"']) ?>
                    </div>
                    <div class="grid-2">
                        <label class="field <?= error_for('state') ? 'has-error' : '' ?>"><span>State</span>
                            <select class="select" name="state" autocomplete="address-level1">
                                <option value="">Select state</option>
                                <?php foreach ($states as $s): ?><option <?= old('state') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                            </select>
                            <?php if ($err = error_for('state')): ?><small class="field-error"><?= e($err) ?></small><?php endif; ?>
                        </label>
                        <?= partial('store/partials/field', ['name' => 'landmark', 'label' => 'Landmark (optional)']) ?>
                    </div>
                    <div class="flex gap wrap">
                        <?php foreach (['home' => 'Home', 'work' => 'Work', 'other' => 'Other'] as $k => $v): ?>
                            <label class="check"><input type="radio" name="type" value="<?= $k ?>" <?= old('type', 'home') === $k ? 'checked' : '' ?>> <?= $v ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card">
                <p class="card__title">2. Payment method</p>
                <?php if (!$methods): ?>
                    <p class="text-danger">Online payments are temporarily unavailable. Please try again shortly.</p>
                <?php endif; ?>
                <?php $first = true; foreach ($methods as $key => $m): ?>
                    <label class="pay-opt">
                        <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $first ? 'checked' : '' ?> required>
                        <?= icon($key === 'cod' ? 'cash' : 'lock', 22) ?>
                        <span><strong><?= e($m['label']) ?></strong><small><?= e($m['sub']) ?></small></span>
                    </label>
                <?php $first = false; endforeach; ?>
                <label class="field mt-2"><span>Order note (optional)</span><input class="input" name="notes" maxlength="300" placeholder="e.g. Gift wrap please"></label>
            </section>

            <section class="card">
                <p class="card__title">Items (<?= (int) $count ?>)</p>
                <?php foreach ($items as $it): ?>
                    <div class="line" style="grid-template-columns:56px 1fr;padding:.6rem 0">
                        <img src="<?= e(upload_url($it['image'])) ?>" alt="" width="56" height="70" style="width:56px" loading="lazy">
                        <div>
                            <p class="line__title" style="font-size:13px"><?= e($it['name']) ?></p>
                            <p class="line__sub mb-0">Size <?= e($it['size']) ?> · Qty <?= (int) $it['qty'] ?><?= $it['custom_name'] || $it['custom_number'] ? ' · Print ' . e(trim($it['custom_name'] . ' ' . $it['custom_number'])) : '' ?> · <strong><?= e(money($it['unit_paise'] * $it['qty'] / 100)) ?></strong></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        </form>

        <aside>
            <?= partial('store/partials/price-card', ['t' => $t, 'cart' => $cart, 'count' => $count, 'from' => 'checkout']) ?>
            <p class="muted" data-cod-note hidden style="font-size:12px">A COD fee of <?= e(money($codFee / 100)) ?> will be added.</p>
            <button class="btn btn--block btn--lg mt-2" type="submit" form="checkout-form" <?= $methods ? '' : 'disabled' ?> data-place-order>
                <?= icon('lock', 18) ?> Place order
            </button>
            <p class="secure"><?= icon('shield', 14) ?> By placing the order you agree to our <a href="/page/terms-and-conditions" style="text-decoration:underline">terms</a>.</p>
        </aside>
    </div>
</div>
