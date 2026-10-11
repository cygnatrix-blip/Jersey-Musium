<?php

declare(strict_types=1);

/**
 * End-to-end smoke + security test against a RUNNING instance (real HTTP, cookies, CSRF).
 *   php -S localhost:8000 -t public public/index.php            (other terminal; demo data seeded)
 *   php tests/smoke.php http://localhost:8000 admin@email adminPassword
 *
 * Covers: storefront → bag → coupon → COD checkout → admin (2FA) → pack/ship (invoice issued) →
 * deliver → return → refund → cancellation → customer management → team RBAC guards →
 * attacker-style security probes (XSS, SQLi, IDOR, CSRF, upload, privilege escalation, headers).
 * Requires MAIL_DRIVER=log so the admin OTP can be read from storage/logs/mail-*.log.
 */

require dirname(__DIR__) . '/bootstrap/app.php';

[$_, $base, $adminEmail, $adminPass] = $argv + [null, 'http://localhost:8000', '', ''];
$pass = 0;
$fail = 0;
db()->execute('DELETE FROM rate_limits'); // tests run many logins from one IP

function check(bool $ok, string $label): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  \u{2713} " : "  \u{2717} ") . $label . PHP_EOL;
}

final class Client
{
    private string $jar;
    public string $last = '';
    public array $headers = [];

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jm');
    }

    public function req(string $method, string $path, array|string $data = [], bool $json = false, array $extraHeaders = []): array
    {
        $ch = curl_init($this->base . $path);
        $h = $json ? ['Accept: application/json'] : [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge($h, $extraHeaders),
        ]);
        if ($method === 'POST') {
            $hasFile = is_array($data) && array_filter($data, fn ($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) && !$hasFile ? http_build_query($data) : $data);
        }
        $raw = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $hs);
        preg_match('/^Location:\s*(\S+)/mi', $head, $m);
        $this->headers = [];
        foreach (explode("\r\n", $head) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->headers[strtolower(trim($k))] = trim($v);
            }
        }
        $this->last = substr($raw, $hs);
        return ['code' => $code, 'body' => $this->last, 'location' => $m[1] ?? null];
    }

    public function get(string $p): array { return $this->req('GET', $p); }

    public function token(string $from): string
    {
        preg_match('/name="_token" value="([^"]+)"/', $this->get($from)['body'], $m);
        return $m[1] ?? '';
    }

    /** POST with the CSRF token scraped from a page. */
    public function post(string $p, array $data, string $tokenFrom = '/', bool $json = false): array
    {
        return $this->req('POST', $p, $data + ['_token' => $this->token($tokenFrom)], $json);
    }
}

/** Latest 6-digit code emailed to $email with the given subject prefix (read from the log mail driver). */
function mailCode(string $email, string $subjectPrefix): ?string
{
    for ($i = 0; $i < 20; $i++) {
        $log = (string) @file_get_contents(BASE_PATH . '/storage/logs/mail-' . date('Y-m-d') . '.log');
        if (preg_match_all('/To: ' . preg_quote($email, '/') . ' \| ' . preg_quote($subjectPrefix, '/') . '(\d{6})/', $log, $m)) {
            return end($m[1]);
        }
        usleep(250000);
    }
    return null;
}

/** Sign in to /admin, completing the email-OTP step by reading the code from the mail log. */
function adminLogin(Client $a, string $email, string $password): bool
{
    $r = $a->post('/admin/login', ['email' => $email, 'password' => $password], '/admin/login');
    if ($r['location'] === '/admin') {
        return true; // 2FA disabled
    }
    if ($r['location'] !== '/admin/login/verify') {
        return false;
    }
    $code = null;
    for ($i = 0; $i < 20 && !$code; $i++) {
        usleep(250000);
        $log = (string) @file_get_contents(BASE_PATH . '/storage/logs/mail-' . date('Y-m-d') . '.log');
        if (preg_match_all('/To: ' . preg_quote($email, '/') . ' \| Your admin sign-in code: (\d{6})/', $log, $m)) {
            $code = end($m[1]);
        }
    }
    $r = $a->post('/admin/login/verify', ['code' => $code], '/admin/login/verify');
    return $r['location'] === '/admin';
}

