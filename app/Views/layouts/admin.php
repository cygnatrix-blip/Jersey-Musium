<?php
/** @var string $content @var ?string $title */
$a = admin_auth()->user();
$counts = [
    'orders'  => (int) db()->scalar("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','packed')"),
    'returns' => (int) db()->scalar("SELECT COUNT(*) FROM returns WHERE status = 'requested'"),
    'reviews' => (int) db()->scalar("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'"),
];
$nav = [
    'Overview'  => [['/admin', 'Dashboard', 'dashboard.view', null]],
    'Catalog'   => [['/admin/products', 'Products', 'products.manage', null], ['/admin/categories', 'Categories', 'categories.manage', null]],
    'Sales'     => [['/admin/orders', 'Orders', 'orders.view', $counts['orders']], ['/admin/returns', 'Returns', 'returns.manage', $counts['returns']],
                    ['/admin/payments', 'Transactions', 'payments.view', null], ['/admin/refunds', 'Refunds', 'refunds.manage', null]],
    'Marketing' => [['/admin/coupons', 'Promo codes', 'coupons.manage', null], ['/admin/banners', 'Offer banners', 'banners.manage', null]],
    'Customers' => [['/admin/customers', 'Customers', 'customers.manage', null], ['/admin/reviews', 'Reviews', 'reviews.manage', $counts['reviews']]],
    'Content'   => [['/admin/pages', 'Pages', 'pages.manage', null]],
    'System'    => [['/admin/settings', 'Settings', 'settings.manage', null], ['/admin/team', 'Team & roles', 'admins.manage', null], ['/admin/audit', 'Audit log', 'audit.view', null]],
];
$path = request()->path();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(($title ?? 'Admin') . ' · ' . setting('store_name') . ' Admin') ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@1,800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<div class="adm">
    <aside class="side" id="side">
        <a class="side__brand" href="/admin">
            <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" width="30" height="30">
            <div>Jersey <span>Museum</span><small>ADMIN CONSOLE</small></div>
        </a>
        <nav aria-label="Admin">
            <?php foreach ($nav as $section => $links):
                $links = array_filter($links, fn ($l) => admin_auth()->can($l[2]));
                if (!$links) continue; ?>
                <div class="side__label"><?= e($section) ?></div>
                <?php foreach ($links as [$href, $label, $perm, $count]):
                    $active = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
                    <a href="<?= e($href) ?>" class="<?= $active ? 'is-active' : '' ?>"><?= e($label) ?><?php if ($count): ?><span class="count"><?= (int) $count ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="scrim" id="scrim"></div>

    <div>
        <header class="top">
            <button class="top__menu" type="button" id="menu-btn" aria-label="Open menu"><?= icon('menu', 22) ?></button>
            <a class="btn btn--outline btn--sm" href="/" target="_blank" rel="noopener">View store ↗</a>
            <div class="top__right">
                <div class="top__user"><strong><?= e($a['name']) ?></strong><small><?= e($a['role_name']) ?></small></div>
                <form method="post" action="/admin/logout"><?= csrf_field() ?><button class="btn btn--outline btn--sm" type="submit">Logout</button></form>
            </div>
        </header>
        <main class="main">
            <?php foreach (['success', 'error', 'info'] as $type): if ($msg = flash($type)): ?>
                <div class="flash flash--<?= $type ?>" role="status"><?= e($msg) ?></div>
            <?php endif; endforeach; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">window.JM={csrf:"<?= e(csrf_token()) ?>"};</script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
