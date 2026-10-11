<?php
/** @var ?array $a @var array $roles */
$roleOpts = array_column($roles, 'name', 'id');
?>
<div class="page-head"><h1><?= $a ? 'Edit ' . e($a['name']) : 'Add admin' ?></h1><a class="btn btn--outline" href="/admin/team">Back</a></div>
<form class="card" method="post" action="<?= $a ? '/admin/team/admins/' . (int) $a['id'] : '/admin/team/admins' ?>" style="max-width:640px" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
        <?= partial('admin/partials/f', ['name' => 'name', 'label' => 'Name', 'value' => $a['name'] ?? '', 'attrs' => 'required']) ?>
        <?= partial('admin/partials/f', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $a['email'] ?? '', 'attrs' => 'required']) ?>
        <?= partial('admin/partials/f', ['name' => 'role_id', 'label' => 'Role', 'type' => 'select', 'value' => $a['role_id'] ?? '', 'options' => $roleOpts]) ?>
        <?php if ($a): ?>
            <?= partial('admin/partials/f', ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $a['status'], 'options' => ['active' => 'Active', 'disabled' => 'Disabled']]) ?>
        <?php endif; ?>
        <?= partial('admin/partials/f', ['name' => 'password', 'label' => $a ? 'New password (leave blank to keep)' : 'Password (min 12 characters)', 'type' => 'password', 'attrs' => ($a ? '' : 'required ') . 'minlength="12" autocomplete="new-password"']) ?>
        <?= partial('admin/partials/f', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'attrs' => 'autocomplete="new-password"']) ?>
    </div>
    <button class="btn" type="submit">Save</button>
</form>
