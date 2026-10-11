<?php
use App\Services\CatalogService;

/** @var string $heading @var ?array $category @var array $children @var App\Core\Paginator $paginator
 *  @var array $facets @var array $filters @var array $banners @var string $path */
$kitLabels = config('app.kit_types');
$genderLabels = config('app.genders');
$q = request()->queryAll();

// Build "remove this filter" links for the applied-filter chips.
$applied = [];
foreach (['team', 'league', 'brand', 'kit', 'gender', 'size'] as $key) {
    foreach ((array) ($filters[$key] ?? []) as $val) {
        $next = $q;
        $next[$key] = array_values(array_diff((array) ($q[$key] ?? []), [$val]));
        unset($next['page']);
        $label = $key === 'kit' ? ($kitLabels[$val] ?? $val) : ($key === 'gender' ? ($genderLabels[$val] ?? $val) : $val);
        $applied[] = [$label, $path . '?' . http_build_query($next)];
    }
}
if ($filters['price']) {
    $next = $q; unset($next['price'], $next['page']);
    $applied[] = [CatalogService::PRICE_BUCKETS[$filters['price']] ?? $filters['price'], $path . '?' . http_build_query($next)];
}
if ($filters['discount']) {
    $next = $q; unset($next['discount'], $next['page']);
    $applied[] = [$filters['discount'] . '%+ off', $path . '?' . http_build_query($next)];
}
$clearUrl = $path . ($filters['q'] ? '?q=' . rawurlencode($filters['q']) : '');

$facetBlock = function (string $key, string $title, array $rows, array $labels = []) use ($filters) {
    if (!$rows) {
        return '';
    }
    $html = '<div class="fgroup"><h3>' . e($title) . '</h3><div class="fgroup__list">';
    foreach ($rows as $r) {
        $checked = in_array((string) $r['value'], (array) ($filters[$key] ?? []), true) ? ' checked' : '';
        $html .= '<label class="check"><input type="checkbox" name="' . e($key) . '[]" value="' . e($r['value']) . '"' . $checked . '> '
            . e($labels[$r['value']] ?? $r['value']) . ' <em>(' . (int) $r['cnt'] . ')</em></label>';
    }
    return $html . '</div></div>';
};
?>
<div class="container">
    <?= partial('store/partials/crumbs') ?>

    <?php foreach (array_slice($banners, 0, 1) as $b): ?>
        <a class="promo" href="<?= e($b['link_url'] ?: '#') ?>" style="aspect-ratio:auto;max-height:220px;margin-bottom:16px">
            <img src="<?= e(upload_url($b['image_desktop'])) ?>" alt="<?= e($b['title']) ?>" width="1600" height="220">
        </a>
    <?php endforeach; ?>

    <div class="plp-head">
        <h1><?= e($heading) ?></h1>
        <span>- <?= number_format($paginator->total) ?> items</span>
    </div>
    <?php if ($category && $category['description'] && $paginator->page === 1): ?>
        <p class="muted" style="max-width:820px"><?= e($category['description']) ?></p>
    <?php endif; ?>

    <?php if ($children): ?>
        <div class="subcats">
            <?php foreach ($children as $c): ?><a class="chip" href="/c/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="plp">
        <form class="filters" id="filters" method="get" action="<?= e($path) ?>" data-autosubmit>
            <?php if ($filters['q']): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
            <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
            <div class="filters__title">Filters <?php if ($applied): ?><a href="<?= e($clearUrl) ?>">Clear all</a><?php endif; ?></div>

            <?= $facetBlock('team', 'Team', $facets['team']) ?>
            <?= $facetBlock('league', 'League', $facets['league']) ?>

            <?php if ($facets['size']): ?>
                <div class="fgroup">
                    <h3>Size</h3>
                    <div class="fsizes">
                        <?php foreach ($facets['size'] as $s): ?>
                            <label><input type="checkbox" name="size[]" value="<?= e($s['value']) ?>" <?= in_array($s['value'], $filters['size'], true) ? 'checked' : '' ?>><span><?= e($s['value']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="fgroup">
                <h3>Price</h3>
                <?php foreach (CatalogService::PRICE_BUCKETS as $val => $lbl): ?>
                    <label class="check"><input type="radio" name="price" value="<?= e($val) ?>" <?= $filters['price'] === $val ? 'checked' : '' ?>> <?= e($lbl) ?></label>
                <?php endforeach; ?>
            </div>

            <?= $facetBlock('kit', 'Kit type', $facets['kit'], $kitLabels) ?>
            <?= $facetBlock('gender', 'Fit', $facets['gender'], $genderLabels) ?>
            <?= $facetBlock('brand', 'Brand', $facets['brand']) ?>

            <div class="fgroup">
                <h3>Discount</h3>
                <?php foreach ([10, 20, 30, 40, 50] as $d): ?>
                    <label class="check"><input type="radio" name="discount" value="<?= $d ?>" <?= (int) $filters['discount'] === $d ? 'checked' : '' ?>> <?= $d ?>% and above</label>
                <?php endforeach; ?>
            </div>
            <noscript><button class="btn btn--block mt-2" type="submit">Apply filters</button></noscript>
        </form>

        <div>
            <div class="sortbar">
                <button class="btn btn--outline btn--sm mfilter-btn" type="button" data-filters-toggle><?= icon('filter', 16) ?> Filters<?= $applied ? ' (' . count($applied) . ')' : '' ?></button>
                <div class="applied">
                    <?php foreach ($applied as [$lbl, $href]): ?><a href="<?= e($href) ?>" aria-label="Remove filter <?= e($lbl) ?>"><?= e($lbl) ?></a><?php endforeach; ?>
                </div>
                <form class="sort" method="get" action="<?= e($path) ?>">
                    <?php foreach ($q as $k => $v): if (in_array($k, ['sort', 'page'], true)) continue; ?>
                        <?php foreach ((array) $v as $vv): ?><input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
                    <?php endforeach; ?>
                    <label for="sort">Sort by</label>
                    <select class="select" id="sort" name="sort" data-autosubmit-select>
                        <?php foreach (CatalogService::SORTS as $k => $lbl): ?>
                            <option value="<?= e($k) ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($paginator->items): ?>
                <div class="pgrid pgrid--wide" id="pgrid">
                    <?php foreach ($paginator->items as $i => $p): ?>
                        <?= partial('store/partials/product-card', ['p' => $p, 'lazy' => $i > 3]) ?>
                    <?php endforeach; ?>
                </div>
                <?= $paginator->links() ?>
            <?php else: ?>
                <div class="empty">
                    <?= icon('shirt', 64) ?>
                    <h2>No jerseys found</h2>
                    <p>Try removing some filters or searching for a different team or player.</p>
                    <a class="btn" href="<?= e($applied ? $clearUrl : '/shop') ?>"><?= $applied ? 'Clear filters' : 'Browse all jerseys' ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
