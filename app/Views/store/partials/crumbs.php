<?php $crumbs = seo()->breadcrumbs; if (!$crumbs) { return; } ?>
<nav aria-label="Breadcrumb">
    <ol class="crumbs">
        <?php foreach ($crumbs as [$label, $path]): ?>
            <li><?php if ($path): ?><a href="<?= e($path) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
