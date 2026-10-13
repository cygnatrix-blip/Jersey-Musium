<?php

declare(strict_types=1);

/**
 * Idempotent seeder: safe to run repeatedly.
 *   php bin/console seed            → roles, categories, CMS pages, coupons
 *   php bin/console seed --demo=1   → + demo catalog with generated SVG jersey artwork & banners
 */

/** @var array $opts @var callable $out */

// ---------------------------------------------------------------- roles
$perms = array_keys(config('permissions'));
$roles = [
    'super_admin'    => ['Super Admin', []],
    'store_manager'  => ['Store Manager', array_values(array_diff($perms, ['admins.manage', 'audit.view']))],
    'order_ops'      => ['Order Operations', ['dashboard.view', 'orders.view', 'orders.manage', 'returns.manage', 'customers.manage', 'payments.view']],
    'catalog_editor' => ['Catalog & Marketing', ['dashboard.view', 'products.manage', 'categories.manage', 'banners.manage', 'coupons.manage', 'pages.manage', 'reviews.manage']],
    'finance'        => ['Finance', ['dashboard.view', 'orders.view', 'payments.view', 'refunds.manage']],
];
foreach ($roles as $slug => [$name, $p]) {
    db()->execute('INSERT IGNORE INTO roles (name, slug, permissions) VALUES (:n, :s, :p)', ['n' => $name, 's' => $slug, 'p' => json_encode($p)]);
}
$out('  roles ✓');

// ---------------------------------------------------------------- categories
$tree = [
    ['Club Jerseys', 'club-jerseys', 'Official-style home, away and third kits from the world\'s biggest football clubs.', [
        ['Premier League', 'premier-league'], ['La Liga', 'la-liga'], ['Serie A', 'serie-a'], ['Bundesliga', 'bundesliga'],
        ['Ligue 1', 'ligue-1'], ['Indian Super League', 'indian-super-league'],
    ]],
    ['National Teams', 'national-teams', 'Represent your country – national team jerseys for every tournament.', [
        ['South America', 'south-america'], ['Europe', 'europe'], ['Asia', 'asia'], ['Africa', 'africa'],
    ]],
    ['Retro Classics', 'retro-classics', 'Iconic shirts from legendary seasons, re-made for today.', []],
    ['Kids', 'kids', 'Mini kits for young fans.', []],
    ['Training Wear', 'training-wear', 'Training tops, warm-ups and pre-match jerseys.', []],
];
$catIds = [];
foreach ($tree as $i => [$name, $slug, $desc, $children]) {
    db()->execute('INSERT IGNORE INTO categories (name, slug, description, sort_order) VALUES (:n, :s, :d, :o)', ['n' => $name, 's' => $slug, 'd' => $desc, 'o' => $i]);
    $pid = (int) db()->scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $slug]);
    $catIds[$slug] = $pid;
    foreach ($children as $j => [$cn, $cs]) {
        db()->execute('INSERT IGNORE INTO categories (parent_id, name, slug, sort_order) VALUES (:p, :n, :s, :o)', ['p' => $pid, 'n' => $cn, 's' => $cs, 'o' => $j]);
        $catIds[$cs] = (int) db()->scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $cs]);
    }
}
$out('  categories ✓');

