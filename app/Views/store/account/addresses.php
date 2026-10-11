<?php /** @var array $addresses @var array $states */ ?>
<div class="container account">
    <?= partial('store/account/nav') ?>
    <div>
        <h1 style="font-size:22px">Saved addresses</h1>
        <?php foreach ($addresses as $a): ?>
            <div class="card">
                <div class="flex between wrap gap">
                    <div>
                        <strong><?= e($a['name']) ?></strong> <span class="badge"><?= e($a['type']) ?></span>
                        <?php if ($a['is_default']): ?><span class="badge badge--success">Default</span><?php endif; ?>
                        <p class="mb-0 mt-1"><?= e(implode(', ', array_filter([$a['line1'], $a['line2'], $a['landmark'], $a['city'], $a['state']]))) ?> – <?= e($a['pincode']) ?><br>Mobile: <?= e($a['phone']) ?></p>
                    </div>
                    <div class="flex gap">
                        <?php if (!$a['is_default']): ?>
                            <form method="post" action="/account/addresses/<?= (int) $a['id'] ?>/default"><?= csrf_field() ?><button class="btn btn--ghost btn--sm">Make default</button></form>
                        <?php endif; ?>
                        <form method="post" action="/account/addresses/<?= (int) $a['id'] ?>/delete" data-confirm="Delete this address?"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" style="color:var(--danger)">Delete</button></form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <form class="card" method="post" action="/account/addresses">
            <?= csrf_field() ?>
            <p class="card__title">Add new address</p>
            <div class="grid-2">
                <?= partial('store/partials/field', ['name' => 'name', 'label' => 'Full name']) ?>
                <?= partial('store/partials/field', ['name' => 'phone', 'label' => 'Mobile number', 'attrs' => 'inputmode="numeric" maxlength="10"']) ?>
            </div>
            <?= partial('store/partials/field', ['name' => 'line1', 'label' => 'Flat, house no., building, street']) ?>
            <?= partial('store/partials/field', ['name' => 'line2', 'label' => 'Area, locality (optional)']) ?>
            <div class="grid-2">
                <?= partial('store/partials/field', ['name' => 'pincode', 'label' => 'Pincode', 'attrs' => 'inputmode="numeric" maxlength="6"']) ?>
                <?= partial('store/partials/field', ['name' => 'city', 'label' => 'City']) ?>
                <label class="field <?= error_for('state') ? 'has-error' : '' ?>"><span>State</span>
                    <select class="select" name="state"><option value="">Select state</option><?php foreach ($states as $s): ?><option <?= old('state') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
                    <?php if ($err = error_for('state')): ?><small class="field-error"><?= e($err) ?></small><?php endif; ?>
                </label>
                <?= partial('store/partials/field', ['name' => 'landmark', 'label' => 'Landmark (optional)']) ?>
            </div>
            <div class="flex gap wrap mb-2">
                <?php foreach (['home' => 'Home', 'work' => 'Work', 'other' => 'Other'] as $k => $v): ?><label class="check"><input type="radio" name="type" value="<?= $k ?>" <?= $k === 'home' ? 'checked' : '' ?>> <?= $v ?></label><?php endforeach; ?>
                <label class="check"><input type="checkbox" name="is_default" value="1"> Make default</label>
            </div>
            <button class="btn btn--dark" type="submit">Save address</button>
        </form>
    </div>
</div>
