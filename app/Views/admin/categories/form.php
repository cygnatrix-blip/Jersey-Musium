<?php
/** @var ?array $c @var array $parents */
$v = fn (string $k, $d = '') => $c[$k] ?? $d;
$opts = ['' => '— None (top level) —'];
foreach ($parents as $p) {
    $opts[$p['id']] = $p['name'];
}
?>
<div class="page-head"><h1><?= $c ? 'Edit ' . e($c['name']) : 'Add category' ?></h1><a class="btn btn--outline" href="/admin/categories">Back</a></div>
<form method="post" action="<?= $c ? '/admin/categories/' . (int) $c['id'] : '/admin/categories' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="cols cols--2-1">
        <section class="card">
            <div class="form-grid">
                <?= partial('admin/partials/f', ['name' => 'name', 'label' => 'Name', 'value' => $v('name'), 'attrs' => 'required data-slug-source']) ?>
                <?= partial('admin/partials/f', ['name' => 'slug', 'label' => 'URL slug', 'value' => $v('slug'), 'attrs' => 'required data-slug-target', 'hint' => '/c/slug']) ?>
                <?= partial('admin/partials/f', ['name' => 'parent_id', 'label' => 'Parent category', 'type' => 'select', 'value' => $v('parent_id'), 'options' => $opts]) ?>
                <?= partial('admin/partials/f', ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $v('sort_order', 0)]) ?>
            </div>
            <?= partial('admin/partials/f', ['name' => 'description', 'label' => 'Description (shown on category page – good for SEO)', 'type' => 'textarea', 'value' => $v('description')]) ?>
            <?= partial('admin/partials/f', ['name' => 'meta_title', 'label' => 'Meta title', 'value' => $v('meta_title')]) ?>
            <?= partial('admin/partials/f', ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'value' => $v('meta_description'), 'attrs' => 'maxlength="255" style="min-height:70px"']) ?>
        </section>
        <section class="card">
            <?= partial('admin/partials/f', ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?>
            <?= partial('admin/partials/f', ['name' => 'show_in_menu', 'label' => 'Show in menu', 'type' => 'checkbox', 'value' => $v('show_in_menu', 1)]) ?>
            <?php if ($v('image')): ?><img src="<?= e(upload_url($c['image'])) ?>" alt="" style="width:120px;border-radius:50%;margin-bottom:10px"><?php endif; ?>
            <?= partial('admin/partials/f', ['name' => 'image', 'label' => 'Image (square, 400×400)', 'type' => 'file']) ?>
        </section>
    </div>
    <div class="form-actions"><a class="btn btn--outline" href="/admin/categories">Cancel</a><button class="btn" type="submit">Save</button></div>
</form>
