<?php
use App\Services\CartService;
use App\Services\CatalogService;

/** @var string $content */
$menu = CatalogService::menu();
$cartCount = (new CartService())->count();
$user = auth()->user();

// Intro loader: homepage only (never delay a product page from Google), once per session by default, never for bots.
$isBot = (bool) preg_match('/bot|crawl|spider|slurp|lighthouse|pagespeed|headless/i', request()->userAgent());
$showLoader = !empty($isHome) && setting('loader_enabled') === '1' && !$isBot
    && (setting('loader_frequency') === 'always' || !session()->get('loader_seen'));
if ($showLoader) {
    session()->set('loader_seen', 1);
}
$ga = (string) setting('ga4_measurement_id');
?>
<!doctype html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?= partial('store/partials/seo') ?>
    <meta name="theme-color" content="#0b1020">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(asset('img/logo-mark.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,800;1,800&family=Inter:wght@400;500;600;700;800&display=swap">
    <?php if ($showLoader): ?>
        <link rel="preload" as="image" href="<?= e(asset('img/loader/kickoff.jpg')) ?>"
              imagesrcset="<?= e(asset('img/loader/kickoff-768.jpg')) ?> 768w, <?= e(asset('img/loader/kickoff.jpg')) ?> 1536w" imagesizes="100vw">
        <link rel="stylesheet" href="<?= e(asset('css/loader.css')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if ($ga !== ''): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
        <script nonce="<?= e(csp_nonce()) ?>">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
    <?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($showLoader): ?>
    <?= partial('store/partials/loader') ?>
    <script src="<?= e(asset('js/loader.js')) ?>" defer></script>
<?php endif; ?>

<?php if ($announce = setting('announcement')): ?>
    <div class="announce"><?= e($announce) ?></div>
<?php endif; ?>

<?= partial('store/partials/header', ['menu' => $menu, 'cartCount' => $cartCount, 'user' => $user]) ?>

<main id="main">
    <?= $content ?>
</main>

<?= partial('store/partials/footer', ['menu' => $menu]) ?>

<nav class="appbar" aria-label="Quick navigation">
    <a href="/" class="<?= is_path('/') ? 'is-active' : '' ?>"><?= icon('home') ?>Home</a>
    <a href="/shop" class="<?= is_path('/shop') || is_path('/c/') ? 'is-active' : '' ?>"><?= icon('grid') ?>Shop</a>
    <a href="/wishlist" class="<?= is_path('/wishlist') ? 'is-active' : '' ?>"><?= icon('heart') ?>Wishlist</a>
    <a href="/cart" class="<?= is_path('/cart') ? 'is-active' : '' ?>"><?= icon('bag') ?>Bag<span class="action__count" data-cart-count="<?= $cartCount ?>" style="right:22%"><?= $cartCount ?: '' ?></span></a>
    <a href="<?= $user ? '/account' : '/login' ?>" class="<?= is_path('/account') ? 'is-active' : '' ?>"><?= icon('user') ?><?= $user ? 'Account' : 'Login' ?></a>
</nav>

<div class="toasts" id="toasts" role="status" aria-live="polite">
    <?php foreach (['success', 'error', 'info'] as $type): ?>
        <?php if ($msg = flash($type)): ?>
            <div class="toast toast--<?= $type ?>" data-autohide><?= e($msg) ?><button type="button" aria-label="Dismiss">&times;</button></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<script nonce="<?= e(csp_nonce()) ?>">window.JM={csrf:"<?= e(csrf_token()) ?>",loggedIn:<?= $user ? 'true' : 'false' ?>};</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($scripts)): foreach ((array) $scripts as $s): ?>
    <script src="<?= e($s) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
