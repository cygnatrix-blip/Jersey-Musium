<?php /** @var string $content @var string $subject */ ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($subject) ?></title></head>
<body style="margin:0;background:#f5f5f6;font-family:Arial,Helvetica,sans-serif;color:#1c2230">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f6;padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">
            <tr><td style="background:#0b1020;padding:20px 28px;color:#fff;font-size:22px;font-weight:800;letter-spacing:.5px;font-style:italic">
                JERSEY <span style="color:#e11d48">MUSIUM</span>
            </td></tr>
            <tr><td style="padding:28px"><?= $content ?></td></tr>
            <tr><td style="padding:18px 28px;background:#fafafa;color:#7e818c;font-size:12px;line-height:1.6">
                <?= e(setting('store_name')) ?> · <?= e(setting('store_address')) ?><br>
                Need help? Write to <a href="mailto:<?= e(setting('store_email')) ?>" style="color:#e11d48"><?= e(setting('store_email')) ?></a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
