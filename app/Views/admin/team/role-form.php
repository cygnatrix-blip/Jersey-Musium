<?php /** @var ?array $r @var array $perms */ $has = $r['permissions'] ?? []; $super = ($r['slug'] ?? '') === 'super_admin'; ?>
<div class="page-head"><h1><?= $r ? 'Role: ' . e($r['name']) : 'New role' ?></h1><a class="btn btn--outline" href="/admin/team">Back</a></div>
<form class="card" method="post" action="<?= $r ? '/admin/team/roles/' . (int) $r['id'] : '/admin/team/roles' ?>" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <?= partial('admin/partials/f', ['name' => 'name', 'label' => 'Role name', 'value' => $r['name'] ?? '', 'attrs' => 'required data-slug-source' . ($super ? ' disabled' : '')]) ?>
        <?= partial('admin/partials/f', ['name' => 'slug', 'label' => 'Key', 'value' => $r['slug'] ?? '', 'attrs' => 'required data-slug-target' . ($super ? ' disabled' : '')]) ?>
    </div>
    <h2 class="mt-1">Permissions</h2>
    <?php if ($super): ?><p class="muted">Super Admin always has every permission.</p><?php endif; ?>
    <div class="form-grid">
        <?php foreach ($perms as $key => $desc): ?>
            <label class="check" style="margin-bottom:.6rem"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" <?= $super || in_array($key, $has, true) ? 'checked' : '' ?> <?= $super ? 'disabled' : '' ?>> <span><strong><?= e($key) ?></strong><br><span class="muted"><?= e($desc) ?></span></span></label>
        <?php endforeach; ?>
    </div>
    <?php if (!$super): ?><button class="btn mt-2" type="submit">Save role</button><?php endif; ?>
</form>
