<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class SeoController extends Controller
{
    /** Dynamic XML sitemap: home, categories, products (with image), CMS pages. */
    public function sitemap(Request $request): Response
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
        $add = function (string $loc, ?string $lastmod, string $freq, string $prio, ?string $image = null) use (&$x) {
            $x .= '<url><loc>' . e($loc) . '</loc>'
                . ($lastmod ? '<lastmod>' . date('Y-m-d', strtotime($lastmod)) . '</lastmod>' : '')
                . "<changefreq>$freq</changefreq><priority>$prio</priority>"
                . ($image ? '<image:image><image:loc>' . e($image) . '</image:loc></image:image>' : '')
                . '</url>';
        };

        $add(url('/'), date('Y-m-d'), 'daily', '1.0');
        $add(url('/shop'), date('Y-m-d'), 'daily', '0.9');
        foreach (db()->select('SELECT slug, updated_at FROM categories WHERE is_active = 1') as $c) {
            $add(url('/c/' . $c['slug']), $c['updated_at'], 'daily', '0.8');
        }
        $products = db()->select(
            'SELECT p.slug, p.updated_at, (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image
               FROM products p WHERE p.is_active = 1 AND p.deleted_at IS NULL ORDER BY p.id DESC LIMIT 45000'
        );
        foreach ($products as $p) {
            $add(url('/p/' . $p['slug']), $p['updated_at'], 'weekly', '0.7', $p['image'] ? url(upload_url($p['image'])) : null);
        }
        foreach (db()->select('SELECT slug, updated_at FROM pages WHERE is_active = 1') as $pg) {
            $add(url('/page/' . $pg['slug']), $pg['updated_at'], 'monthly', '0.3');
        }
        $x .= '</urlset>';

        return (new Response($x, 200, ['Content-Type' => 'application/xml; charset=utf-8']))->header('Cache-Control', 'public, max-age=3600');
    }

    public function robots(Request $request): Response
    {
        $isProd = config('app.env') === 'production';
        $body = $isProd
            ? "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /account\nDisallow: /cart\nDisallow: /checkout\nDisallow: /search\nDisallow: /api/\nDisallow: /*?*sort=\n\nSitemap: " . url('/sitemap.xml') . "\n"
            : "User-agent: *\nDisallow: /\n"; // never let staging get indexed
        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
