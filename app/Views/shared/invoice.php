<?php
use App\Services\InvoiceService;

/**
 * GST TAX INVOICE (B2C). All figures come from InvoiceService::build() – the view only formats.
 * @var array $order @var array $items @var array $address @var array $inv
 */
$m = fn (int $p) => money($p / 100, true);
$t = $inv['totals'];
$intra = $inv['intra'];
$rate = fn (float $r) => rtrim(rtrim(number_format($r, 2), '0'), '.') . '%';
$sellerState = (string) setting('store_state');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice <?= e($order['invoice_number']) ?></title>
    <meta name="robots" content="noindex">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1c2230; margin: 0; padding: 24px; font-size: 12px; background: #f4f5f8; }
        .inv { max-width: 900px; margin: 0 auto; background: #fff; padding: 28px; border: 1px solid #e3e4e8; }
        .head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 3px solid #0b1020; padding-bottom: 14px; margin-bottom: 14px; }
        .brand { font-size: 24px; font-weight: 800; font-style: italic; }
        .brand span { color: #e11d48; }
        h1 { font-size: 18px; margin: 0 0 6px; letter-spacing: .06em; }
        .meta td { padding: 1px 0 1px 12px; }
        .grid { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 8px; }
        .grid > div { flex: 1 1 260px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #7e818c; font-weight: 700; margin-bottom: 2px; }
        .scroll { overflow-x: auto; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; min-width: 640px; }
        .items th, .items td { border: 1px solid #d4d5d9; padding: 6px; text-align: left; vertical-align: top; }
        .items th { background: #f5f5f6; font-size: 10.5px; text-transform: uppercase; }
        .r { text-align: right; white-space: nowrap; }
        .muted { color: #7e818c; }
        .tot td { font-weight: 700; background: #fafafa; }
        .grand td { font-size: 14px; font-weight: 800; background: #0b1020; color: #fff; }
        .words { margin-top: 10px; padding: 8px 10px; background: #f5f5f6; border-left: 3px solid #e11d48; }
        .foot { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; margin-top: 22px; }
        .sign { text-align: right; min-width: 220px; }
        .sign .line { margin-top: 42px; border-top: 1px solid #1c2230; padding-top: 4px; }
        .stamp { display: inline-block; padding: 3px 8px; border: 2px solid #c62828; color: #c62828; font-weight: 800; transform: rotate(-4deg); margin-top: 8px; }
        .print { margin: 0 auto 12px; max-width: 900px; display: flex; gap: 8px; }
        .print button { padding: 8px 14px; border: 0; border-radius: 6px; background: #e11d48; color: #fff; font-weight: 700; cursor: pointer; }
        .msum-hint { display: none; }
        .msum { display: none; margin-top: 12px; border: 1px solid #d4d5d9; }
        .msum div { display: flex; justify-content: space-between; padding: 6px 10px; border-bottom: 1px solid #eee; }
        .msum div:last-child { background: #0b1020; color: #fff; font-weight: 800; border: 0; }
        @media (max-width: 700px) { body { padding: 8px; } .inv { padding: 14px; } .sign { text-align: left; } .msum, .msum-hint { display: block; } }
        @media print { body { padding: 0; background: #fff; } .inv { border: 0; padding: 0; } .print { display: none; } @page { size: A4; margin: 12mm; } }
    </style>
</head>
<body>
<div class="print">
    <button id="print-btn" type="button">Print / Save as PDF</button>
</div>
<script nonce="<?= e(csp_nonce()) ?>">document.getElementById('print-btn').addEventListener('click', function () { window.print(); });</script>

<div class="inv">
    <div class="head">
        <div>
            <div class="brand">JERSEY <span>MUSEUM</span></div>
            <div><?= e(setting('store_name')) ?><br><?= nl2br(e(setting('store_address'))) ?><br><?= e(setting('store_email')) ?> · <?= e(setting('store_phone')) ?></div>
            <div style="margin-top:4px"><strong>GSTIN:</strong> <?= e(setting('store_gstin') ?: 'Not configured') ?> &nbsp; <strong>State:</strong> <?= e($sellerState) ?> (<?= e(InvoiceService::STATE_CODES[$sellerState] ?? '—') ?>)</div>
        </div>
        <div>
            <h1>TAX INVOICE</h1>
            <table class="meta">
                <tr><td class="muted">Invoice No.</td><td><strong><?= e($order['invoice_number']) ?></strong></td></tr>
                <tr><td class="muted">Invoice date</td><td><?= e(format_date($order['invoice_date'] ?: $order['placed_at'] ?: $order['created_at'])) ?></td></tr>
                <tr><td class="muted">Order No.</td><td><?= e($order['order_number']) ?></td></tr>
                <tr><td class="muted">Order date</td><td><?= e(format_date($order['created_at'])) ?></td></tr>
                <tr><td class="muted">Payment</td><td><?= e(strtoupper($order['payment_method'])) ?> · <?= e(label($order['payment_status'])) ?></td></tr>
            </table>
        </div>
    </div>

    <div class="grid">
        <div>
            <div class="label">Billed &amp; shipped to</div>
            <strong><?= e($address['name'] ?? '') ?></strong><br>
            <?= e(implode(', ', array_filter([$address['line1'] ?? '', $address['line2'] ?? '', $address['landmark'] ?? '']))) ?><br>
            <?= e(($address['city'] ?? '') . ', ' . ($address['state'] ?? '') . ' – ' . ($address['pincode'] ?? '')) ?><br>
            Phone: <?= e($address['phone'] ?? '') ?> · <?= e($order['customer_email']) ?>
        </div>
        <div>
            <div class="label">Place of supply</div>
            <?= e($address['state'] ?? '') ?> (State code <?= e($inv['state_code']) ?>)<br>
            <span class="muted"><?= $intra ? 'Intra-state supply – CGST + SGST' : 'Inter-state supply – IGST' ?></span><br>
            <span class="muted">Tax payable on reverse charge: No</span>
        </div>
    </div>

    <div class="scroll">
    <table class="items">
        <thead>
        <tr>
            <th>#</th><th>Description</th><th>HSN/SAC</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Discount</th>
            <th class="r">Taxable value</th><th class="r">GST rate</th>
            <?php if ($intra): ?><th class="r">CGST</th><th class="r">SGST</th><?php else: ?><th class="r">IGST</th><?php endif; ?>
            <th class="r">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php $i = 0; foreach ($inv['lines'] as $l): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= e($l['description']) ?><br><span class="muted">Size <?= e($l['size']) ?> · SKU <?= e($l['sku']) ?><?= $l['custom'] !== '' ? ' · Print: ' . e($l['custom']) : '' ?></span></td>
                <td><?= e($l['hsn']) ?></td>
                <td class="r"><?= (int) $l['qty'] ?></td>
                <td class="r"><?= e($m($l['unit_price'])) ?></td>
                <td class="r"><?= $l['discount'] ? '-' . e($m($l['discount'])) : '—' ?></td>
                <td class="r"><?= e($m($l['taxable'])) ?></td>
                <td class="r"><?= e($rate($l['rate'])) ?></td>
                <?php if ($intra): ?><td class="r"><?= e($m($l['cgst'])) ?></td><td class="r"><?= e($m($l['sgst'])) ?></td><?php else: ?><td class="r"><?= e($m($l['igst'])) ?></td><?php endif; ?>
                <td class="r"><?= e($m($l['value'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($inv['charges'] as $l): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= e($l['description']) ?></td>
                <td><?= e($l['hsn']) ?></td>
                <td class="r">1</td><td class="r"><?= e($m($l['value'])) ?></td><td class="r">—</td>
                <td class="r"><?= e($m($l['taxable'])) ?></td>
                <td class="r"><?= e($rate($l['rate'])) ?></td>
                <?php if ($intra): ?><td class="r"><?= e($m($l['cgst'])) ?></td><td class="r"><?= e($m($l['sgst'])) ?></td><?php else: ?><td class="r"><?= e($m($l['igst'])) ?></td><?php endif; ?>
                <td class="r"><?= e($m($l['value'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr class="tot">
            <td colspan="6" class="r">Total</td>
            <td class="r"><?= e($m($t['taxable'])) ?></td><td></td>
            <?php if ($intra): ?><td class="r"><?= e($m($t['cgst'])) ?></td><td class="r"><?= e($m($t['sgst'])) ?></td><?php else: ?><td class="r"><?= e($m($t['igst'])) ?></td><?php endif; ?>
            <td class="r"><?= e($m($t['value'])) ?></td>
        </tr>
        <?php if ($t['round_off'] !== 0): ?>
            <tr><td colspan="<?= $intra ? 10 : 9 ?>" class="r">Round off</td><td class="r"><?= e($m($t['round_off'])) ?></td></tr>
        <?php endif; ?>
        <tr class="grand"><td colspan="<?= $intra ? 10 : 9 ?>" class="r">Invoice total (GST inclusive)</td><td class="r"><?= e($m($t['grand'])) ?></td></tr>
        </tfoot>
    </table>
    </div>

    <div class="msum" aria-hidden="true">
        <div><span>Taxable value</span><span><?= e($m($t['taxable'])) ?></span></div>
        <div><span><?= $intra ? 'CGST + SGST' : 'IGST' ?></span><span><?= e($m($t['tax'])) ?></span></div>
        <div><span>Invoice total</span><span><?= e($m($t['grand'])) ?></span></div>
    </div>
    <p class="muted msum-hint" style="margin:6px 0 0">Swipe the table sideways to see every column.</p>
    <div class="words"><strong>Amount in words:</strong> <?= e(InvoiceService::amountInWords($t['grand'])) ?></div>
    <?php if ($order['coupon_code']): ?>
        <p class="muted">Coupon <?= e($order['coupon_code']) ?> applied: <?= e(money($order['discount'], true)) ?> (shown as line discounts above).</p>
    <?php endif; ?>
    <?php if ((float) $order['refunded_total'] > 0): ?>
        <p class="muted">Refunds of <?= e(money($order['refunded_total'], true)) ?> have been issued against this invoice (see order history).</p>
    <?php endif; ?>

    <div class="foot">
        <div class="muted" style="max-width:480px">
            Goods once sold can be returned within <?= (int) setting('return_window_days') ?> days as per our return policy.
            This is a computer-generated invoice. Subject to <?= e($sellerState) ?> jurisdiction.
            <?php if ($order['status'] === 'cancelled'): ?><br><span class="stamp">CANCELLED</span><?php endif; ?>
        </div>
        <div class="sign">
            For <?= e(setting('store_name')) ?>
            <div class="line">Authorised signatory</div>
        </div>
    </div>
</div>
</body>
</html>
