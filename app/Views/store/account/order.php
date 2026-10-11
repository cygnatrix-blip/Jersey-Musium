<?php
use App\Services\OrderService;

/** @var array $order @var array $items @var array $history @var array $shipments @var array $returns @var array $refunds
 *  @var bool $canCancel @var bool $canReturn @var array $address */
$flow = ['confirmed' => 'Confirmed', 'packed' => 'Packed', 'shipped' => 'Shipped', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered'];
$reached = array_search($order['status'], array_keys($flow), true);
if (in_array($order['status'], ['return_requested', 'returned'], true)) {
    $reached = 4;
}
?>
<div class="container account">
    <?= partial('store/account/nav') ?>
    <div>
        <div class="flex between center wrap gap">
            <div>
                <h1 style="font-size:22px;margin:0">Order <?= e($order['order_number']) ?></h1>
                <p class="muted mb-0">Placed on <?= e(format_date($order['created_at'], 'd M Y, h:i A')) ?></p>
            </div>
            <span class="badge badge--<?= OrderService::STATUS_BADGE[$order['status']] ?? 'muted' ?>" style="font-size:12px"><?= e(label($order['status'])) ?></span>
        </div>

        <?php if (in_array($order['status'], ['pending_payment', 'payment_failed'], true)): ?>
            <div class="card mt-2" style="border-color:var(--warning)">
                <strong>Payment pending.</strong> Complete payment to confirm your order – it will be cancelled automatically if unpaid.
                <a class="btn btn--sm mt-1" href="/checkout/pay/<?= e($order['order_number']) ?>">Pay now</a>
            </div>
        <?php endif; ?>

        <?php if ($reached !== false): ?>
            <ol class="tracker" aria-label="Order progress">
                <?php $i = 0; foreach ($flow as $key => $lbl): ?>
                    <li class="<?= $i <= $reached ? 'is-done' : '' ?>"><?= e($lbl) ?></li>
                <?php $i++; endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="card mt-2">
            <p class="card__title">Items</p>
            <?php foreach ($items as $it): ?>
                <div class="line" style="grid-template-columns:72px 1fr">
                    <a href="/p/<?= e($it['product_slug']) ?>"><img src="<?= e(upload_url($it['image'])) ?>" alt="" width="72" height="90" style="width:72px" loading="lazy"></a>
                    <div>
                        <p class="line__title"><?= e($it['product_name']) ?></p>
                        <p class="line__sub mb-0">Size <?= e($it['size']) ?> · Qty <?= (int) $it['qty'] ?><?= $it['custom_name'] || $it['custom_number'] ? ' · Print: ' . e(trim($it['custom_name'] . ' ' . $it['custom_number'])) : '' ?></p>
                        <strong><?= e(money($it['line_total'])) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="flex gap wrap mt-2">
                <?php if ($canReturn): ?><a class="btn btn--outline btn--sm" href="/account/orders/<?= e($order['order_number']) ?>/return"><?= icon('return', 14) ?> Return / Exchange</a><?php endif; ?>
                <?php if (\App\Services\InvoiceService::eligible($order)): ?>
                    <a class="btn btn--outline btn--sm" href="/account/orders/<?= e($order['order_number']) ?>/invoice" target="_blank" rel="noopener">Download GST invoice</a>
                <?php elseif (!in_array($order['status'], ['pending_payment', 'payment_failed', 'cancelled'], true)): ?>
                    <span class="muted" style="font-size:12px;align-self:center">GST invoice will be available once your order ships.</span>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                    <form method="post" action="/account/orders/<?= e($order['order_number']) ?>/cancel" data-confirm="Cancel this order?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reason" value="Changed my mind">
                        <button class="btn btn--ghost btn--sm" type="submit" style="color:var(--danger)">Cancel order</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($shipments as $s): ?>
            <div class="card">
                <div class="flex between center wrap gap">
                    <p class="card__title mb-0"><?= icon('truck', 16) ?> <?= e($s['courier']) ?> · AWB <?= e($s['awb_number']) ?></p>
                    <?php if ($s['tracking_url']): ?><a class="btn btn--sm btn--outline" href="<?= e($s['tracking_url']) ?>" target="_blank" rel="noopener nofollow">Track on courier site</a><?php endif; ?>
                </div>
                <ul class="timeline mt-2">
                    <?php foreach ($s['events'] as $ev): ?>
                        <li><strong><?= e(label($ev['status'])) ?></strong><?= $ev['location'] ? ' – ' . e($ev['location']) : '' ?><?= $ev['note'] ? '<br>' . e($ev['note']) : '' ?><time><?= e(format_date($ev['event_at'], 'd M, h:i A')) ?></time></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <?php if ($returns): ?>
            <div class="card">
                <p class="card__title">Returns</p>
                <?php foreach ($returns as $r): ?>
                    <p><strong><?= e($r['rma_number']) ?></strong> · <?= e($r['product_name']) ?> (<?= e($r['size']) ?>) × <?= (int) $r['qty'] ?> · <?= e(ucfirst($r['type'])) ?>
                        <span class="badge"><?= e(label($r['status'])) ?></span><?= $r['admin_note'] ? '<br><span class="muted">' . e($r['admin_note']) . '</span>' : '' ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid-2" style="gap:16px;align-items:start">
            <div class="card" style="margin-top:16px">
                <p class="card__title">Delivery address</p>
                <p class="mb-0"><strong><?= e($address['name'] ?? '') ?></strong><br>
                    <?= e(implode(', ', array_filter([$address['line1'] ?? '', $address['line2'] ?? '', $address['landmark'] ?? '']))) ?><br>
                    <?= e(($address['city'] ?? '') . ', ' . ($address['state'] ?? '') . ' – ' . ($address['pincode'] ?? '')) ?><br>
                    Phone: <?= e($address['phone'] ?? '') ?></p>
            </div>
            <div class="card pricecard" style="margin-top:16px">
                <p class="card__title">Payment</p>
                <dl>
                    <dt>Items total</dt><dd><?= e(money($order['subtotal'], true)) ?></dd>
                    <?php if ((float) $order['discount'] > 0): ?><dt>Coupon (<?= e($order['coupon_code']) ?>)</dt><dd class="save">-<?= e(money($order['discount'], true)) ?></dd><?php endif; ?>
                    <dt>Delivery</dt><dd><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'], true)) : 'FREE' ?></dd>
                    <?php if ((float) $order['cod_fee'] > 0): ?><dt>COD fee</dt><dd><?= e(money($order['cod_fee'], true)) ?></dd><?php endif; ?>
                    <dt class="total">Total</dt><dd class="total"><?= e(money($order['grand_total'], true)) ?></dd>
                    <?php if ((float) $order['refunded_total'] > 0): ?><dt>Refunded</dt><dd class="save"><?= e(money($order['refunded_total'], true)) ?></dd><?php endif; ?>
                </dl>
                <p class="muted mb-0"><?= e(strtoupper($order['payment_method'])) ?> · <?= e(label($order['payment_status'])) ?></p>
                <?php foreach ($refunds as $rf): ?>
                    <p class="muted mb-0" style="font-size:12px">Refund <?= e(money($rf['amount'], true)) ?> – <?= e(label($rf['status'])) ?></p>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <p class="card__title">Order history</p>
            <ul class="timeline">
                <?php foreach (array_reverse($history) as $h): ?>
                    <li><strong><?= e(label($h['to_status'])) ?></strong><?= $h['note'] ? ' – ' . e($h['note']) : '' ?><time><?= e(format_date($h['created_at'], 'd M Y, h:i A')) ?></time></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