// ---------------------------------------------------------------- CMS pages (needed for Razorpay / Cashfree activation)
$store = setting('store_name');
$email = setting('store_email');
$days = setting('return_window_days');
$pages = [
    ['About Us', 'about-us', "<p>$store is a football-first store built by fans, for fans. We curate club, national-team and retro jerseys and personalise them with your name and number.</p><h2>Our promise</h2><ul><li>Every jersey is quality-checked before dispatch</li><li>Secure payments via UPI, cards, net banking and Cash on Delivery</li><li>Easy $days-day returns and exchanges</li></ul>"],
    ['Contact Us', 'contact-us', "<p>We're here to help, 10am–7pm IST, Monday to Saturday.</p><ul><li>Email: <a href=\"mailto:$email\">$email</a></li><li>Phone: " . setting('store_phone') . '</li><li>Address: ' . setting('store_address') . '</li></ul>'],
    ['Shipping Policy', 'shipping-policy', '<p>We ship across India through trusted courier partners.</p><ul><li>Orders are dispatched within 1–2 business days.</li><li>Delivery usually takes ' . setting('delivery_days_min') . '–' . setting('delivery_days_max') . ' business days depending on your pincode.</li><li>Shipping is free on orders above ₹' . setting('free_shipping_threshold') . '; a fee of ₹' . setting('shipping_fee') . ' applies below that.</li><li>Personalised jerseys need 2–3 extra days for printing.</li><li>You will receive tracking details by email once your order ships.</li></ul>'],
    ['Cancellation & Refund Policy', 'refund-policy', "<h2>Cancellations</h2><p>You can cancel an order from <strong>My Orders</strong> until it is packed. Prepaid orders are refunded in full to the original payment method.</p><h2>Returns & exchanges</h2><p>Unused items with original tags can be returned or exchanged within $days days of delivery. Personalised (name/number) jerseys can only be exchanged for manufacturing defects.</p><h2>Refunds</h2><p>Once we receive and inspect the item, refunds are initiated within 48 hours. Online payments are refunded to the original method (5–7 business days). Cash on Delivery orders are refunded to the UPI ID you provide.</p>"],
    ['Privacy Policy', 'privacy-policy', "<p>We collect only the information needed to process your orders: name, email, phone, delivery address and order history. Payments are processed by PCI-DSS compliant gateways (Razorpay / Cashfree); we never see or store your card details.</p><h2>How we use data</h2><ul><li>To fulfil and deliver orders and send order updates</li><li>To provide customer support</li><li>To send offers – only if you subscribe, and you can unsubscribe anytime</li></ul><h2>Your rights</h2><p>You may request a copy or deletion of your data by writing to $email. This policy is aligned with the Digital Personal Data Protection Act, 2023.</p>"],
    ['Terms and Conditions', 'terms-and-conditions', "<p>By using $store you agree to these terms.</p><ul><li>Prices are in INR and include GST.</li><li>We may cancel orders affected by pricing errors or stock issues, with a full refund.</li><li>Product images are representative; minor colour variations may occur.</li><li>All disputes are subject to the jurisdiction of courts in " . setting('store_state') . ', India.</li></ul><p><em>Please have these policies reviewed by your legal advisor before launch.</em></p>'],
];
foreach ($pages as [$t, $s, $c]) {
    db()->execute('INSERT IGNORE INTO pages (title, slug, content) VALUES (:t, :s, :c)', ['t' => $t, 's' => $s, 'c' => $c]);
}
$out('  pages ✓');

