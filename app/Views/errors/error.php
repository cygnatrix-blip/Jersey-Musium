<?php /** @var int $status @var string $message @var ?string $debug */ ?>
<?php if (str_starts_with(request()->path(), '/admin')): ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= (int) $status ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head><body class="adm-auth">
<?php endif; ?>
<div class="container">
    <div class="empty" style="padding:72px 16px">
        <p style="font:800 italic 96px/1 var(--display, sans-serif);color:var(--brand, #e11d48);margin:0"><?= (int) $status ?></p>
        <h1 style="font-size:24px"><?= $status === 404 ? 'Offside! This page doesn\'t exist.' : ($status === 403 ? 'Access denied' : 'Something went wrong') ?></h1>
        <p><?= e($message) ?></p>
        <a class="btn" href="<?= str_starts_with(request()->path(), '/admin') ? '/admin' : '/' ?>">Back to <?= str_starts_with(request()->path(), '/admin') ? 'dashboard' : 'home' ?></a>
        <?php if ($debug): ?><pre style="text-align:left;white-space:pre-wrap;font-size:12px;background:#111;color:#eee;padding:1rem;border-radius:8px;margin-top:2rem;overflow:auto"><?= e($debug) ?></pre><?php endif; ?>
    </div>
</div>
<?php if (str_starts_with(request()->path(), '/admin')): ?></body></html><?php endif; ?>
