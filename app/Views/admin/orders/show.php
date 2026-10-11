<?php
use App\Services\ShipmentService;

/** @var array $order @var array $items @var array $history @var array $shipments @var array $payments @var array $refunds
 *  @var array $returns @var ?array $customer @var array $address @var array $next @var int $refundable */
$canShip = in_array($order['status'], ['confirmed', 'packed'], true);
$manualNext = array_values(array_diff($next, ['shipped', 'cancelled', 'return_requested', 'returned', 'confirmed', 'payment_failed']));
$canCancel = in_array('cancelled', $next, true);
?>
<div class="page-head">
    <div>
        <h1>Order <?= e($order['order_number']) ?> <?= partial('admin/partials/status', ['s' => $order['status']]) ?></h1>
        <p>Placed <?= e(format_date($order['created_at'], 'd M Y, h:i A')) ?> · <?= e(strtoupper($order['payment_method'])) ?> · Payment <?= e(label($order['payment_status'])) ?></p>
    </div>
    <div class="flex gap wrap">
        <?php if (\App\Services\InvoiceService::eligible($order)): ?>
            <a class="btn btn--outline" href="/admin/orders/<?= (int) $order['id'] ?>/invoice" target="_blank" rel="noopener">GST invoice <?= e($order['invoice_number']) ?></a>
        <?php else: ?>
            <span class="badge" title="Invoice number is allocated when the shipment is created">Invoice issued on dispatch</span>
        <?php endif; ?>
        <a class="btn btn--outline" href="/admin/orders">Back</a>
    </div>
</div>