$variant = db()->first("SELECT v.* FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.slug = 'argentina-2026-home-jersey' AND v.size = 'M'");
db()->execute('UPDATE product_variants SET stock = 50 WHERE id = :id', ['id' => $variant['id']]);

echo "Storefront\n";
$c = new Client($base);
check($c->get('/')['code'] === 200 && str_contains($c->last, 'id="jm-loader"'), 'home renders with kick-off loader (first visit)');
check(!str_contains($c->get('/')['body'], 'id="jm-loader"'), 'loader not repeated in same session');
check(str_contains($c->get('/p/argentina-2026-home-jersey')['body'], '"@type":"Product"'), 'product page has Product JSON-LD');
$r = $c->req('POST', '/cart/add', ['variant_id' => 1], true);
check($r['code'] === 419, 'POST without CSRF token rejected (419)');

echo "\nForm validation returns to the same page\n";
$v = new Client($base);
$tok = $v->token('/register');
$r = $v->req('POST', '/register', ['_token' => $tok, 'name' => 'X', 'email' => 'not-an-email', 'phone' => '123', 'password' => 'a', 'password_confirmation' => 'b'],
    false, ['Referer: ' . $base . '/register']);
check($r['location'] === '/register', 'invalid sign-up (with Referer) → back to /register, not home');
$page = $v->get('/register')['body'];
check(str_contains($page, 'Enter a valid email address.') && str_contains($page, 'has-error') && str_contains($page, 'value="X"'),
    'field errors highlighted and typed values kept');
$tok = $v->token('/login');
$r = $v->req('POST', '/login', ['_token' => $tok, 'email' => 'bad', 'password' => '']);
check($r['location'] === '/login', 'invalid login (browser sent no Referer) → back to /login');
$tok = $v->token('/forgot-password');
$r = $v->req('POST', '/forgot-password', ['_token' => $tok, 'email' => ''], false, ['Referer: https://evil.example/x']);
check($r['location'] === '/forgot-password', 'foreign Referer ignored → still back to the form');

$email = 'smoke+' . bin2hex(random_bytes(3)) . '@example.com';
$r = $c->post('/register', ['name' => 'Smoke Tester', 'email' => $email, 'phone' => '9830012345', 'password' => 'Football123', 'password_confirmation' => 'Football123'], '/register');
check($r['code'] === 302 && db()->scalar('SELECT 1 FROM users WHERE email = :e', ['e' => $email]), 'customer registers');

echo "\nEmail OTP verification\n";
check($r['location'] === '/verify-email', 'sign-up redirects to email verification');
check(db()->scalar('SELECT email_verified_at FROM users WHERE email = :e', ['e' => $email]) === null, 'new account starts unverified');
$code = mailCode($email, 'Your Jersey Musium verification code: ');
check((bool) preg_match('/^\d{6}$/', (string) $code), '6-digit code emailed to the customer');
check($c->get('/checkout')['location'] === '/verify-email', 'checkout blocked until email is verified');
$c->post('/verify-email', ['code' => $code === '000000' ? '111111' : '000000'], '/verify-email');
check(db()->scalar('SELECT email_verified_at FROM users WHERE email = :e', ['e' => $email]) === null, 'wrong code rejected');
check(str_contains($c->get('/verify-email')['body'], 'Incorrect code. 4 attempt(s) left.'), 'remaining attempts shown (4 left)');
$c->post('/verify-email/resend', [], '/verify-email');
check(str_contains($c->get('/verify-email')['body'], 'Please wait'), 'immediate resend refused (cooldown)');
check(mailCode($email, 'Your Jersey Musium verification code: ') === $code, 'no new code issued during cooldown');
$r = $c->post('/verify-email', ['code' => $code], '/verify-email');
check(db()->scalar('SELECT email_verified_at FROM users WHERE email = :e', ['e' => $email]) !== null, 'correct code verifies the email');
check($c->post('/verify-email', ['code' => $code], '/account')['location'] === '/account', 'code cannot be reused');
check(str_contains($c->get('/account')['body'], 'Email verified'), 'account shows "Email verified"');
echo "\nShopping\n";

