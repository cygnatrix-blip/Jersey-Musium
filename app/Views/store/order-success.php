<?php /** @var array $order @var array $items */
$paid = in_array($order['payment_status'], ['paid', 'cod_pending'], true);
?>
<div class="container">
    <div class="auth" style="background:none">
        <div class="auth__card text-center" style="max-width:560px">
            <?php if ($paid): ?>
                <div style="width:72px;height:72px;border-radius:50%;background:var(--green-50);color:var(--green);display:grid;place-items:center;margin:0 auto 1rem"><?= icon('check', 40) ?></div>
                <h1>Goal! Order confirmed</h1>
                <p class="lead">Thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?>. Your order <strong><?= e($order['order_number']) ?></strong> has been placed.
                    <?= $order['payment_method'] === 'cod' ? 'Please pay ' . e(money($order['grand_total'])) . ' on delivery.' : 'Payment received.' ?></p>
                <p class="muted">Confirmation sent to <?= e($order['customer_email']) ?>. Expected delivery in <?= (int) setting('delivery_days_min') ?>–<?= (int) setting('delivery_days_max') ?> days.</p>
            <?php else: ?>
                <h1>Payment processing</h1>
                <p class="lead">We are confirming your payment for order <strong><?= e($order['order_number']) ?></strong>. This usually takes a few seconds – we'll email you as soon as it's confirmed.</p>
            <?php endif; ?>
            <div class="flex gap wrap" style="justify-content:center;margin-top:1.25rem">
                <a class="btn btn--outline" href="/account/orders/<?= e($order['order_number']) ?>">View order</a>
                <a class="btn" href="/shop">Continue shopping</a>
            </div>
        </div>
    </div>
</div>
<?php if ($paid && setting('ga4_measurement_id')): ?>
<script nonce="<?= e(csp_nonce()) ?>">
if (window.gtag) gtag('event', 'purchase', <?= json_encode([
    'transaction_id' => $order['order_number'], 'value' => (float) $order['grand_total'], 'currency' => 'INR',
    'shipping' => (float) $order['shipping_fee'], 'coupon' => $order['coupon_code'],
    'items' => array_map(fn ($i) => ['item_id' => $i['sku'], 'item_name' => $i['product_name'], 'item_variant' => $i['size'], 'price' => (float) $i['unit_price'], 'quantity' => (int) $i['qty']], $items),
], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>);
</script>
<?php endif; ?>
