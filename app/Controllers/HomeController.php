<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        seo()->title((string) setting('home_meta_title'))
            ->description((string) setting('home_meta_description'))
            ->canonical('/')
            ->image(asset('img/og-default.jpg'))
            ->jsonLd([
                '@type' => 'Organization',
                'name'  => setting('store_name'),
                'url'   => url('/'),
                'logo'  => url('/assets/img/logo-mark.svg'),
                'sameAs' => array_values(array_filter([setting('social_instagram'), setting('social_facebook'), setting('social_x'), setting('social_youtube')])),
                'contactPoint' => ['@type' => 'ContactPoint', 'telephone' => setting('store_phone'), 'contactType' => 'customer service', 'areaServed' => 'IN'],
            ])
            ->jsonLd([
                '@type' => 'WebSite',
                'name'  => setting('store_name'),
                'url'   => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/search') . '?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ]);

        $topCategories = array_values(array_filter(CatalogService::categories(), fn ($c) => !$c['parent_id']));
        $teams = db()->select(
            "SELECT team, COUNT(*) AS cnt FROM products WHERE is_active = 1 AND deleted_at IS NULL AND team IS NOT NULL AND team <> ''
              GROUP BY team ORDER BY cnt DESC LIMIT 12"
        );

        $title = setting('store_name') . ' – ' . setting('home_meta_title');
        return view('store/home', [
            'h1'          => $title,
            'hero'        => CatalogService::banners('home_hero'),
            'strip'       => CatalogService::banners('home_strip'),
            'grid'        => CatalogService::banners('home_grid'),
            'categories'  => $topCategories,
            'teams'       => $teams,
            'featured'    => CatalogService::rail('featured', 12),
            'newArrivals' => CatalogService::rail('new', 12),
            'deals'       => CatalogService::rail('deals', 12),
            'bestsellers' => CatalogService::rail('bestseller', 12),
            'isHome'      => true,
        ]);
    }
}