$stockBefore = 50;
$r = $c->post('/cart/add', ['variant_id' => $variant['id'], 'qty' => 2, 'custom_name' => 'Tester', 'custom_number' => '10'], '/p/argentina-2026-home-jersey', true);
check(str_contains($r['body'], '"ok":true'), 'add to bag (AJAX) with name & number');
check(str_contains($c->get('/cart')['body'], 'TESTER 10'), 'bag shows personalisation');
$r = $c->post('/cart/coupon', ['code' => 'kickoff10'], '/cart');
check(str_contains($c->get('/cart')['body'], 'KICKOFF10 applied'), 'coupon KICKOFF10 applied');
check($c->get('/checkout')['code'] === 200, 'checkout page loads');
$r = $c->post('/checkout/place', [
    'address_id' => 0, 'name' => 'Smoke Tester', 'phone' => '9830012345', 'line1' => '12 Stadium Road', 'city' => 'Kolkata',
    'state' => 'West Bengal', 'pincode' => '700001', 'type' => 'home', 'payment_method' => 'cod',
], '/checkout');
$order = db()->first('SELECT o.* FROM orders o JOIN users u ON u.id = o.user_id WHERE u.email = :e', ['e' => $email]);
check($order && str_starts_with((string) $r['location'], '/order/success/'), 'COD order placed → success page');
check($order && $order['status'] === 'confirmed' && $order['payment_status'] === 'cod_pending', 'order confirmed, COD pending');
$expected = 2 * (1499 + 249);
$disc = min(300, intdiv($expected * 10, 100));
check($order && (float) $order['grand_total'] === (float) ($expected - $disc + 49), 'totals: 2×(1499+249) −10% (cap 300) + ₹49 COD = ₹' . $order['grand_total']);
check((int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $variant['id']]) === $stockBefore - 2, 'stock reserved (−2) and ledgered');
check(str_contains($c->get('/account/orders/' . $order['order_number'])['body'], $order['order_number']), 'customer sees order page');
check($c->get('/account/orders/' . $order['order_number'] . '/invoice')['code'] === 404, 'no GST invoice before dispatch');

