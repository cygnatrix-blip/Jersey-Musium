<?php /** @var array $user @var array $orders */ ?>
<div class="container account">
    <?= partial('store/account/nav') ?>
    <div>
        <h1 style="font-size:22px">Hi, <?= e($user['name']) ?></h1>
        <p class="muted">Member since <?= e(format_date($user['created_at'], 'M Y')) ?> ·
            <?php if ($user['email_verified_at']): ?><span class="badge badge--success">Email verified</span>
            <?php else: ?><a class="badge badge--warning" href="/verify-email">Email not verified – verify now</a><?php endif; ?></p>

        <?php if ($orders): ?>
            <div class="card">
                <div class="flex between center"><p class="card__title">Recent orders</p><a class="section__link" href="/account/orders">All orders &rarr;</a></div>
                <?php foreach ($orders as $o): ?><?= partial('store/account/order-row', ['o' => $o]) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid-2" style="gap:16px;align-items:start;margin-top:16px">
            <form class="card" method="post" action="/account/profile" style="margin:0">
                <?= csrf_field() ?>
                <p class="card__title">Profile</p>
                <?= partial('store/partials/field', ['name' => 'name', 'label' => 'Full name', 'value' => old('name', $user['name'])]) ?>
                <?= partial('store/partials/field', ['name' => 'phone', 'label' => 'Mobile', 'value' => old('phone', $user['phone']), 'attrs' => 'inputmode="numeric" maxlength="10"']) ?>
                <label class="field"><span>Email</span><input class="input" value="<?= e($user['email']) ?>" disabled></label>
                <button class="btn btn--dark" type="submit">Save changes</button>
            </form>
            <form class="card" method="post" action="/account/password" style="margin:0">
                <?= csrf_field() ?>
                <p class="card__title">Change password</p>
                <?= partial('store/partials/field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="current-password"']) ?>
                <?= partial('store/partials/field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password"']) ?>
                <?= partial('store/partials/field', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password"']) ?>
                <button class="btn btn--outline" type="submit">Update password</button>
            </form>
        </div>
    </div>
</div>
