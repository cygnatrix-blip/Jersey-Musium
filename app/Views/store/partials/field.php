<?php
/** Reusable labelled input with validation error. @var string $name @var string $label @var ?string $value @var ?string $type @var ?string $attrs */
$err = error_for($name);
$value = $value ?? old($name);
?>
<label class="field <?= $err ? 'has-error' : '' ?>">
    <span><?= e($label) ?></span>
    <input class="input" type="<?= e($type ?? 'text') ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $attrs ?? '' ?> <?= $err ? 'aria-invalid="true"' : '' ?>>
    <?php if ($err): ?><small class="field-error"><?= e($err) ?></small><?php endif; ?>
</label>
