<?php /** @var App\Core\Paginator $orders */ ?>
<div class="container account">
    <?= partial('store/account/nav') ?>
    <div>
        <h1 style="font-size:22px">Orders &amp; returns</h1>
        <?php if (!$orders->items): ?>
            <div class="empty"><?= icon('package', 64) ?><h2>No orders yet</h2><p>When you place an order it will show up here.</p><a class="btn" href="/shop">Start shopping</a></div>
        <?php endif; ?>
        <?php foreach ($orders->items as $o): ?><?= partial('store/account/order-row', ['o' => $o]) ?><?php endforeach; ?>
        <?= $orders->links() ?>
    </div>
</div>
