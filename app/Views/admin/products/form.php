<?php
/** @var ?array $p @var array $images @var array $variants @var array $categories */
$v = fn (string $k, $d = '') => $p[$k] ?? $d;
$catOpts = ['' => 'Select category'];
foreach ($categories as $c) {
    $catOpts[$c['id']] = ($c['parent_id'] ? '— ' : '') . $c['name'];
}
$sizes = config('app.sizes');
$variantRows = $variants ?: array_map(fn ($s) => ['id' => '', 'size' => $s, 'sku' => '', 'stock' => 0, 'is_active' => 1], ['S', 'M', 'L', 'XL', 'XXL']);
?>
<div class="page-head">
    <div><h1><?= $p ? e($p['name']) : 'Add product' ?></h1><p><?= $p ? 'SKU ' . e($p['sku']) : 'Onboard a new jersey to the catalog' ?></p></div>
    <div class="flex gap">
        <?php if ($p): ?><a class="btn btn--outline" href="/p/<?= e($p['slug']) ?>" target="_blank" rel="noopener">View on store ↗</a><?php endif; ?>
        <a class="btn btn--outline" href="/admin/products">Back</a>
    </div>
</div>

<form method="post" action="<?= $p ? '/admin/products/' . (int) $p['id'] : '/admin/products' ?>" enctype="multipart/form-data" id="product-form">
    <?= csrf_field() ?>
    <div class="cols cols--2-1">
        <div>
            <section class="card">
                <h2>Basic information</h2>
                <?= partial('admin/partials/f', ['name' => 'name', 'label' => 'Product name', 'value' => $v('name'), 'attrs' => 'required maxlength="190" data-slug-source', 'hint' => 'e.g. Argentina 2026 Home Jersey – Fan Edition']) ?>
                <div class="form-grid">
                    <?= partial('admin/partials/f', ['name' => 'slug', 'label' => 'URL slug', 'value' => $v('slug'), 'attrs' => 'required data-slug-target pattern="[a-z0-9]+(-[a-z0-9]+)*"', 'hint' => '/p/your-slug']) ?>
                    <?= partial('admin/partials/f', ['name' => 'sku', 'label' => 'SKU', 'value' => $v('sku'), 'attrs' => 'required maxlength="64"']) ?>
                </div>
                <?= partial('admin/partials/f', ['name' => 'short_description', 'label' => 'Short description', 'type' => 'textarea', 'value' => $v('short_description'), 'attrs' => 'maxlength="500" style="min-height:70px"']) ?>
                <?= partial('admin/partials/f', ['name' => 'description', 'label' => 'Full description', 'type' => 'textarea', 'value' => $v('description'), 'attrs' => 'style="min-height:160px"', 'hint' => 'Fabric, fit, care instructions, what\'s included.']) ?>
            </section>

            <section class="card">
                <h2>Jersey details</h2>
                <div class="form-grid">
                    <?= partial('admin/partials/f', ['name' => 'team', 'label' => 'Team / club', 'value' => $v('team'), 'attrs' => 'maxlength="120"']) ?>
                    <?= partial('admin/partials/f', ['name' => 'league', 'label' => 'League / competition', 'value' => $v('league'), 'attrs' => 'maxlength="120"']) ?>
                    <?= partial('admin/partials/f', ['name' => 'season', 'label' => 'Season', 'value' => $v('season'), 'attrs' => 'maxlength="20" placeholder="2026/27"']) ?>
                    <?= partial('admin/partials/f', ['name' => 'kit_type', 'label' => 'Kit type', 'type' => 'select', 'value' => $v('kit_type'), 'options' => ['' => '—'] + config('app.kit_types')]) ?>
                    <?= partial('admin/partials/f', ['name' => 'brand', 'label' => 'Brand / manufacturer', 'value' => $v('brand'), 'attrs' => 'maxlength="80"']) ?>
                    <?= partial('admin/partials/f', ['name' => 'gender', 'label' => 'Fit', 'type' => 'select', 'value' => $v('gender', 'unisex'), 'options' => config('app.genders')]) ?>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><h2>Sizes &amp; stock</h2><button class="btn btn--outline btn--sm" type="button" data-add-variant>+ Add size</button></div>
                <?php if ($err = error_for('variants')): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
                <div id="variants">
                    <?php foreach ($variantRows as $i => $row): if (!($row['is_active'] ?? 1)) continue; ?>
                        <div class="variant-row">
                            <input type="hidden" name="variants[<?= $i ?>][id]" value="<?= e($row['id']) ?>">
                            <label class="field mb-0"><span>Size</span><select class="select" name="variants[<?= $i ?>][size]"><?php foreach ($sizes as $s): ?><option <?= $s === $row['size'] ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></label>
                            <label class="field mb-0"><span>Variant SKU (auto if blank)</span><input class="input" name="variants[<?= $i ?>][sku]" value="<?= e($row['sku']) ?>"></label>
                            <label class="field mb-0"><span>Stock</span><input class="input" type="number" min="0" name="variants[<?= $i ?>][stock]" value="<?= (int) $row['stock'] ?>"></label>
                            <button class="btn btn--danger btn--sm" type="button" data-remove-row aria-label="Remove size">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template id="variant-template">
                    <div class="variant-row">
                        <input type="hidden" name="variants[__i__][id]" value="">
                        <label class="field mb-0"><span>Size</span><select class="select" name="variants[__i__][size]"><?php foreach ($sizes as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></label>
                        <label class="field mb-0"><span>Variant SKU</span><input class="input" name="variants[__i__][sku]"></label>
                        <label class="field mb-0"><span>Stock</span><input class="input" type="number" min="0" name="variants[__i__][stock]" value="0"></label>
                        <button class="btn btn--danger btn--sm" type="button" data-remove-row aria-label="Remove size">✕</button>
                    </div>
                </template>
                <p class="muted mb-0" style="font-size:12px">Every stock change is recorded in the inventory ledger. Removing a size hides it (past orders keep their history).</p>
            </section>

            <section class="card">
                <h2>Images</h2>
                <?php if ($images): ?>
                    <div class="images-grid mb-2">
                        <?php foreach ($images as $img): ?>
                            <figure>
                                <img src="<?= e(upload_url($img['path'])) ?>" alt="" loading="lazy">
                                <figcaption>
                                    <button class="btn btn--danger btn--sm" type="submit" formaction="/admin/products/<?= (int) $p['id'] ?>/images/<?= (int) $img['id'] ?>/delete" formnovalidate data-no-lock data-confirm-click="Delete this image?">✕</button>
                                </figcaption>
                                <input class="input" type="number" name="image_order[<?= (int) $img['id'] ?>]" value="<?= (int) $img['sort_order'] ?>" title="Display order" style="border-radius:0;border-width:1px 0 0">
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?= partial('admin/partials/f', ['name' => 'images[]', 'label' => 'Upload images (JPG/PNG/WEBP, up to 10, max ' . config('app.uploads.max_mb') . ' MB each)', 'type' => 'file', 'attrs' => 'multiple data-preview="img-preview"', 'hint' => 'Recommended 1200×1500 px (4:5). First image is the main image; the second appears on hover.']) ?>
                <?php if ($err = error_for('images')): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
                <div class="images-grid" id="img-preview"></div>
            </section>

            <section class="card">
                <h2>Search engine listing</h2>
                <?= partial('admin/partials/f', ['name' => 'meta_title', 'label' => 'Meta title', 'value' => $v('meta_title'), 'attrs' => 'maxlength="190"', 'hint' => 'Defaults to product name. ~60 characters is ideal.']) ?>
                <?= partial('admin/partials/f', ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'value' => $v('meta_description'), 'attrs' => 'maxlength="255" style="min-height:70px"', 'hint' => '~155 characters. Shown in Google results.']) ?>
            </section>
        </div>

        <div>
            <section class="card">
                <h2>Visibility</h2>
                <?= partial('admin/partials/f', ['name' => 'is_active', 'label' => 'Live on store', 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?>
                <?= partial('admin/partials/f', ['name' => 'is_featured', 'label' => 'Featured on homepage', 'type' => 'checkbox', 'value' => $v('is_featured', 0)]) ?>
                <?= partial('admin/partials/f', ['name' => 'is_new_arrival', 'label' => 'New arrival badge', 'type' => 'checkbox', 'value' => $v('is_new_arrival', 1)]) ?>
                <?= partial('admin/partials/f', ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'value' => $v('category_id'), 'options' => $catOpts, 'attrs' => 'required']) ?>
            </section>
            <section class="card">
                <h2>Pricing</h2>
                <?= partial('admin/partials/f', ['name' => 'mrp', 'label' => 'MRP (₹)', 'type' => 'number', 'value' => $v('mrp'), 'attrs' => 'required min="1" step="0.01"']) ?>
                <?= partial('admin/partials/f', ['name' => 'price', 'label' => 'Selling price (₹, GST inclusive)', 'type' => 'number', 'value' => $v('price'), 'attrs' => 'required min="1" step="0.01"']) ?>
                <div class="form-grid">
                    <?= partial('admin/partials/f', ['name' => 'tax_rate', 'label' => 'GST %', 'type' => 'number', 'value' => $v('tax_rate', '5'), 'attrs' => 'required min="0" max="28" step="0.01"', 'hint' => 'Apparel: 5% / 18% by price slab']) ?>
                    <?= partial('admin/partials/f', ['name' => 'hsn_code', 'label' => 'HSN code', 'value' => $v('hsn_code', '6109')]) ?>
                </div>
            </section>
            <section class="card">
                <h2>Personalisation</h2>
                <?= partial('admin/partials/f', ['name' => 'allow_customization', 'label' => 'Allow name & number printing', 'type' => 'checkbox', 'value' => $v('allow_customization', 0)]) ?>
                <?= partial('admin/partials/f', ['name' => 'customization_price', 'label' => 'Printing charge (₹)', 'type' => 'number', 'value' => $v('customization_price', '249'), 'attrs' => 'min="0" step="1"']) ?>
            </section>
            <?php if ($p): ?>
                <section class="card">
                    <h2>Danger zone</h2>
                    <button class="btn btn--danger btn--block" type="submit" formaction="/admin/products/<?= (int) $p['id'] ?>/delete" formnovalidate
                            data-confirm-click="Archive this product? It will disappear from the store." data-no-lock>Archive product</button>
                    <p class="muted mb-0 mt-1" style="font-size:12px">Hides it from the store. Order history is kept.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn--outline" href="/admin/products">Cancel</a>
        <button class="btn" type="submit"><?= $p ? 'Save changes' : 'Create product' ?></button>
    </div>
</form>
