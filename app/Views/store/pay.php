<?php /** @var array $order @var ?array $checkout @var ?string $error */ ?>
<div class="container">
    <div class="auth" style="background:none;min-height:60vh">
        <div class="auth__card text-center" id="pay-box"
             <?php if ($checkout): ?>data-checkout-config="<?= e(json_encode($checkout, JSON_UNESCAPED_SLASHES)) ?>"<?php endif; ?>
             data-order="<?= e($order['order_number']) ?>">
            <?= icon('lock', 40, 'muted') ?>
            <h1 style="font-size:28px;margin-top:.75rem">Complete your payment</h1>
            <p class="lead">Order <strong><?= e($order['order_number']) ?></strong> · <strong><?= e(money($order['grand_total'], true)) ?></strong></p>
            <?php if ($error): ?><p class="text-danger" style="font-weight:600" data-pay-error><?= e($error) ?></p><?php else: ?><p class="text-danger" style="font-weight:600" data-pay-error hidden></p><?php endif; ?>
            <p class="muted" data-pay-status>Opening secure payment window…</p>
            <?php if ($checkout): ?>
                <button class="btn btn--block btn--lg" type="button" data-pay-now><?= icon('lock', 18) ?> Pay <?= e(money($order['grand_total'], true)) ?></button>
            <?php else: ?>
                <a class="btn btn--block btn--lg" href="/checkout/pay/<?= e($order['order_number']) ?>">Try again</a>
            <?php endif; ?>
            <p class="secure">Your order is reserved for <?= (int) setting('pending_payment_expiry_minutes') ?> minutes. Payments are processed by <?= e(ucfirst($order['payment_method'])) ?> – we never see your card details.</p>
            <a class="btn btn--ghost mt-1" href="/account/orders/<?= e($order['order_number']) ?>">View order</a>
        </div>
    </div>
</div>
<?php if ($checkout): ?>
    <script src="<?= e($checkout['script']) ?>" defer nonce="<?= e(csp_nonce()) ?>"></script>
    <script src="<?= e(asset('js/pay.js')) ?>" defer></script>
<?php endif; ?>
