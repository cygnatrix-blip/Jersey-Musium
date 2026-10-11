<?php
/**
 * Generic admin form field.
 * @var string $name @var string $label @var ?string $type text|number|email|password|textarea|select|checkbox|date|datetime-local|file|url
 * @var mixed $value @var ?array $options (for select: value => label) @var ?string $hint @var ?string $attrs @var ?string $class
 */
$type ??= 'text';
$err = error_for(rtrim($name, '[]'));
$old = old($name, null);
$value = $old ?? ($value ?? '');
$attrs ??= '';
?>
<?php if ($type === 'checkbox'): ?>
    <label class="check <?= e($class ?? '') ?>" style="margin:.4rem 1.25rem .9rem 0">
        <input type="hidden" name="<?= e($name) ?>" value="0">
        <input type="checkbox" name="<?= e($name) ?>" value="1" <?= (string) $value === '1' || $value === 1 || $value === true ? 'checked' : '' ?> <?= $attrs ?>>
        <?= e($label) ?>
    </label>
<?php else: ?>
    <label class="field <?= $err ? 'has-error' : '' ?> <?= e($class ?? '') ?>">
        <span><?= e($label) ?></span>
        <?php if ($type === 'textarea'): ?>
            <textarea class="textarea" name="<?= e($name) ?>" <?= $attrs ?>><?= e($value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <select class="select" name="<?= e($name) ?>" <?= $attrs ?>>
                <?php foreach ($options as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (string) $k === (string) $value ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'file'): ?>
            <input class="input" type="file" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp" <?= $attrs ?>>
        <?php else: ?>
            <input class="input" type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($type === 'password' ? '' : $value) ?>" <?= $attrs ?>>
        <?php endif; ?>
        <?php if (!empty($hint)): ?><small class="hint"><?= e($hint) ?></small><?php endif; ?>
        <?php if ($err): ?><small class="field-error"><?= e($err) ?></small><?php endif; ?>
    </label>
<?php endif; ?>
