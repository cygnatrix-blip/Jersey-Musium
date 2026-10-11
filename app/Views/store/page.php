<?php /** @var array $page */ ?>
<div class="container">
    <?= partial('store/partials/crumbs') ?>
    <article class="prose">
        <h1><?= e($page['title']) ?></h1>
        <?= safe_html($page['content']) ?>
        <p class="muted" style="font-size:12px">Last updated: <?= e(format_date($page['updated_at'])) ?></p>
    </article>
</div>
