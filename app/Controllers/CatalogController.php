<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;

final class CatalogController extends Controller
{
    public function shop(Request $request): Response
    {
        seo()->title('Shop All Football Jerseys')
            ->description('Browse every club, national team and retro football jersey at ' . setting('store_name') . '. Filter by team, league, size and price.');
        return $this->listing($request, null, 'All Jerseys', '/shop');
    }

    public function category(Request $request, string $slug): Response
    {
        $category = CatalogService::categoryBySlug($slug) ?? throw new HttpException(404);
        seo()->title($category['meta_title'] ?: $category['name'] . ' Jerseys')
            ->description($category['meta_description'] ?: ($category['description'] ?: 'Shop ' . $category['name'] . ' jerseys online at ' . setting('store_name') . '.'))
            ->image($category['image'] ? upload_url($category['image']) : null);
        return $this->listing($request, $category, $category['name'], '/c/' . $category['slug']);
    }

    public function search(Request $request): Response
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 80);
        seo()->title($q !== '' ? 'Search results for "' . $q . '"' : 'Search')->noindex();
        return $this->listing($request, null, $q !== '' ? 'Results for "' . $q . '"' : 'Search', '/search', $q);
    }

    public function suggest(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return json(['products' => [], 'teams' => []]);
        }
        return json(CatalogService::suggest($q))->header('Cache-Control', 'public, max-age=60');
    }

    private function listing(Request $request, ?array $category, string $heading, string $path, string $q = ''): Response
    {
        $filters = [
            'category_id' => $category['id'] ?? null,
            'q'           => $q,
            'team'        => (array) $request->query('team', []),
            'league'      => (array) $request->query('league', []),
            'brand'       => (array) $request->query('brand', []),
            'kit'         => (array) $request->query('kit', []),
            'gender'      => (array) $request->query('gender', []),
            'size'        => (array) $request->query('size', []),
            'price'       => (string) $request->query('price', ''),
            'discount'    => (int) $request->query('discount', 0),
            'sort'        => array_key_exists((string) $request->query('sort'), CatalogService::SORTS) ? (string) $request->query('sort') : 'popular',
        ];
        $result = CatalogService::listing($filters, (int) config('app.per_page'));
        $paginator = $result['paginator'];

        // SEO: canonical drops filters; filtered/sorted permutations are noindex to avoid thin duplicate pages.
        $isFiltered = (bool) array_filter([$filters['team'], $filters['league'], $filters['brand'], $filters['kit'], $filters['gender'], $filters['size'], $filters['price'], $filters['discount']])
            || $request->query('sort');
        seo()->canonical($path . ($paginator->page > 1 ? '?page=' . $paginator->page : ''));
        if ($isFiltered) {
            seo()->noindex();
        }

        $crumbs = [['Home', '/']];
        if ($category) {
            foreach (CatalogService::ancestry((int) $category['id']) as $c) {
                $crumbs[] = [$c['name'], '/c/' . $c['slug']];
            }
            $crumbs[count($crumbs) - 1][1] = null;
        } else {
            $crumbs[] = [$heading, null];
        }
        seo()->breadcrumbs($crumbs);

        if ($paginator->items) {
            seo()->jsonLd([
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn ($p, $i) => [
                    '@type' => 'ListItem', 'position' => $paginator->from() + $i, 'url' => url('/p/' . $p['slug']), 'name' => $p['name'],
                ], $paginator->items, array_keys($paginator->items)),
            ]);
        }

        if ($request->wantsJson()) {
            // Infinite-scroll / AJAX filtering
            return json([
                'html'     => implode('', array_map(fn ($p) => partial('store/partials/product-card', ['p' => $p]), $paginator->items)),
                'next'     => $paginator->page < $paginator->lastPage ? $paginator->url($paginator->page + 1) : null,
                'total'    => $paginator->total,
            ]);
        }

        return view('store/listing', [
            'heading'   => $heading,
            'category'  => $category,
            'children'  => $category ? array_map(fn ($id) => CatalogService::categories()[$id], $category['children']) : [],
            'paginator' => $paginator,
            'facets'    => $result['facets'],
            'filters'   => $filters,
            'banners'   => CatalogService::banners('listing_top'),
            'path'      => $path,
        ]);
    }
}