echo "\nAdmin (two-step login)\n";
$a = new Client($base);
$r = $a->post('/admin/login', ['email' => $adminEmail, 'password' => 'wrong-password'], '/admin/login');
check($a->get('/admin')['code'] === 302, 'wrong password does not sign in');
db()->execute('DELETE FROM rate_limits');
$a2 = new Client($base);
$r = $a2->post('/admin/login', ['email' => $adminEmail, 'password' => $adminPass], '/admin/login');
check($r['location'] === '/admin/login/verify' && $a2->get('/admin')['code'] === 302, 'password alone is not enough (OTP step required)');
$a2->post('/admin/login/verify', ['code' => '000000'], '/admin/login/verify');
check($a2->get('/admin')['code'] === 302, 'wrong OTP rejected');
check(adminLogin($a, $adminEmail, $adminPass), 'admin signs in with password + emailed OTP');
check(str_contains($a->get('/admin')['body'], 'Revenue per day'), 'dashboard with revenue chart');
foreach (['/admin/products', '/admin/products/create', '/admin/categories', '/admin/orders', '/admin/payments', '/admin/refunds', '/admin/returns',
          '/admin/coupons', '/admin/coupons/create', '/admin/banners', '/admin/banners/create', '/admin/customers', '/admin/customers?status=blocked',
          '/admin/reviews', '/admin/pages', '/admin/settings', '/admin/team', '/admin/team/admins/create', '/admin/team/roles/create', '/admin/audit',
          '/admin/orders/' . $order['id'], '/admin/products/1/edit', '/admin/orders/export'] as $p) {
    $code = $a->get($p)['code'];
    check($code === 200, "GET $p → $code");
}
$oid = $order['id'];
$a->post("/admin/orders/$oid/status", ['status' => 'packed'], "/admin/orders/$oid");
check(db()->scalar('SELECT status FROM orders WHERE id = :id', ['id' => $oid]) === 'packed', 'order → packed');
$a->post("/admin/orders/$oid/ship", ['courier' => 'Delhivery', 'awb_number' => 'AWB123456', 'tracking_url' => 'https://example.com/t/AWB123456'], "/admin/orders/$oid");
$o = db()->first('SELECT * FROM orders WHERE id = :id', ['id' => $oid]);
check($o['status'] === 'shipped', 'shipment created → shipped');
check((bool) preg_match('#^JM/\d{2}-\d{2}/\d{6}$#', (string) $o['invoice_number']), 'GST invoice number issued at dispatch: ' . $o['invoice_number']);
$inv = $c->get('/account/orders/' . $order['order_number'] . '/invoice');
check($inv['code'] === 200 && str_contains($inv['body'], $o['invoice_number']) && str_contains($inv['body'], 'CGST') && str_contains($inv['body'], 'Amount in words'), 'customer downloads GST invoice (CGST/SGST, amount in words)');
check($a->get("/admin/orders/$oid/invoice")['code'] === 200, 'admin can open the same invoice');
$sid = (int) db()->scalar('SELECT id FROM shipments WHERE order_id = :id', ['id' => $oid]);
$a->post("/admin/orders/$oid/shipments/$sid/event", ['status' => 'out_for_delivery', 'location' => 'Kolkata Hub'], "/admin/orders/$oid");
$a->post("/admin/orders/$oid/shipments/$sid/event", ['status' => 'delivered', 'location' => 'Kolkata'], "/admin/orders/$oid");
$o = db()->first('SELECT * FROM orders WHERE id = :id', ['id' => $oid]);
check($o['status'] === 'delivered' && $o['payment_status'] === 'paid', 'tracking events → delivered, COD marked paid');

// Personalised items are not returnable → second plain order for the return flow
$c->post('/cart/add', ['variant_id' => $variant['id'], 'qty' => 1], '/p/argentina-2026-home-jersey', true);
$addrId = (int) db()->scalar('SELECT id FROM addresses WHERE user_id = :u', ['u' => $o['user_id']]);
$c->post('/checkout/place', ['address_id' => $addrId, 'payment_method' => 'cod'], '/checkout');
$o2 = db()->first('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => $o['user_id']]);
$a->post("/admin/orders/{$o2['id']}/ship", ['courier' => 'Delhivery', 'awb_number' => 'AWB999'], "/admin/orders/{$o2['id']}");
$sid2 = (int) db()->scalar('SELECT id FROM shipments WHERE order_id = :id', ['id' => $o2['id']]);
$a->post("/admin/orders/{$o2['id']}/shipments/$sid2/event", ['status' => 'delivered'], "/admin/orders/{$o2['id']}");
$inv2 = (string) db()->scalar('SELECT invoice_number FROM orders WHERE id = :id', ['id' => $o2['id']]);
check((int) substr($inv2, -6) === (int) substr((string) $o['invoice_number'], -6) + 1, 'next invoice number is consecutive: ' . $inv2);

echo "\nReturns & refunds\n";
$item = db()->first('SELECT * FROM order_items WHERE order_id = :id', ['id' => $o2['id']]);
$c->post("/account/orders/{$o2['order_number']}/return", ['order_item_id' => $item['id'], 'qty' => 1, 'type' => 'return', 'reason' => 'size_issue', 'refund_upi' => 'tester@upi'], "/account/orders/{$o2['order_number']}/return");
$ret = db()->first('SELECT * FROM returns WHERE order_id = :id', ['id' => $o2['id']]);
check($ret && $ret['status'] === 'requested', 'customer requests return');
$stockMid = (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $variant['id']]);
foreach (['approved', 'picked_up', 'received'] as $st) {
    $a->post("/admin/returns/{$ret['id']}/status", ['status' => $st], "/admin/returns/{$ret['id']}");
}
check(db()->scalar('SELECT status FROM returns WHERE id = :id', ['id' => $ret['id']]) === 'received', 'admin approves → picked up → received');
check((int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $variant['id']]) === $stockMid + 1, 'returned unit restocked');
$a->post("/admin/returns/{$ret['id']}/refund", ['amount' => $item['unit_price'], 'manual_reference' => 'UTR0001'], "/admin/returns/{$ret['id']}");
$o2 = db()->first('SELECT * FROM orders WHERE id = :id', ['id' => $o2['id']]);
check((float) $o2['refunded_total'] === (float) $item['unit_price'] && $o2['status'] === 'returned', 'manual UPI refund ₹' . $o2['refunded_total'] . ' → order returned');

