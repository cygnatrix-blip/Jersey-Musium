<?php /** @var ?array $order @var ?string $error @var array $history @var array $shipments */ ?>
<div class="container" style="max-width:720px;padding-top:24px;padding-bottom:48px">
    <h1 style="font:800 italic 36px/1 var(--display);text-transform:uppercase">Track your order</h1>
    <form class="card" method="post" action="/track-order">
        <?= csrf_field() ?>
        <div class="grid-2">
            <label class="field"><span>Order number</span><input class="input" name="order_number" required placeholder="JM261011XXXXXX" value="<?= e(request()->input('order_number', '')) ?>"></label>
            <label class="field"><span>Email used for the order</span><input class="input" type="email" name="email" required value="<?= e(request()->input('email', '')) ?>"></label>
        </div>
        <button class="btn" type="submit">Track</button>
        <?php if ($error): ?><p class="text-danger mt-1 mb-0"><?= e($error) ?></p><?php endif; ?>
    </form>

    <?php if ($order): ?>
        <div class="card">
            <div class="flex between center"><strong><?= e($order['order_number']) ?></strong><span class="badge badge--info"><?= e(label($order['status'])) ?></span></div>
            <?php foreach ($shipments as $s): ?>
                <p class="mt-2 mb-0"><?= icon('truck', 16) ?> <?= e($s['courier']) ?> · AWB <?= e($s['awb_number']) ?>
                    <?php if ($s['tracking_url']): ?> · <a href="<?= e($s['tracking_url']) ?>" target="_blank" rel="noopener nofollow" style="color:var(--brand)">Courier tracking</a><?php endif; ?></p>
                <ul class="timeline mt-1">
                    <?php foreach ($s['events'] as $ev): ?><li><strong><?= e(label($ev['status'])) ?></strong><?= $ev['location'] ? ' – ' . e($ev['location']) : '' ?><time><?= e(format_date($ev['event_at'], 'd M, h:i A')) ?></time></li><?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <ul class="timeline mt-2">
                <?php foreach (array_reverse($history) as $h): ?><li><strong><?= e(label($h['to_status'])) ?></strong><time><?= e(format_date($h['created_at'], 'd M Y, h:i A')) ?></time></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