<div class="cols cols--2-1">
    <div>
        <section class="card">
            <h2>Items</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th></th><th>Product</th><th>Size</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(upload_url($it['image'])) ?>" alt="" width="40" height="50"></td>
                        <td><?= e($it['product_name']) ?><br><span class="muted"><?= e($it['sku']) ?></span>
                            <?php if ($it['custom_name'] || $it['custom_number']): ?><br><span class="badge badge--warning">PRINT: <?= e(trim($it['custom_name'] . ' #' . $it['custom_number'], ' #')) ?></span><?php endif; ?></td>
                        <td><?= e($it['size']) ?></td>
                        <td class="num"><?= (int) $it['qty'] ?></td>
                        <td class="num"><?= e(money($it['unit_price'], true)) ?></td>
                        <td class="num"><?= e(money($it['line_total'], true)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <dl class="kv mt-2" style="grid-template-columns:1fr auto;max-width:320px;margin-left:auto">
                <dt>Subtotal</dt><dd class="num"><?= e(money($order['subtotal'], true)) ?></dd>
                <?php if ((float) $order['discount'] > 0): ?><dt>Coupon <?= e($order['coupon_code']) ?></dt><dd>-<?= e(money($order['discount'], true)) ?></dd><?php endif; ?>
                <dt>Shipping</dt><dd><?= e(money($order['shipping_fee'], true)) ?></dd>
                <?php if ((float) $order['cod_fee'] > 0): ?><dt>COD fee</dt><dd><?= e(money($order['cod_fee'], true)) ?></dd><?php endif; ?>
                <dt>GST included</dt><dd><?= e(money($order['tax_total'], true)) ?></dd>
                <dt><strong>Grand total</strong></dt><dd><strong><?= e(money($order['grand_total'], true)) ?></strong></dd>
                <?php if ((float) $order['refunded_total'] > 0): ?><dt>Refunded</dt><dd class="text-danger">-<?= e(money($order['refunded_total'], true)) ?></dd><?php endif; ?>
            </dl>
        </section>

        <section class="card">
            <h2>Delivery</h2>
            <?php if ($canShip): ?>
                <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/ship" class="mb-2">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <?= partial('admin/partials/f', ['name' => 'courier', 'label' => 'Courier', 'type' => 'select', 'value' => 'Shiprocket', 'options' => array_combine(ShipmentService::COURIERS, ShipmentService::COURIERS)]) ?>
                        <?= partial('admin/partials/f', ['name' => 'awb_number', 'label' => 'AWB / tracking number', 'attrs' => 'required']) ?>
                        <?= partial('admin/partials/f', ['name' => 'tracking_url', 'label' => 'Tracking URL (optional)', 'type' => 'url']) ?>
                        <?= partial('admin/partials/f', ['name' => 'weight_grams', 'label' => 'Weight (g)', 'type' => 'number', 'attrs' => 'min="1"']) ?>
                    </div>
                    <button class="btn" type="submit">Create shipment &amp; mark shipped</button>
                </form>
            <?php elseif (!$shipments): ?>
                <p class="muted">Shipments can be created once the order is confirmed.</p>
            <?php endif; ?>

            <?php foreach ($shipments as $s): ?>
                <div class="card" style="box-shadow:none;background:#fafbfc">
                    <div class="card__head">
                        <h2><?= e($s['courier']) ?> · <?= e($s['awb_number']) ?> <span class="badge badge--info"><?= e(label($s['status'])) ?></span></h2>
                        <?php if ($s['tracking_url']): ?><a class="link" href="<?= e($s['tracking_url']) ?>" target="_blank" rel="noopener">Track ↗</a><?php endif; ?>
                    </div>
                    <?php if (!in_array($s['status'], ['delivered', 'lost'], true)): ?>
                        <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/shipments/<?= (int) $s['id'] ?>/event" class="form-grid form-grid--3" style="align-items:end">
                            <?= csrf_field() ?>
                            <?= partial('admin/partials/f', ['name' => 'status', 'label' => 'New tracking status', 'type' => 'select', 'value' => '', 'options' => array_combine(array_slice(ShipmentService::STATUSES, 1), array_map('label', array_slice(ShipmentService::STATUSES, 1)))]) ?>
                            <?= partial('admin/partials/f', ['name' => 'location', 'label' => 'Location', 'attrs' => 'placeholder="Hub / city"']) ?>
                            <label class="field"><span>&nbsp;</span><button class="btn btn--dark btn--block" type="submit">Add update</button></label>
                        </form>
                    <?php endif; ?>
                    <ul class="timeline">
                        <?php foreach ($s['events'] as $ev): ?><li><strong><?= e(label($ev['status'])) ?></strong><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?><?= $ev['note'] ? ' – ' . e($ev['note']) : '' ?><time><?= e(format_date($ev['event_at'], 'd M Y, h:i A')) ?></time></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card">
            <h2>Payments &amp; refunds</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Gateway</th><th>Reference</th><th>Status</th><th>Method</th><th class="num">Amount</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr><td><?= e(ucfirst($p['gateway'])) ?></td><td class="muted"><?= e($p['gateway_payment_id'] ?: $p['gateway_order_id']) ?><?= $p['error_message'] ? '<br><span class="text-danger">' . e($p['error_message']) . '</span>' : '' ?></td>
                        <td><span class="badge badge--<?= $p['status'] === 'captured' ? 'success' : ($p['status'] === 'failed' ? 'danger' : 'muted') ?>"><?= e(label($p['status'])) ?></span></td>
                        <td><?= e($p['method']) ?></td><td class="num"><?= e(money($p['amount'], true)) ?></td><td><?= e(format_date($p['created_at'], 'd M, h:i A')) ?></td></tr>
                <?php endforeach; ?>
                <?php foreach ($refunds as $r): ?>
                    <tr><td>Refund (<?= e($r['mode']) ?>)</td><td class="muted"><?= e($r['refund_number']) ?><?= $r['gateway_refund_id'] ? '<br>' . e($r['gateway_refund_id']) : '' ?><?= $r['manual_reference'] ? '<br>UTR ' . e($r['manual_reference']) : '' ?><?= $r['error_message'] ? '<br><span class="text-danger">' . e($r['error_message']) . '</span>' : '' ?></td>
                        <td><span class="badge badge--<?= $r['status'] === 'processed' ? 'success' : ($r['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e(label($r['status'])) ?></span></td>
                        <td><?= e(str_limit($r['reason'], 30)) ?></td><td class="num">-<?= e(money($r['amount'], true)) ?></td><td><?= e(format_date($r['created_at'], 'd M, h:i A')) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if ($refundable > 0 && admin_auth()->can('refunds.manage')): ?>
                <details class="mt-2">
                    <summary class="btn btn--outline btn--sm">Issue refund (max <?= e(money($refundable / 100, true)) ?>)</summary>
                    <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/refund" class="mt-2">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <?= partial('admin/partials/f', ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number', 'value' => rupees($refundable), 'attrs' => 'step="0.01" min="1" max="' . rupees($refundable) . '" required']) ?>
                            <?= partial('admin/partials/f', ['name' => 'mode', 'label' => 'Refund via', 'type' => 'select', 'value' => $order['payment_method'] === 'cod' ? 'manual' : 'gateway',
                                'options' => $order['payment_method'] === 'cod' ? ['manual' => 'Manual bank/UPI transfer'] : ['gateway' => 'Original payment method (' . ucfirst($order['payment_method']) . ')', 'manual' => 'Manual bank/UPI transfer'], 'attrs' => 'data-refund-mode']) ?>
                        </div>
                        <?= partial('admin/partials/f', ['name' => 'reason', 'label' => 'Reason', 'attrs' => 'required maxlength="255"']) ?>
                        <div data-manual-ref <?= $order['payment_method'] === 'cod' ? '' : 'hidden' ?>><?= partial('admin/partials/f', ['name' => 'manual_reference', 'label' => 'UTR / transaction reference']) ?></div>
                        <button class="btn btn--danger" type="submit" data-confirm-click="Issue this refund?">Issue refund</button>
                    </form>
                </details>
            <?php endif; ?>
        </section>

        <?php if ($returns): ?>
            <section class="card">
                <h2>Returns</h2>
                <?php foreach ($returns as $r): ?>
                    <p><a class="link" href="/admin/returns/<?= (int) $r['id'] ?>"><?= e($r['rma_number']) ?></a> · <?= e($r['product_name']) ?> (<?= e($r['size']) ?>) × <?= (int) $r['qty'] ?> · <?= e($r['type']) ?> <span class="badge"><?= e(label($r['status'])) ?></span></p>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($manualNext || $canCancel): ?>
            <section class="card">
                <h2>Update status</h2>
                <?php if ($manualNext): ?>
                    <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/status">
                        <?= csrf_field() ?>
                        <?= partial('admin/partials/f', ['name' => 'status', 'label' => 'Move to', 'type' => 'select', 'value' => $manualNext[0], 'options' => array_combine($manualNext, array_map('label', $manualNext))]) ?>
                        <?= partial('admin/partials/f', ['name' => 'note', 'label' => 'Note (optional, internal)']) ?>
                        <button class="btn btn--block" type="submit">Update</button>
                    </form>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                    <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/cancel" class="mt-2" data-confirm="Cancel this order? Stock will be restored<?= $order['payment_status'] === 'paid' ? ' and a full refund issued' : '' ?>.">
                        <?= csrf_field() ?>
                        <?= partial('admin/partials/f', ['name' => 'reason', 'label' => 'Cancellation reason', 'attrs' => 'required placeholder="e.g. Out of stock / RTO / customer request"']) ?>
                        <button class="btn btn--danger btn--block" type="submit">Cancel order</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Customer</h2>
            <?php if ($customer): ?>
                <p class="mb-0"><a class="link" href="/admin/customers/<?= (int) $customer['id'] ?>"><?= e($customer['name']) ?></a><br><?= e($customer['email']) ?><br><?= e($customer['phone']) ?><br><span class="muted"><?= (int) $customer['orders'] ?> orders · since <?= e(format_date($customer['created_at'], 'M Y')) ?></span></p>
            <?php endif; ?>
            <h2 class="mt-2">Ship to</h2>
            <p class="mb-0"><?= e($address['name'] ?? '') ?> · <?= e($address['phone'] ?? '') ?><br><?= e(implode(', ', array_filter([$address['line1'] ?? '', $address['line2'] ?? '', $address['landmark'] ?? '']))) ?><br><?= e(($address['city'] ?? '') . ', ' . ($address['state'] ?? '') . ' – ' . ($address['pincode'] ?? '')) ?></p>
            <?php if ($order['notes']): ?><h2 class="mt-2">Customer note</h2><p class="mb-0"><?= e($order['notes']) ?></p><?php endif; ?>
        </section>

        <section class="card">
            <h2>Internal notes</h2>
            <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/note">
                <?= csrf_field() ?>
                <textarea class="textarea" name="admin_notes" aria-label="Internal notes"><?= e($order['admin_notes']) ?></textarea>
                <button class="btn btn--outline btn--sm mt-1" type="submit">Save note</button>
            </form>
        </section>

        <section class="card">
            <h2>Timeline</h2>
            <ul class="timeline">
                <?php foreach (array_reverse($history) as $h): ?>
                    <li><strong><?= e(label($h['to_status'])) ?></strong> <span class="muted">by <?= e($h['actor_type']) ?></span><?= $h['note'] ? '<br>' . e($h['note']) : '' ?><time><?= e(format_date($h['created_at'], 'd M Y, h:i A')) ?></time></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