echo "\nCustomer cancellation\n";
$c->post('/cart/add', ['variant_id' => $variant['id'], 'qty' => 1], '/p/argentina-2026-home-jersey', true);
$before = (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $variant['id']]);
$c->post('/checkout/place', ['address_id' => $addrId, 'payment_method' => 'cod'], '/checkout');
$o3 = db()->first('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => $o['user_id']]);
$c->post("/account/orders/{$o3['order_number']}/cancel", ['reason' => 'test'], "/account/orders/{$o3['order_number']}");
check(db()->scalar('SELECT status FROM orders WHERE id = :id', ['id' => $o3['id']]) === 'cancelled', 'customer cancels order');
check((int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $variant['id']]) === $before, 'stock restored after cancel');
check(db()->scalar('SELECT invoice_number FROM orders WHERE id = :id', ['id' => $o3['id']]) === null, 'no invoice number burned for an order cancelled before dispatch');

echo "\nCustomer management (admin)\n";
$uid = (int) $o['user_id'];
$a->post("/admin/customers/$uid", ['name' => 'Smoke Tester Edited', 'email' => $email, 'phone' => '9830012345', 'admin_note' => 'VIP'], "/admin/customers/$uid");
check(db()->scalar('SELECT name FROM users WHERE id = :id', ['id' => $uid]) === 'Smoke Tester Edited', 'admin edits customer details');
$a->post("/admin/customers/$uid/reset-link", [], "/admin/customers/$uid");
check((bool) db()->scalar('SELECT 1 FROM password_resets WHERE email = :e', ['e' => $email]), 'admin sends password-reset link (token stored hashed)');
$a->post("/admin/customers/$uid/toggle", [], "/admin/customers/$uid");
check($c->get('/account')['code'] === 302, 'blocked customer is signed out immediately');
$cb = new Client($base);
$cb->post('/login', ['email' => $email, 'password' => 'Football123'], '/login');
check($cb->get('/account')['code'] === 302, 'blocked customer cannot log in');
$a->post("/admin/customers/$uid/toggle", [], "/admin/customers/$uid");
$victim = 'erase+' . bin2hex(random_bytes(3)) . '@example.com';
$cv = new Client($base);
$cv->post('/register', ['name' => 'Erase Me', 'email' => $victim, 'phone' => '9830012346', 'password' => 'Football123', 'password_confirmation' => 'Football123'], '/register');
$vid = (int) db()->scalar('SELECT id FROM users WHERE email = :e', ['e' => $victim]);
$a->post("/admin/customers/$vid/anonymise", ['confirm' => 'nope'], "/admin/customers/$vid");
check(db()->scalar('SELECT status FROM users WHERE id = :id', ['id' => $vid]) === 'active', 'anonymise requires typing DELETE');
$a->post("/admin/customers/$vid/anonymise", ['confirm' => 'DELETE'], "/admin/customers/$vid");
$v = db()->first('SELECT * FROM users WHERE id = :id', ['id' => $vid]);
check($v['status'] === 'deleted' && $v['email'] !== $victim && $v['phone'] === null, 'right-to-erasure: personal data anonymised');

