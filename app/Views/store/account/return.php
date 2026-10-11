<?php /** @var array $order @var array $items @var array $reasons */ ?>
<div class="container account">
    <?= partial('store/account/nav') ?>
    <div>
        <h1 style="font-size:22px">Return or exchange</h1>
        <p class="muted">Order <?= e($order['order_number']) ?> · Items must be unused with tags intact. Window: <?= (int) setting('return_window_days') ?> days from delivery.</p>
        <form class="card" method="post" action="/account/orders/<?= e($order['order_number']) ?>/return">
            <?= csrf_field() ?>
            <p class="card__title">1. Select item</p>
            <?php foreach ($items as $i => $it): ?>
                <label class="addr">
                    <input type="radio" name="order_item_id" value="<?= (int) $it['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> data-max-qty="<?= (int) $it['returnable_qty'] ?>">
                    <strong><?= e($it['product_name']) ?></strong>
                    <p>Size <?= e($it['size']) ?> · Returnable qty: <?= (int) $it['returnable_qty'] ?> · <?= e(money($it['unit_price'])) ?> each</p>
                </label>
            <?php endforeach; ?>

            <div class="grid-2 mt-2">
                <label class="field"><span>Quantity</span><input class="input" type="number" name="qty" value="1" min="1" max="10" required></label>
                <label class="field"><span>I want to</span>
                    <select class="select" name="type" data-return-type>
                        <option value="return">Return for refund</option>
                        <option value="exchange">Exchange for another size</option>
                    </select>
                </label>
            </div>
            <label class="field" data-exchange-size hidden><span>New size</span>
                <select class="select" name="exchange_size"><option value="">Select size</option><?php foreach (config('app.sizes') as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
            </label>
            <label class="field"><span>Reason</span>
                <select class="select" name="reason" required>
                    <option value="">Select a reason</option>
                    <?php foreach ($reasons as $k => $v): ?><option value="<?= e($k) ?>" <?= old('reason') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Details (optional)</span><textarea class="textarea" name="details" maxlength="1000"><?= e(old('details')) ?></textarea></label>
            <?php if ($order['payment_method'] === 'cod'): ?>
                <label class="field"><span>UPI ID for refund (COD order)</span><input class="input" name="refund_upi" placeholder="name@upi" value="<?= e(old('refund_upi')) ?>"></label>
            <?php else: ?>
                <p class="muted">Refund will be credited to your original payment method within 5–7 business days after we receive the item.</p>
            <?php endif; ?>
            <button class="btn btn--lg" type="submit">Submit request</button>
        </form>
    </div>
</div>
