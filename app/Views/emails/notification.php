<?php
/** @var string $heading  plain text @var array $lines  trusted HTML built with e() @var ?array $order @var ?array $items @var ?array $cta [label, url] */
?>
<h1 style="font-size:22px;margin:0 0 16px"><?= e($heading) ?></h1>
<?php foreach ($lines as $line): ?>
    <p style="font-size:15px;line-height:1.6;margin:0 0 12px"><?= $line ?></p>
<?php endforeach; ?>

<?php if (!empty($items)): ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-top:1px solid #eaeaec">
        <?php foreach ($items as $it): ?>
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #eaeaec;font-size:14px">
                    <strong><?= e($it['product_name']) ?></strong><br>
                    <span style="color:#7e818c">Size <?= e($it['size']) ?> · Qty <?= (int) $it['qty'] ?><?= $it['custom_name'] || $it['custom_number'] ? ' · ' . e(trim($it['custom_name'] . ' ' . $it['custom_number'])) : '' ?></span>
                </td>
                <td align="right" style="padding:10px 0;border-bottom:1px solid #eaeaec;font-size:14px;white-space:nowrap"><?= e(money($it['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!empty($order)): ?>
            <tr><td style="padding:12px 0 0;font-weight:bold">Total</td><td align="right" style="padding:12px 0 0;font-weight:bold"><?= e(money($order['grand_total'], true)) ?></td></tr>
        <?php endif; ?>
    </table>
<?php endif; ?>

<?php if (!empty($cta)): ?>
    <p style="margin:24px 0 0"><a href="<?= e($cta[1]) ?>" style="display:inline-block;background:#e11d48;color:#fff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:6px"><?= e($cta[0]) ?></a></p>
<?php endif; ?>
