<?php /** @var string $email masked @var int $cooldown seconds before resend allowed */ ?>
<div class="auth">
    <div class="auth__card">
        <div style="width:56px;height:56px;border-radius:50%;background:var(--brand-50);color:var(--brand);display:grid;place-items:center;margin-bottom:1rem"><?= icon('lock', 28) ?></div>
        <h1>Verify your email</h1>
        <p class="lead">We sent a 6-digit code to <strong><?= e($email) ?></strong>. Enter it below to activate your account. The code is valid for 10 minutes.</p>
        <form method="post" action="/verify-email" novalidate>
            <?= csrf_field() ?>
            <label class="field <?= error_for('code') ? 'has-error' : '' ?>">
                <span>Verification code</span>
                <input class="input otp-input" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6"
                       placeholder="••••••" required autofocus aria-describedby="otp-help">
            </label>
            <button class="btn btn--block btn--lg" type="submit">Verify email</button>
        </form>
        <form method="post" action="/verify-email/resend" class="text-center mt-2">
            <?= csrf_field() ?>
            <p class="muted mb-0" id="otp-help" style="font-size:13px">Didn't get it? Check spam, or
                <button class="btn btn--ghost" type="submit" data-countdown="<?= (int) $cooldown ?>" style="padding:0;min-height:0">resend code</button>
            </p>
        </form>
        <p class="auth__alt">Wrong email? <a href="/account">Contact support from your account</a> or
            <a href="#" data-logout>sign out</a>.</p>
        <form method="post" action="/logout" id="logout-form" hidden><?= csrf_field() ?></form>
    </div>
</div>