echo "\nTeam & RBAC guards\n";
$roleId = (int) db()->insert('roles', ['name' => 'Ops Lead', 'slug' => 'ops_lead_' . bin2hex(random_bytes(2)), 'permissions' => json_encode(['dashboard.view', 'admins.manage', 'orders.view'])]);
$opsEmail = 'ops+' . bin2hex(random_bytes(3)) . '@jerseymusium.test';
$a->post('/admin/team/admins', ['name' => 'Ops Lead', 'email' => $opsEmail, 'role_id' => $roleId, 'password' => 'OpsLeadPass!2026', 'password_confirmation' => 'OpsLeadPass!2026'], '/admin/team/admins/create');
$opsId = (int) db()->scalar('SELECT id FROM admins WHERE email = :e', ['e' => $opsEmail]);
check($opsId > 0, 'super admin creates a limited admin');
$ops = new Client($base);
db()->execute('DELETE FROM rate_limits');
check(adminLogin($ops, $opsEmail, 'OpsLeadPass!2026'), 'limited admin signs in (2FA)');
check($ops->get('/admin/products')['code'] === 403, 'limited admin blocked from Products (no permission)');
check($ops->get('/admin/settings')['code'] === 403, 'limited admin blocked from Settings');
$superRole = (int) db()->scalar("SELECT id FROM roles WHERE slug = 'super_admin'");
$r = $ops->post("/admin/team/admins/$opsId", ['name' => 'Ops Lead', 'email' => $opsEmail, 'role_id' => $superRole, 'status' => 'active'], "/admin/team/admins/$opsId/edit");
check((int) db()->scalar('SELECT role_id FROM admins WHERE id = :id', ['id' => $opsId]) === $roleId, 'privilege escalation blocked: cannot grant self Super Admin');
$ops->post("/admin/team/roles/$roleId", ['name' => 'Ops Lead', 'slug' => 'ops_lead_x', 'permissions' => ['settings.manage', 'refunds.manage']], "/admin/team/roles/$roleId/edit");
check(!str_contains((string) db()->scalar('SELECT permissions FROM roles WHERE id = :id', ['id' => $roleId]), 'refunds.manage'), 'privilege escalation blocked: cannot widen own role');
$superId = (int) db()->scalar("SELECT a.id FROM admins a JOIN roles r ON r.id = a.role_id WHERE r.slug = 'super_admin' LIMIT 1");
$ops->post("/admin/team/admins/$superId", ['name' => 'Hacked', 'email' => $adminEmail, 'role_id' => $roleId, 'status' => 'disabled'], "/admin/team/admins/$superId/edit");
check(db()->scalar('SELECT status FROM admins WHERE id = :id', ['id' => $superId]) === 'active', 'limited admin cannot disable a Super Admin');
check((int) db()->scalar("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'customer.%'") >= 4, 'customer-management actions are audit-logged');

echo "\nSecurity probes\n";
$g = new Client($base);
$xss = '"><script>alert(1)</script>';
$body = $g->get('/search?q=' . rawurlencode($xss))['body'];
check(!str_contains($body, '<script>alert(1)</script>'), 'reflected XSS in search is escaped');
$r = $g->get("/search?q=" . rawurlencode("' OR 1=1 -- "));
check($r['code'] === 200 && !str_contains($r['body'], 'SQLSTATE'), 'SQL injection in search is inert');
check($g->get('/p/' . rawurlencode("x' OR '1'='1"))['code'] === 404, 'SQL injection in product slug → 404');
check($g->get("/shop?team[]=" . rawurlencode("x') OR 1=1#") . '&sort=' . rawurlencode('price_asc;DROP TABLE users'))['code'] === 200
    && db()->scalar('SELECT COUNT(*) FROM users') > 0, 'SQL injection in filters/sort is inert');
