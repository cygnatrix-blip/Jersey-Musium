<?php /** @var ?string $problem @var ?string $newKey @var string $token */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Store setup · Jersey Musium</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm-auth">
<div class="card" style="max-width:460px">
    <div class="flex center gap mb-2">
        <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" width="40" height="40">
        <div style="font:800 italic 22px/1 var(--display);text-transform:uppercase">Jersey <span style="color:var(--brand)">Musium</span></div>
    </div>
    <h1 style="font-size:18px">One-time setup: create the Super Admin</h1>
    <p class="muted">This page works only once, while the store has no admin account.</p>

    <?php foreach (['success', 'error', 'info'] as $type): if ($msg = flash($type)): ?>
        <div class="flash flash--<?= $type ?>"><?= e($msg) ?></div>
    <?php endif; endforeach; ?>

    <?php if ($problem): ?>
        <div class="flash flash--error"><?= e($problem) ?></div>
        <?php if ($newKey): ?>
            <label class="field"><span>Your new APP_KEY (copy everything)</span>
                <input class="input" readonly value="<?= e($newKey) ?>" style="font-family:ui-monospace,monospace;font-size:12px">
            </label>
            <p class="muted" style="font-size:12px">Paste it as <code>APP_KEY=<?= e($newKey) ?></code>. Keep it secret and never change it later: it protects sign-in codes.</p>
        <?php endif; ?>
        <a class="btn btn--outline btn--block" href="/setup?token=<?= e(rawurlencode($token)) ?>">Check again</a>
    <?php else: ?>
        <form method="post" action="/setup" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?= partial('admin/partials/f', ['name' => 'name', 'label' => 'Your name', 'attrs' => 'required']) ?>
            <?= partial('admin/partials/f', ['name' => 'email', 'label' => 'Email (sign-in codes are sent here)', 'type' => 'email', 'attrs' => 'required']) ?>
            <?= partial('admin/partials/f', ['name' => 'password', 'label' => 'Password (min 12, letters + numbers)', 'type' => 'password', 'attrs' => 'required minlength="12" autocomplete="new-password"']) ?>
            <?= partial('admin/partials/f', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'attrs' => 'required autocomplete="new-password"']) ?>
            <button class="btn btn--block" type="submit">Create Super Admin</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