// ---------------------------------------------------------------- coupons
db()->execute("INSERT IGNORE INTO coupons (code, description, type, value, max_discount, min_order_amount, per_user_limit) VALUES
    ('KICKOFF10', 'Launch offer on every jersey', 'percent', 10, 300, 999, 1),
    ('FIRSTJERSEY', 'Flat ₹200 off your first order', 'flat', 200, NULL, 1499, 1)");
db()->execute("UPDATE coupons SET first_order_only = 1 WHERE code = 'FIRSTJERSEY'");
$out('  coupons ✓');

if (empty($opts['demo'])) {
    $out('Seed complete. (Add --demo=1 for a demo catalog.)');
    return;
}

// ---------------------------------------------------------------- demo catalog with generated SVG artwork
$dir = config('app.uploads.dir') . '/demo';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/** Draws a jersey (front with crest letters, or back with name & number). */
$jersey = function (string $primary, string $secondary, string $accent, string $pattern, string $code, string $num, string $name, bool $back): string {
    $body = 'M180 120h240l46 46 104 52-44 112-74-32v352H148V298l-74 32-44-112 104-52z';
    $stripes = '';
    if ($pattern === 'stripes') {
        for ($x = 150; $x < 460; $x += 60) {
            $stripes .= "<rect x=\"$x\" y=\"110\" width=\"30\" height=\"580\" fill=\"$secondary\"/>";
        }
    } elseif ($pattern === 'hoops') {
        for ($y = 180; $y < 700; $y += 80) {
            $stripes .= "<rect x=\"20\" y=\"$y\" width=\"560\" height=\"40\" fill=\"$secondary\"/>";
        }
    } elseif ($pattern === 'sash') {
        $stripes = "<path d=\"M120 160 L190 120 L480 640 L410 690z\" fill=\"$secondary\"/>";
    } elseif ($pattern === 'half') {
        $stripes = "<rect x=\"300\" y=\"100\" width=\"300\" height=\"620\" fill=\"$secondary\"/>";
    }
    $text = $back
        ? "<text x=\"300\" y=\"300\" text-anchor=\"middle\" font-family=\"Arial Narrow,Arial,sans-serif\" font-weight=\"700\" font-size=\"44\" letter-spacing=\"4\" fill=\"$accent\">$name</text>
           <text x=\"300\" y=\"520\" text-anchor=\"middle\" font-family=\"Arial Narrow,Arial,sans-serif\" font-weight=\"700\" font-size=\"210\" fill=\"$accent\" stroke=\"rgba(0,0,0,.15)\" stroke-width=\"3\">$num</text>"
        : "<circle cx=\"380\" cy=\"270\" r=\"34\" fill=\"$accent\"/><text x=\"380\" y=\"282\" text-anchor=\"middle\" font-family=\"Arial,sans-serif\" font-weight=\"700\" font-size=\"28\" fill=\"$primary\">$code</text>
           <path d=\"M200 260h80v14h-80z\" fill=\"$accent\" opacity=\".9\"/>";
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 750">'
        . '<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f7f7f9"/><stop offset="1" stop-color="#e9ebf0"/></linearGradient>'
        . "<clipPath id=\"j\"><path d=\"$body\"/></clipPath>"
        . '<linearGradient id="sh" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#000" stop-opacity=".18"/><stop offset=".25" stop-color="#000" stop-opacity="0"/><stop offset=".75" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".18"/></linearGradient></defs>'
        . '<rect width="600" height="750" fill="url(#bg)"/><ellipse cx="300" cy="712" rx="190" ry="14" fill="#000" opacity=".08"/>'
        . "<g clip-path=\"url(#j)\"><rect width=\"600\" height=\"750\" fill=\"$primary\"/>$stripes<rect width=\"600\" height=\"750\" fill=\"url(#sh)\"/></g>"
        . ($back ? "<path d=\"M250 120h100c0 14-22 24-50 24s-50-10-50-24z\" fill=\"$accent\"/>" : "<path d=\"M246 120h108l-54 58z\" fill=\"$accent\"/>")
        . "<path d=\"M74 330l-44-112 12-6 44 112zM526 330l44-112-12-6-44 112z\" fill=\"$accent\" opacity=\".85\"/>"
        . $text . '</svg>';
};

$demo = [
    // name, team, league, cat, primary, secondary, accent, pattern, code, mrp, price, kit, featured
    ['Argentina 2026 Home Jersey', 'Argentina', 'FIFA World Cup', 'south-america', '#75AADB', '#ffffff', '#1c2230', 'stripes', 'ARG', 2499, 1499, 'home', 1],
    ['Portugal 2026 Home Jersey', 'Portugal', 'FIFA World Cup', 'europe', '#b5122b', '#0f6b3a', '#f5c518', 'none', 'POR', 2499, 1599, 'home', 1],
    ['Brazil 2026 Home Jersey', 'Brazil', 'FIFA World Cup', 'south-america', '#f7d117', '#0b8e36', '#0b3d91', 'none', 'BRA', 2499, 1499, 'home', 1],
    ['India 2026 Home Jersey', 'India', 'AFC Asian Cup', 'asia', '#1d4fbf', '#f47920', '#ffffff', 'sash', 'IND', 1999, 999, 'home', 1],
    ['Germany 2026 Away Jersey', 'Germany', 'FIFA World Cup', 'europe', '#1c1c1c', '#c8102e', '#ffce00', 'none', 'GER', 2499, 1699, 'away', 0],
    ['Morocco 2026 Home Jersey', 'Morocco', 'FIFA World Cup', 'africa', '#c1272d', '#006233', '#ffffff', 'none', 'MAR', 2299, 1399, 'home', 0],
    ['Manchester Red 2026/27 Home Jersey', 'Manchester Red', 'Premier League', 'premier-league', '#da291c', '#ffffff', '#ffe500', 'none', 'MR', 2999, 1799, 'home', 1],
    ['Merseyside Reds 2026/27 Home Jersey', 'Merseyside Reds', 'Premier League', 'premier-league', '#c8102e', '#c8102e', '#ffffff', 'none', 'MER', 2999, 1899, 'home', 0],
    ['Sky Blues 2026/27 Home Jersey', 'Sky Blues', 'Premier League', 'premier-league', '#6cabdd', '#1c2c5b', '#ffffff', 'none', 'SKY', 2999, 1799, 'home', 0],
    ['Madrid Whites 2026/27 Home Jersey', 'Madrid Whites', 'La Liga', 'la-liga', '#ffffff', '#f1f1f1', '#febe10', 'none', 'MAD', 3199, 1999, 'home', 1],
    ['Barcelona Blaugrana 2026/27 Home Jersey', 'Blaugrana', 'La Liga', 'la-liga', '#a50044', '#004d98', '#edbb00', 'stripes', 'BAR', 3199, 1999, 'home', 1],
    ['Milan Rossoneri 2026/27 Home Jersey', 'Rossoneri', 'Serie A', 'serie-a', '#fb090b', '#111111', '#ffffff', 'stripes', 'MIL', 2899, 1699, 'home', 0],
    ['Turin Bianconeri 2026/27 Home Jersey', 'Bianconeri', 'Serie A', 'serie-a', '#111111', '#ffffff', '#f5c518', 'stripes', 'TOR', 2899, 1699, 'home', 0],
    ['Munich Reds 2026/27 Home Jersey', 'Munich Reds', 'Bundesliga', 'bundesliga', '#dc052d', '#ffffff', '#0066b2', 'none', 'MUN', 2899, 1799, 'home', 0],
    ['Paris Navy 2026/27 Home Jersey', 'Paris Navy', 'Ligue 1', 'ligue-1', '#004170', '#da291c', '#ffffff', 'half', 'PAR', 2899, 1799, 'home', 0],
    ['Kolkata Mariners 2026/27 Home Jersey', 'Kolkata Mariners', 'Indian Super League', 'indian-super-league', '#00563f', '#9e1b32', '#ffffff', 'half', 'KOL', 1799, 899, 'home', 1],
    ['Kerala Tuskers 2026/27 Home Jersey', 'Kerala Tuskers', 'Indian Super League', 'indian-super-league', '#fcd116', '#0033a0', '#0033a0', 'none', 'KER', 1799, 899, 'home', 0],
    ['Bengaluru Blues 2026/27 Home Jersey', 'Bengaluru Blues', 'Indian Super League', 'indian-super-league', '#1d3f94', '#ffffff', '#ffffff', 'none', 'BLR', 1799, 849, 'home', 0],
    ['Argentina 1986 Retro Jersey', 'Argentina', 'Retro', 'retro-classics', '#75AADB', '#ffffff', '#1c2230', 'stripes', 'ARG', 2199, 1299, 'retro', 1],
    ['Brazil 1970 Retro Jersey', 'Brazil', 'Retro', 'retro-classics', '#f7d117', '#0b8e36', '#0b8e36', 'none', 'BRA', 2199, 1299, 'retro', 0],
    ['Netherlands 1988 Retro Jersey', 'Netherlands', 'Retro', 'retro-classics', '#f36c21', '#ffffff', '#ffffff', 'none', 'NED', 2199, 1399, 'retro', 0],
    ['Kids Argentina Home Kit', 'Argentina', 'FIFA World Cup', 'kids', '#75AADB', '#ffffff', '#1c2230', 'stripes', 'ARG', 1499, 899, 'home', 0],
    ['Kids Brazil Home Kit', 'Brazil', 'FIFA World Cup', 'kids', '#f7d117', '#0b8e36', '#0b3d91', 'none', 'BRA', 1499, 899, 'home', 0],
    ['Pro Training Top – Black/Red', 'Jersey Museum', 'Training', 'training-wear', '#151515', '#e11d48', '#ffffff', 'hoops', 'JM', 1299, 699, 'training', 0],
];
$names = ['MESSI' => '10', 'RONALDO' => '7', 'NEYMAR' => '10', 'CHHETRI' => '11', 'MUSIALA' => '42', 'HAKIMI' => '2'];
$n = 0;
foreach ($demo as $i => [$name, $team, $league, $cat, $pc, $sc, $ac, $pat, $code, $mrp, $price, $kit, $featured]) {
    $slug = slugify($name);
    if (db()->scalar('SELECT 1 FROM products WHERE slug = :s', ['s' => $slug])) {
        continue;
    }
    $sku = 'JM-' . strtoupper(substr(md5($slug), 0, 6));
    $isKids = $cat === 'kids';
    $pid = db()->insert('products', [
        'category_id' => $catIds[$cat], 'name' => $name, 'slug' => $slug, 'sku' => $sku, 'brand' => 'Jersey Museum',
        'team' => $team, 'league' => $league, 'season' => $kit === 'retro' ? 'Retro' : '2026/27', 'kit_type' => $kit,
        'gender' => $isKids ? 'kids' : 'unisex',
        'short_description' => "Breathable fan-edition $team jersey with moisture-wicking fabric and a relaxed match-day fit.",
        'description' => "• 100% recycled polyester, quick-dry mesh\n• Heat-applied crest and details\n• Regular fit – order your usual size\n• Machine wash cold, inside out. Do not iron on print.\n\nDemo product – replace with your real catalog in Admin → Products.",
        'mrp' => $mrp, 'price' => $price, 'tax_rate' => $price > 2500 ? 18 : 5, 'hsn_code' => '6109',
        'allow_customization' => $kit === 'training' ? 0 : 1, 'customization_price' => 249,
        'is_featured' => $featured, 'is_new_arrival' => (int) ($i % 3 === 0),
        'sold_count' => random_int(5, 400), 'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 2) . ' days')),
    ]);
    [$pn, $num] = [array_keys($names)[$i % count($names)], array_values($names)[$i % count($names)]];
    foreach ([false, true] as $k => $back) {
        $file = "$slug-" . ($back ? 'back' : 'front') . '.svg';
        file_put_contents("$dir/$file", $jersey($pc, $sc, $ac, $pat, $code, $num, $pn, $back));
        db()->insert('product_images', ['product_id' => $pid, 'path' => "demo/$file", 'alt' => $name . ($back ? ' back' : ' front'), 'sort_order' => $k]);
    }
    $sizes = $isKids ? ['XS', 'S', 'M'] : ['S', 'M', 'L', 'XL', 'XXL'];
    foreach ($sizes as $order => $size) {
        $vid = db()->insert('product_variants', ['product_id' => $pid, 'size' => $size, 'sku' => "$sku-$size", 'stock' => 0, 'sort_order' => $order]);
        App\Services\InventoryService::adjust($vid, random_int(0, 4) === 0 ? random_int(1, 3) : random_int(8, 40), 'initial', 'seed');
    }
    $n++;
}
$out("  demo products ✓ ($n new)");

// demo banners (vector art; replace with real photography in Admin → Offer banners)
$banner = function (int $w, int $h, string $c1, string $c2, string $big) {
    return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 $w $h\" preserveAspectRatio=\"xMidYMid slice\">"
        . "<defs><linearGradient id=\"g\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\"><stop offset=\"0\" stop-color=\"$c1\"/><stop offset=\"1\" stop-color=\"$c2\"/></linearGradient>"
        . "<radialGradient id=\"l\" cx=\".75\" cy=\".1\" r=\".7\"><stop offset=\"0\" stop-color=\"#fff\" stop-opacity=\".35\"/><stop offset=\"1\" stop-color=\"#fff\" stop-opacity=\"0\"/></radialGradient></defs>"
        . "<rect width=\"$w\" height=\"$h\" fill=\"url(#g)\"/><rect width=\"$w\" height=\"$h\" fill=\"url(#l)\"/>"
        . '<g opacity=".08" stroke="#fff" stroke-width="40">' . implode('', array_map(fn ($x) => "<line x1=\"$x\" y1=\"-50\" x2=\"" . ($x - 400) . "\" y2=\"" . ($h + 50) . "\"/>", range(0, $w + 400, 140))) . '</g>'
        . "<text x=\"" . ($w * .97) . "\" y=\"" . ($h * .92) . "\" text-anchor=\"end\" font-family=\"Arial Black,Arial,sans-serif\" font-style=\"italic\" font-weight=\"900\" font-size=\"" . ($h * .55) . "\" fill=\"#fff\" opacity=\".08\">$big</text></svg>";
};
$banners = [
    ['World Cup 26 kits are here', 'Back your nation. Add your name & number.', 'Shop national teams', '/c/national-teams', 'home_hero', 1600, 610, '#0b1020', '#7a0f2b', '26'],
    ['Retro week: up to 45% off', 'Legendary shirts from the greatest seasons.', 'Shop retro', '/c/retro-classics', 'home_hero', 1600, 610, '#1b2a10', '#0b1020', '86'],
    ['ISL season kits', 'Support your local heroes', null, '/c/indian-super-league', 'home_strip', 800, 450, '#0d3b66', '#0b1020', 'ISL'],
    ['Name + number', 'Personalise any jersey for ₹249', null, '/shop', 'home_strip', 800, 450, '#7a0f2b', '#e11d48', '10'],
    ['Kids kits', 'Mini fans, big dreams', null, '/c/kids', 'home_strip', 800, 450, '#0f5132', '#0b1020', 'JR'],
];
if (!db()->scalar('SELECT COUNT(*) FROM banners')) {
    foreach ($banners as $i => [$t, $st, $cta, $link, $place, $w, $h, $c1, $c2, $big]) {
        $file = "banner-$i.svg";
        file_put_contents("$dir/$file", $banner($w, $h, $c1, $c2, $big));
        db()->insert('banners', ['title' => $t, 'subtitle' => $st, 'cta_label' => $cta, 'link_url' => $link, 'image_desktop' => "demo/$file", 'placement' => $place, 'sort_order' => $i]);
    }
    $out('  demo banners ✓');
}
$out('Seed complete.');
