<?php
use App\Controllers\Admin\BannerController;

/** @var ?array $b */
$v = fn (string $k, $d = '') => $b[$k] ?? $d;
$dt = fn (?string $s) => $s ? date('Y-m-d\TH:i', strtotime($s)) : '';
?>
<div class="page-head"><h1><?= $b ? 'Edit banner' : 'New banner' ?></h1><a class="btn btn--outline" href="/admin/banners">Back</a></div>
<form method="post" action="<?= $b ? '/admin/banners/' . (int) $b['id'] : '/admin/banners' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="cols cols--2-1">
        <section class="card">
            <div class="form-grid">
                <?= partial('admin/partials/f', ['name' => 'title', 'label' => 'Headline', 'value' => $v('title'), 'attrs' => 'required maxlength="140"']) ?>
                <?= partial('admin/partials/f', ['name' => 'subtitle', 'label' => 'Sub-headline', 'value' => $v('subtitle')]) ?>
                <?= partial('admin/partials/f', ['name' => 'cta_label', 'label' => 'Button label', 'value' => $v('cta_label', 'Shop now')]) ?>
                <?= partial('admin/partials/f', ['name' => 'link_url', 'label' => 'Link', 'value' => $v('link_url', '/shop'), 'hint' => 'Relative (/c/club-jerseys) or full URL']) ?>
                <?= partial('admin/partials/f', ['name' => 'placement', 'label' => 'Placement', 'type' => 'select', 'value' => $v('placement', 'home_hero'), 'options' => BannerController::PLACEMENTS]) ?>
                <?= partial('admin/partials/f', ['name' => 'theme', 'label' => 'Text colour', 'type' => 'select', 'value' => $v('theme', 'dark'), 'options' => ['dark' => 'White text on dark image', 'light' => 'Dark text on light image']]) ?>
                <?= partial('admin/partials/f', ['name' => 'starts_at', 'label' => 'Go live at', 'type' => 'datetime-local', 'value' => $dt($b['starts_at'] ?? null)]) ?>
                <?= partial('admin/partials/f', ['name' => 'ends_at', 'label' => 'Take down at', 'type' => 'datetime-local', 'value' => $dt($b['ends_at'] ?? null)]) ?>
            </div>
        </section>
        <section class="card">
            <?= partial('admin/partials/f', ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?>
            <?= partial('admin/partials/f', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'value' => $v('sort_order', 0)]) ?>
            <?php if ($v('image_desktop')): ?><img src="<?= e(upload_url($b['image_desktop'])) ?>" alt="" style="border-radius:8px;margin-bottom:8px"><?php endif; ?>
            <?= partial('admin/partials/f', ['name' => 'image_desktop', 'label' => 'Desktop image' . ($b ? ' (leave empty to keep)' : ''), 'type' => 'file', 'attrs' => $b ? '' : 'required']) ?>
            <?php if ($v('image_mobile')): ?><img src="<?= e(upload_url($b['image_mobile'])) ?>" alt="" style="border-radius:8px;margin-bottom:8px;max-width:120px"><?php endif; ?>
            <?= partial('admin/partials/f', ['name' => 'image_mobile', 'label' => 'Mobile image (optional, portrait)', 'type' => 'file']) ?>
        </section>
    </div>
    <div class="form-actions"><a class="btn btn--outline" href="/admin/banners">Cancel</a><button class="btn" type="submit">Save banner</button></div>
</form>
