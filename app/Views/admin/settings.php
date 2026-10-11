<?php
/** @var array $s @var array $gateways @var array $webhooks */
$f = fn (string $name, string $label, string $type = 'text', array $extra = []) => partial('admin/partials/f', ['name' => $name, 'label' => $label, 'type' => $type, 'value' => $s[$name] ?? ''] + $extra);
?>
<div class="page-head"><div><h1>Settings</h1><p>Store-wide configuration. API keys and secrets live in the server's <code>.env</code> file, never here.</p></div></div>
<form method="post" action="/admin/settings">
    <?= csrf_field() ?>
    <div class="cols cols--1-1">
        <section class="card">
            <h2>Store</h2>
            <div class="form-grid">
                <?= $f('store_name', 'Store name') ?>
                <?= $f('store_tagline', 'Tagline') ?>
                <?= $f('store_email', 'Support email', 'email') ?>
                <?= $f('store_phone', 'Support phone') ?>
                <?= $f('store_gstin', 'GSTIN') ?>
                <?= $f('store_state', 'Registered state (for CGST/SGST vs IGST)') ?>
            </div>
            <?= $f('store_address', 'Business address', 'textarea', ['attrs' => 'style="min-height:70px"']) ?>
            <?= $f('announcement', 'Announcement bar text', 'text', ['hint' => 'Leave empty to hide']) ?>
        </section>

        <section class="card">
            <h2>Payments</h2>
            <p class="mb-2">
                Razorpay keys: <?= $gateways['razorpay'] ? '<span class="badge badge--success">Configured</span>' : '<span class="badge badge--danger">Missing in .env</span>' ?>
                &nbsp; Cashfree keys: <?= $gateways['cashfree'] ? '<span class="badge badge--success">Configured</span>' : '<span class="badge badge--danger">Missing in .env</span>' ?>
            </p>
            <?= $f('razorpay_enabled', 'Enable Razorpay at checkout', 'checkbox') ?><br>
            <?= $f('cashfree_enabled', 'Enable Cashfree at checkout', 'checkbox') ?><br>
            <?= $f('cod_enabled', 'Enable Cash on Delivery', 'checkbox') ?>
            <div class="form-grid mt-1">
                <?= $f('cod_fee', 'COD fee (₹)', 'number') ?>
                <?= $f('cod_max_amount', 'COD max order value (₹)', 'number') ?>
                <?= $f('pending_payment_expiry_minutes', 'Unpaid order expiry (minutes)', 'number', ['hint' => 'Reserved stock is released after this']) ?>
            </div>
            <p class="muted" style="font-size:12px">Webhook URLs to paste in the gateway dashboards:<br>
                Razorpay → <code><?= e($webhooks['razorpay']) ?></code> (events: payment.captured, payment.failed, refund.processed, refund.failed)<br>
                Cashfree → <code><?= e($webhooks['cashfree']) ?></code> (payment success/failed/dropped, refund status)</p>
        </section>

        <section class="card">
            <h2>Shipping &amp; returns</h2>
            <div class="form-grid">
                <?= $f('free_shipping_threshold', 'Free shipping above (₹)', 'number') ?>
                <?= $f('shipping_fee', 'Shipping fee (₹)', 'number') ?>
                <?= $f('delivery_days_min', 'Delivery days – metro', 'number') ?>
                <?= $f('delivery_days_max', 'Delivery days – rest of India', 'number') ?>
                <?= $f('return_window_days', 'Return window (days)', 'number') ?>
                <?= $f('low_stock_threshold', 'Low stock alert at', 'number') ?>
            </div>
        </section>

        <section class="card">
            <h2>Experience &amp; SEO</h2>
            <?= $f('loader_enabled', 'Show "kick-off" intro animation on the homepage', 'checkbox') ?>
            <?= $f('loader_frequency', 'Intro frequency', 'select', ['options' => ['session' => 'Once per visit (recommended)', 'always' => 'Every homepage load']]) ?>
            <?= $f('home_meta_title', 'Homepage title') ?>
            <?= $f('home_meta_description', 'Homepage meta description', 'textarea', ['attrs' => 'maxlength="300" style="min-height:70px"']) ?>
            <div class="form-grid">
                <?= $f('google_site_verification', 'Google Search Console verification code') ?>
                <?= $f('ga4_measurement_id', 'GA4 measurement ID', 'text', ['hint' => 'G-XXXXXXX']) ?>
            </div>
        </section>

        <section class="card">
            <h2>Social links</h2>
            <div class="form-grid">
                <?= $f('social_instagram', 'Instagram URL', 'url') ?>
                <?= $f('social_facebook', 'Facebook URL', 'url') ?>
                <?= $f('social_x', 'X / Twitter URL', 'url') ?>
                <?= $f('social_youtube', 'YouTube URL', 'url') ?>
            </div>
        </section>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Save settings</button></div>
</form>
