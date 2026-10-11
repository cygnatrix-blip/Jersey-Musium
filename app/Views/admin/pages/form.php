<?php /** @var ?array $pg */ $v = fn (string $k, $d = '') => $pg[$k] ?? $d; ?>
<div class="page-head"><h1><?= $pg ? 'Edit ' . e($pg['title']) : 'New page' ?></h1><a class="btn btn--outline" href="/admin/pages">Back</a></div>
<form method="post" action="<?= $pg ? '/admin/pages/' . (int) $pg['id'] : '/admin/pages' ?>">
    <?= csrf_field() ?>
    <div class="cols cols--2-1">
        <section class="card">
            <?= partial('admin/partials/f', ['name' => 'title', 'label' => 'Title', 'value' => $v('title'), 'attrs' => 'required data-slug-source']) ?>
            <?= partial('admin/partials/f', ['name' => 'content', 'label' => 'Content (HTML: p, h2, h3, ul, ol, li, a, strong, em, table)', 'type' => 'textarea', 'value' => $v('content'), 'attrs' => 'required style="min-height:420px;font-family:ui-monospace,monospace;font-size:13px"']) ?>
        </section>
        <section class="card">
            <?= partial('admin/partials/f', ['name' => 'slug', 'label' => 'URL slug', 'value' => $v('slug'), 'attrs' => 'required data-slug-target', 'hint' => '/page/slug']) ?>
            <?= partial('admin/partials/f', ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'value' => $v('meta_description'), 'attrs' => 'maxlength="255" style="min-height:80px"']) ?>
            <?= partial('admin/partials/f', ['name' => 'is_active', 'label' => 'Published', 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?><br>
            <?= partial('admin/partials/f', ['name' => 'show_in_footer', 'label' => 'Link in footer', 'type' => 'checkbox', 'value' => $v('show_in_footer', 1)]) ?>
        </section>
    </div>
    <div class="form-actions"><a class="btn btn--outline" href="/admin/pages">Cancel</a><button class="btn" type="submit">Save page</button></div>
</form>
