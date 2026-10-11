<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\CouponService;

final class ProductController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $p = CatalogService::productBySlug($slug) ?? throw new HttpException(404);

        $title = $p['meta_title'] ?: $p['name'];
        $description = $p['meta_description'] ?: ($p['short_description'] ?: 'Buy ' . $p['name'] . ' online at ' . money($p['price']) . '. ' . setting('store_tagline') . '.');
        $image = $p['images'] ? url(upload_url($p['images'][0]['path'])) : null;

        seo()->title($title)->description($description)->canonical('/p/' . $p['slug'])->image($image)->type('product');

        $crumbs = [['Home', '/']];
        foreach (CatalogService::ancestry((int) $p['category_id']) as $c) {
            $crumbs[] = [$c['name'], '/c/' . $c['slug']];
        }
        $crumbs[] = [$p['name'], null];
        seo()->breadcrumbs($crumbs);

        // Google Product rich result
        $schema = [
            '@type'       => 'Product',
            'name'        => $p['name'],
            'sku'         => $p['sku'],
            'description' => str_limit($p['description'] ?: $p['short_description'], 500),
            'image'       => array_map(fn ($i) => url(upload_url($i['path'])), $p['images']),
            'brand'       => ['@type' => 'Brand', 'name' => $p['brand'] ?: setting('store_name')],
            'offers'      => [
                '@type'         => 'Offer',
                'url'           => url('/p/' . $p['slug']),
                'priceCurrency' => 'INR',
                'price'         => number_format((float) $p['price'], 2, '.', ''),
                'availability'  => $p['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller'        => ['@type' => 'Organization', 'name' => setting('store_name')],
                'hasMerchantReturnPolicy' => [
                    '@type' => 'MerchantReturnPolicy', 'applicableCountry' => 'IN',
                    'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                    'merchantReturnDays' => (int) setting('return_window_days'),
                ],
            ],
        ];
        if ((int) $p['review_count'] > 0) {
            $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float) $p['avg_rating'], 1), 'reviewCount' => (int) $p['review_count']];
        }
        seo()->jsonLd($schema);

        $inWishlist = auth()->check() && db()->scalar('SELECT 1 FROM wishlists WHERE user_id = :u AND product_id = :p', ['u' => auth()->id(), 'p' => $p['id']]);
        $canReview = auth()->check() && !db()->scalar('SELECT 1 FROM product_reviews WHERE user_id = :u AND product_id = :p', ['u' => auth()->id(), 'p' => $p['id']]);

        return view('store/product', [
            'p'          => $p,
            'related'    => CatalogService::related($p),
            'reviews'    => CatalogService::reviews((int) $p['id']),
            'breakdown'  => CatalogService::ratingBreakdown((int) $p['id']),
            'offers'     => CouponService::publicOffers(),
            'inWishlist' => (bool) $inWishlist,
            'canReview'  => $canReview,
            'crumbs'     => $crumbs,
        ]);
    }

    public function review(Request $request, string $slug): Response
    {
        $p = CatalogService::productBySlug($slug) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'rating' => 'required|integer|between:1,5',
            'title'  => 'nullable|max:120',
            'body'   => 'nullable|max:2000',
        ]);
        $verified = (bool) db()->scalar(
            "SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.user_id = :u AND oi.product_id = :p AND o.status IN ('delivered','return_requested','returned') LIMIT 1",
            ['u' => auth()->id(), 'p' => $p['id']]
        );
        db()->execute(
            'INSERT IGNORE INTO product_reviews (product_id, user_id, rating, title, body, is_verified_purchase) VALUES (:p, :u, :r, :t, :b, :v)',
            ['p' => $p['id'], 'u' => auth()->id(), 'r' => (int) $data['rating'], 't' => $data['title'], 'b' => $data['body'], 'v' => (int) $verified]
        );
        return redirect('/p/' . $slug . '#reviews')->with('success', 'Thanks! Your review will appear after moderation.');
    }

    /** Delivery estimate for a pincode (PDP "Check delivery"). */
    public function pincode(Request $request): Response
    {
        $pin = (string) $request->query('pincode', '');
        if (!preg_match('/^[1-9]\d{5}$/', $pin)) {
            return json(['ok' => false, 'message' => 'Enter a valid 6-digit pincode'], 422);
        }
        $min = (int) setting('delivery_days_min');
        $max = (int) setting('delivery_days_max');
        // Major metro pincode prefixes (Delhi, Mumbai, Bengaluru, Chennai, Kolkata, Hyderabad, Pune, Ahmedabad) get the faster estimate.
        $metro = in_array(substr($pin, 0, 2), ['11', '40', '56', '60', '70', '50', '41', '38'], true);
        $days = $metro ? $min : $max;
        return json([
            'ok'      => true,
            'message' => 'Delivery by ' . date('D, d M', strtotime("+$days days")),
            'cod'     => setting('cod_enabled') === '1',
        ]);
    }
}
