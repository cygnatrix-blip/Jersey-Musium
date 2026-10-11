<?php
use App\Services\OrderService;

/** @var array $o */
?>
<a class="order-card" href="/account/orders/<?= e($o['order_number']) ?>">
    <img src="<?= e(upload_url($o['image'] ?? null)) ?>" alt="" width="72" height="90" loading="lazy">
    <div>
        <h3><span class="badge badge--<?= OrderService::STATUS_BADGE[$o['status']] ?? 'muted' ?>"><?= e(label($o['status'])) ?></span></h3>
        <p><strong><?= e($o['order_number']) ?></strong> · <?= e(format_date($o['created_at'])) ?><?= isset($o['item_count']) ? ' · ' . (int) $o['item_count'] . ' item(s)' : '' ?></p>
    </div>
    <strong><?= e(money($o['grand_total'])) ?></strong>
</a>
