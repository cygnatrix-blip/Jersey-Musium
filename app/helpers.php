<?php

declare(strict_types=1);

use App\Core\AdminAuth;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Seo;
use App\Core\Session;
use App\Core\View;
use App\Services\SettingService;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/** HTML-escape any scalar for safe output. Use for EVERY dynamic value in views. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute URL (used for canonical, sitemap, gateway callbacks, emails). */
function url(string $path = '', array $query = []): string
{
    $u = config('app.url') . '/' . ltrim($path, '/');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

/** Versioned static asset URL for long-term browser caching. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function upload_url(?string $path, string $fallback = 'img/placeholder.svg'): string
{
    if (!$path) {
        return asset($fallback);
    }
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    return config('app.uploads.url') . '/' . ltrim($path, '/');
}

function view(string $template, array $data = [], ?string $layout = 'layouts/store', int $status = 200): Response
{
    return new Response(View::render($template, $data, $layout), $status);
}

function redirect(string $to, int $status = 302): Response
{
    return Response::redirect($to, $status);
}

/** Redirect to the previous page (same-origin only, to avoid open redirects). */
function back(string $fallback = '/'): Response
{
    // 1) Referer, if it points to this site (compare host AND port: "localhost:8000" ≠ "localhost")
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '' && ($p = parse_url($ref)) && !empty($p['host'])) {
        $refHost = strtolower($p['host'] . (isset($p['port']) ? ':' . $p['port'] : ''));
        $app = parse_url((string) config('app.url'));
        $ours = array_filter([
            strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')),
            strtolower(($app['host'] ?? '') . (isset($app['port']) ? ':' . $app['port'] : '')),
        ]);
        if (in_array($refHost, $ours, true)) {
            return redirect(($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : ''));
        }
    }
    // 2) Last page this visitor viewed (recorded per session) – works even when browsers omit the Referer
    $prev = (string) session()->get('_previous_url', '');
    if (str_starts_with($prev, '/') && !str_starts_with($prev, '//')) {
        return redirect($prev);
    }
    return redirect($fallback);
}

function json(mixed $data, int $status = 200): Response
{
    return Response::json($data, $status);
}

function db(): Database
{
    return Database::instance();
}

function session(): Session
{
    return Session::instance();
}

function auth(): Auth
{
    return Auth::instance();
}

function admin_auth(): AdminAuth
{
    return AdminAuth::instance();
}

function request(): Request
{
    return Request::current();
}

function seo(): Seo
{
    return Seo::instance();
}

function logger(): Logger
{
    return Logger::instance();
}

function setting(string $key, mixed $default = null): mixed
{
    return SettingService::get($key, $default);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = session()->getFlash('_old', []);
    return $old[$key] ?? $default;
}

/** Validation error for a field (set via Response::withErrors). */
function error_for(string $key): ?string
{
    $errors = session()->getFlash('_errors', []);
    return $errors[$key][0] ?? null;
}

function flash(string $key): ?string
{
    return session()->getFlash($key);
}

function abort(int $code, string $message = ''): never
{
    throw new HttpException($code, $message);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Convert a rupee amount to integer paise (all money maths is done in paise). */
function paise(int|float|string|null $amount): int
{
    return (int) round(((float) $amount) * 100);
}

function rupees(int $paise): string
{
    return number_format($paise / 100, 2, '.', '');
}

/** Indian-numbering currency format: 1234567.5 => ₹12,34,567.50 */
function money(int|float|string|null $amount, bool $decimals = false): string
{
    $p = paise($amount);
    $neg = $p < 0;
    $p = abs($p);
    if ($decimals) {
        $int = (string) intdiv($p, 100);
        $frac = '.' . str_pad((string) ($p % 100), 2, '0', STR_PAD_LEFT);
    } else {
        $int = (string) (int) round($p / 100);
        $frac = '';
    }
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    return ($neg ? '-' : '') . '₹' . $int . $frac;
}

function discount_percent(int|float|string $mrp, int|float|string $price): int
{
    $mrp = (float) $mrp;
    return $mrp > 0 && $mrp > (float) $price ? (int) floor((($mrp - (float) $price) / $mrp) * 100) : 0;
}

function slugify(string $text): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    // Some iconv builds (e.g. Windows) transliterate "é" as "'e" – drop leftover accent marks first.
    $ascii = preg_replace('/[\'"`^~]/', '', $ascii);
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $ascii), '-'));
    return $text !== '' ? $text : bin2hex(random_bytes(4));
}

function str_limit(?string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

function is_path(string $prefix): bool
{
    $path = request()->path();
    return $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '—';
}

/** Human label for snake_case statuses: out_for_delivery => Out for delivery */
function label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

function partial(string $template, array $data = []): string
{
    return \App\Core\View::partial($template, $data);
}

/** Nonce for the few inline <script> tags we allow under CSP. */
function csp_nonce(): string
{
    return \App\Middleware\SecurityHeaders::nonce();
}

/** Inline SVG icon (stroke style, 24px grid). Decorative by default (aria-hidden). */
function icon(string $name, int $size = 20, string $class = ''): string
{
    static $paths = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
        'bag'      => '<path d="M6 7h12l1 14H5L6 7z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'    => '<path d="M18 6 6 18M6 6l12 12"/>',
        'home'     => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'star'     => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" fill="currentColor" stroke="none"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'truck'    => '<path d="M1 6h13v10H1zM14 10h4l3 3v3h-7z"/><circle cx="5.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/>',
        'return'   => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'tag'      => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'left'     => '<path d="m15 18-6-6 6-6"/>',
        'right'    => '<path d="m9 18 6-6-6-6"/>',
        'filter'   => '<path d="M3 5h18M6 12h12M10 19h4"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/>',
        'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'package'  => '<path d="m12 3 9 5v8l-9 5-9-5V8z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'pin'      => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'shirt'    => '<path d="M8 3h8l5 4-3 4-2-1v11H8V10l-2 1-3-4z"/>',
        'cash'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'instagram'=> '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".5" fill="currentColor"/>',
        'facebook' => '<path d="M15 3h-3a4 4 0 0 0-4 4v3H5v4h3v7h4v-7h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'x'        => '<path d="M4 4l16 16M20 4 4 20"/>',
        'youtube'  => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/**
 * DOM-based allow-list sanitiser for admin-authored CMS HTML.
 * Only listed tags survive (others are unwrapped, keeping their text); only listed attributes survive;
 * links must be http(s), mailto, tel, relative or #anchors (entity-encoded "javascript:" is decoded by
 * the parser first, so it cannot sneak through). CSP is the second line of defence.
 */
function safe_html(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    $allowed = ['p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'strong' => [], 'b' => [],
                'em' => [], 'i' => [], 'a' => ['href', 'title'], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [],
                'td' => [], 'blockquote' => [], 'hr' => []];
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) {
        return e(strip_tags($html));
    }

    $clean = function (DOMNode $node) use (&$clean, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form'], true)) {
                $node->removeChild($child);            // drop dangerous elements with their content
                continue;
            }
            $clean($child);
            if (!isset($allowed[$tag])) {              // unwrap unknown tags, keep their (cleaned) children
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                if (!in_array(strtolower($attr->name), $allowed[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $href = trim(preg_replace('/[\x00-\x20]+/', '', $child->getAttribute('href')));
                if (!preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $href)) {
                    $child->removeAttribute('href');
                } elseif (preg_match('#^https?://#i', $href)) {
                    $child->setAttribute('rel', 'noopener nofollow');
                }
            }
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return $out;
}
