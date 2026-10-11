<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin sign in · <?= e(setting('store_name')) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@1,800&family=Inter:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm-auth">
<div class="card">
    <div class="flex center gap mb-2">
        <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" width="40" height="40">
        <div style="font:800 italic 24px/1 var(--display);text-transform:uppercase">Jersey <span style="color:var(--brand)">Musium</span></div>
    </div>
    <?php $otpStep = ($step ?? 'password') === 'otp'; ?>
    <h1 style="font-size:18px"><?= $otpStep ? 'Two-step verification' : 'Admin console' ?></h1>
    <p class="muted"><?= $otpStep ? 'Enter the 6-digit code we emailed you.' : 'Sign in to manage the store.' ?></p>
    <?php foreach (['success', 'error', 'info'] as $type): if ($msg = flash($type)): ?>
        <div class="flash flash--<?= $type ?>"><?= e($msg) ?></div>
    <?php endif; endforeach; ?>
    <?php if ($otpStep): ?>
        <form method="post" action="/admin/login/verify">
            <?= csrf_field() ?>
            <label class="field"><span>Verification code</span>
                <input class="input" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus
                       style="font-size:22px;letter-spacing:8px;text-align:center">
            </label>
            <button class="btn btn--block" type="submit">Verify &amp; sign in</button>
        </form>
        <form method="post" action="/admin/login/resend" class="mt-1" style="text-align:center">
            <?= csrf_field() ?><button class="btn btn--outline btn--sm" type="submit">Resend code</button>
            <a class="btn btn--outline btn--sm" href="/admin/login">Start over</a>
        </form>
    <?php else: ?>
        <form method="post" action="/admin/login">
            <?= csrf_field() ?>
            <?= partial('admin/partials/f', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'attrs' => 'required autofocus autocomplete="username"']) ?>
            <?= partial('admin/partials/f', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'attrs' => 'required autocomplete="current-password"']) ?>
            <button class="btn btn--block" type="submit">Sign in</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
