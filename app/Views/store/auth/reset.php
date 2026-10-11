<?php /** @var string $token */ ?>
<div class="auth">
    <div class="auth__card">
        <h1>Set a new password</h1>
        <p class="lead">Choose a strong password you don't use elsewhere.</p>
        <form method="post" action="/reset-password" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?= partial('store/partials/field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password" minlength="8" required']) ?>
            <?= partial('store/partials/field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password" required']) ?>
            <button class="btn btn--block btn--lg" type="submit">Update password</button>
        </form>
    </div>
</div>
