<?php
/** @var ?array $c @var array $categories */
$v = fn (string $k, $d = '') => $c[$k] ?? $d;
$dt = fn (?string $s) => $s ? date('Y-m-d\TH:i', strtotime($s)) : '';
$cats = ['' => 'All products'];
foreach ($categories as $cat) {
    $cats[$cat['id']] = $cat['name'];
}
?>
<div class="page-head"><h1><?= $c ? 'Edit ' . e($c['code']) : 'Create promo code' ?></h1><a class="btn btn--outline" href="/admin/coupons">Back</a></div>
<form method="post" action="<?= $c ? '/admin/coupons/' . (int) $c['id'] : '/admin/coupons' ?>">
    <?= csrf_field() ?>
    <div class="cols cols--2-1">
        <section class="card">
            <div class="form-grid">
                <?= partial('admin/partials/f', ['name' => 'code', 'label' => 'Code', 'value' => $v('code'), 'attrs' => 'required maxlength="40" style="text-transform:uppercase"', 'hint' => 'e.g. KICKOFF10, FIRSTJERSEY']) ?>
                <?= partial('admin/partials/f', ['name' => 'description', 'label' => 'Description (shown to customers)', 'value' => $v('description')]) ?>
                <?= partial('admin/partials/f', ['name' => 'type', 'label' => 'Discount type', 'type' => 'select', 'value' => $v('type', 'percent'), 'options' => ['percent' => 'Percentage (%)', 'flat' => 'Flat amount (₹)']]) ?>
                <?= partial('admin/partials/f', ['name' => 'value', 'label' => 'Value', 'type' => 'number', 'value' => $v('value'), 'attrs' => 'required step="0.01" min="1"']) ?>
                <?= partial('admin/partials/f', ['name' => 'max_discount', 'label' => 'Max discount ₹ (percent only)', 'type' => 'number', 'value' => $v('max_discount'), 'attrs' => 'step="1" min="1"']) ?>
                <?= partial('admin/partials/f', ['name' => 'min_order_amount', 'label' => 'Minimum order ₹', 'type' => 'number', 'value' => $v('min_order_amount', 0), 'attrs' => 'step="1" min="0"']) ?>
                <?= partial('admin/partials/f', ['name' => 'category_id', 'label' => 'Applies to', 'type' => 'select', 'value' => $v('category_id'), 'options' => $cats]) ?>
                <?= partial('admin/partials/f', ['name' => 'usage_limit', 'label' => 'Total redemptions limit', 'type' => 'number', 'value' => $v('usage_limit'), 'attrs' => 'min="1"', 'hint' => 'Blank = unlimited']) ?>
                <?= partial('admin/partials/f', ['name' => 'per_user_limit', 'label' => 'Uses per customer', 'type' => 'number', 'value' => $v('per_user_limit', 1), 'attrs' => 'required min="1"']) ?>
                <span></span>
                <?= partial('admin/partials/f', ['name' => 'starts_at', 'label' => 'Starts', 'type' => 'datetime-local', 'value' => $dt($c['starts_at'] ?? null)]) ?>
                <?= partial('admin/partials/f', ['name' => 'ends_at', 'label' => 'Ends', 'type' => 'datetime-local', 'value' => $dt($c['ends_at'] ?? null)]) ?>
            </div>
        </section>
        <section class="card">
            <?= partial('admin/partials/f', ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?><br>
            <?= partial('admin/partials/f', ['name' => 'is_public', 'label' => 'Show in "available offers"', 'type' => 'checkbox', 'value' => $v('is_public', 1)]) ?><br>
            <?= partial('admin/partials/f', ['name' => 'first_order_only', 'label' => 'First order only', 'type' => 'checkbox', 'value' => $v('first_order_only', 0)]) ?>
            <?php if ($c): ?><p class="muted mt-2 mb-0">Redeemed <?= (int) $c['used_count'] ?> times.</p><?php endif; ?>
        </section>
    </div>
    <div class="form-actions"><a class="btn btn--outline" href="/admin/coupons">Cancel</a><button class="btn" type="submit">Save promo code</button></div>
</form>
