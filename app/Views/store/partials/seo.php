<?php
$s = seo();
$title = $s->fullTitle();
$desc = $s->description ?: (string) setting('home_meta_description');
$image = $s->image ?: url(asset('img/og-default.jpg'));
$canonical = $s->canonical;
?>
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= e($s->robots) ?><?= $s->robots === 'index,follow' ? ',max-image-preview:large' : '' ?>">
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<?php if ($v = setting('google_site_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e(setting('store_name')) ?>">
<meta property="og:type" content="<?= e($s->type) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">
<?php foreach ($s->jsonLd as $schema): ?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