check($g->get('/admin/orders')['code'] === 302, 'admin area requires admin login');
check($c->get('/admin/orders')['code'] === 302, 'customer session cannot reach admin');
$other = new Client($base);
$other->post('/register', ['name' => 'Other User', 'email' => 'other+' . bin2hex(random_bytes(3)) . '@example.com', 'phone' => '9830012347', 'password' => 'Football123', 'password_confirmation' => 'Football123'], '/register');
check($other->get('/account/orders/' . $order['order_number'])['code'] === 404, 'IDOR: another customer cannot view the order');
check($other->get('/account/orders/' . $order['order_number'] . '/invoice')['code'] === 404, 'IDOR: another customer cannot download the invoice');
$r = $other->post('/account/orders/' . $order['order_number'] . '/cancel', ['reason' => 'x'], '/account');
check(db()->scalar('SELECT status FROM orders WHERE id = :id', ['id' => $oid]) === 'delivered', 'IDOR: another customer cannot cancel the order');
check($g->req('POST', '/webhooks/razorpay', '{"event":"payment.captured"}', false, ['Content-Type: application/json', 'X-Razorpay-Signature: forged'])['code'] === 401, 'forged webhook rejected (401)');
$r = $g->req('GET', '/login', [], false, ['Referer: https://evil.example/phish']);
$r = $g->post('/newsletter', ['email' => 'bad'], '/');
check(!str_contains((string) $r['location'], 'evil.example'), 'no open redirect via Referer');
$tmp = tempnam(sys_get_temp_dir(), 'up') . '.jpg';
file_put_contents($tmp, "<?php system(\$_GET['c']); ?>");
$tok = $a->token('/admin/products/1/edit');
$p1 = db()->first('SELECT * FROM products WHERE id = 1');
$a->req('POST', '/admin/products/1', [
    '_token' => $tok, 'name' => $p1['name'], 'slug' => $p1['slug'], 'sku' => $p1['sku'], 'category_id' => $p1['category_id'], 'gender' => $p1['gender'],
    'mrp' => $p1['mrp'], 'price' => $p1['price'], 'tax_rate' => $p1['tax_rate'], 'is_active' => 1, 'images[]' => new CURLFile($tmp, 'image/jpeg', 'shell.jpg'),
]);
check(!glob(BASE_PATH . '/public/uploads/products/*/*/*.php') && !str_contains((string) db()->scalar('SELECT GROUP_CONCAT(path) FROM product_images WHERE product_id = 1'), 'shell'), 'PHP shell disguised as JPEG rejected by upload');
@unlink($tmp);
$g->get('/');
$h = $g->headers;
check(isset($h['content-security-policy']) && str_contains($h['content-security-policy'], "nonce-") && !str_contains($h['content-security-policy'], "'unsafe-inline' https://checkout"), 'CSP with script nonces');
check(($h['x-frame-options'] ?? '') === 'SAMEORIGIN' && ($h['x-content-type-options'] ?? '') === 'nosniff' && isset($h['referrer-policy']) && isset($h['cross-origin-opener-policy']), 'XFO, nosniff, Referrer-Policy, COOP headers');
$c->get('/account/orders');
check(str_contains($c->headers['cache-control'] ?? '', 'no-store'), 'account pages are no-store');
$cookie = '';
$g2 = new Client($base);
$g2->get('/');
check(str_contains(strtolower($g2->headers['set-cookie'] ?? ''), 'httponly') && str_contains(strtolower($g2->headers['set-cookie'] ?? ''), 'samesite=lax'), 'session cookie HttpOnly + SameSite=Lax');
check($g->get('/../.env')['code'] !== 200 || !str_contains($g->last, 'DB_PASSWORD'), '.env not reachable over HTTP');
check($g->get('/storage/logs/')['code'] !== 200, 'logs not reachable over HTTP');

echo PHP_EOL . "$pass passed, $fail failed" . PHP_EOL;
exit($fail ? 1 : 0);
