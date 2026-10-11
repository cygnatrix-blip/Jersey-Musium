<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Paginator;

/** Read-side catalog queries for the storefront (listing, filters, search, PDP). */
final class CatalogService
{
    public const SORTS = [
        'popular'    => 'Popularity',
        'new'        => 'What\'s New',
        'price_asc'  => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'discount'   => 'Better Discount',
        'rating'     => 'Customer Rating',
    ];

    public const PRICE_BUCKETS = [
        '0-999'     => 'Under ₹999',
        '1000-1999' => '₹1,000 – ₹1,999',
        '2000-3999' => '₹2,000 – ₹3,999',
        '4000-'     => '₹4,000 & above',
    ];

    private static ?array $categories = null;

    /** Columns needed to render a product card. */
    public static function cardSelect(): string
    {
        return "p.id, p.name, p.slug, p.brand, p.team, p.mrp, p.price, p.avg_rating, p.review_count, p.is_new_arrival,
                p.allow_customization, p.category_id,
                (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image,
                (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1 OFFSET 1) AS image_alt,
                (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock";
    }

    /** All active categories, indexed by id, each with 'children' ids. Cached per request. */
    public static function categories(): array
    {
        if (self::$categories !== null) {
            return self::$categories;
        }
        $rows = db()->select('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        $cats = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            $cats[$r['id']] = $r;
        }
        foreach ($cats as $id => $c) {
            if ($c['parent_id'] && isset($cats[$c['parent_id']])) {
                $cats[$c['parent_id']]['children'][] = $id;
            }
        }
        return self::$categories = $cats;
    }

    /** Top-level categories with their child rows – for the mega menu. */
    public static function menu(): array
    {
        $cats = self::categories();
        $menu = [];
        foreach ($cats as $c) {
            if (!$c['parent_id'] && $c['show_in_menu']) {
                $c['items'] = array_values(array_filter(
                    array_map(fn ($cid) => $cats[$cid], $c['children']),
                    fn ($child) => (bool) $child['show_in_menu']
                ));
                $menu[] = $c;
            }
        }
        return $menu;
    }

    public static function categoryBySlug(string $slug): ?array
    {
        foreach (self::categories() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    /** Category id plus all descendant ids. */
    public static function descendantIds(int $id): array
    {
        $cats = self::categories();
        $ids = [$id];
        $stack = $cats[$id]['children'] ?? [];
        while ($stack) {
            $cid = array_pop($stack);
            $ids[] = $cid;
            array_push($stack, ...($cats[$cid]['children'] ?? []));
        }
        return $ids;
    }

    /** Breadcrumb chain root → category. */
    public static function ancestry(int $id): array
    {
        $cats = self::categories();
        $chain = [];
        while ($id && isset($cats[$id]) && count($chain) < 10) {
            array_unshift($chain, $cats[$id]);
            $id = (int) $cats[$id]['parent_id'];
        }
        return $chain;
    }

    /**
     * Product listing with filters, facets & sort.
     * $filters keys: category_id, q, team[], league[], brand[], kit[], gender[], size[], price, discount, sort
     */
    public static function listing(array $filters, int $perPage): array
    {
        // ---- base scope (category / search) – facets are computed within this ----
        $where = ['p.is_active = 1', 'p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['category_id'])) {
            [$in, $p] = Database::in('cat', self::descendantIds((int) $filters['category_id']));
            $where[] = "p.category_id IN $in";
            $params += $p;
        }
        if (!empty($filters['q'])) {
            $q = mb_substr((string) $filters['q'], 0, 80);
            $boolean = implode(' ', array_map(fn ($w) => '+' . $w . '*', array_filter(preg_split('/[^\p{L}\p{N}]+/u', $q), fn ($w) => mb_strlen($w) >= 3)));
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $where[] = '(' . ($boolean !== '' ? 'MATCH(p.name, p.team, p.league, p.brand, p.short_description) AGAINST (:ft IN BOOLEAN MODE) OR ' : '')
                . 'p.name LIKE :lk1 OR p.team LIKE :lk2 OR p.sku = :skuq)';
            if ($boolean !== '') {
                $params['ft'] = $boolean;
            }
            $params += ['lk1' => $like, 'lk2' => $like, 'skuq' => $q];
        }
        $baseWhere = $where;
        $baseParams = $params;

        // ---- refinement filters ----
        foreach (['team' => 'p.team', 'league' => 'p.league', 'brand' => 'p.brand', 'kit' => 'p.kit_type', 'gender' => 'p.gender'] as $key => $col) {
            $vals = array_filter((array) ($filters[$key] ?? []), 'strlen');
            if ($vals) {
                [$in, $p] = Database::in($key, array_slice($vals, 0, 30));
                $where[] = "$col IN $in";
                $params += $p;
            }
        }
        $sizes = array_filter((array) ($filters['size'] ?? []), 'strlen');
        if ($sizes) {
            [$in, $p] = Database::in('sz', array_slice($sizes, 0, 10));
            $where[] = "EXISTS (SELECT 1 FROM product_variants sv WHERE sv.product_id = p.id AND sv.is_active = 1 AND sv.stock > 0 AND sv.size IN $in)";
            $params += $p;
        }
        if (!empty($filters['price']) && preg_match('/^(\d+)-(\d*)$/', (string) $filters['price'], $m)) {
            $where[] = 'p.price >= :pmin';
            $params['pmin'] = (int) $m[1];
            if ($m[2] !== '') {
                $where[] = 'p.price <= :pmax';
                $params['pmax'] = (int) $m[2];
            }
        }
        if (!empty($filters['discount'])) {
            $where[] = '(p.mrp > 0 AND (p.mrp - p.price) / p.mrp * 100 >= :disc)';
            $params['disc'] = (int) $filters['discount'];
        }

        $order = match ($filters['sort'] ?? 'popular') {
            'new'        => 'p.created_at DESC, p.id DESC',
            'price_asc'  => 'p.price ASC, p.id DESC',
            'price_desc' => 'p.price DESC, p.id DESC',
            'discount'   => '(p.mrp - p.price) / p.mrp DESC, p.id DESC',
            'rating'     => 'p.avg_rating DESC, p.review_count DESC',
            default      => 'p.is_featured DESC, p.sold_count DESC, p.id DESC',
        };

        $whereSql = implode(' AND ', $where);
        $paginator = Paginator::query(
            'SELECT ' . self::cardSelect() . " FROM products p WHERE $whereSql ORDER BY $order",
            $params,
            $perPage,
            null,
            "SELECT COUNT(*) FROM products p WHERE $whereSql"
        );

        return ['paginator' => $paginator, 'facets' => self::facets(implode(' AND ', $baseWhere), $baseParams)];
    }

    private static function facets(string $where, array $params): array
    {
        $facet = fn (string $col) => db()->select(
            "SELECT $col AS value, COUNT(*) AS cnt FROM products p WHERE $where AND $col IS NOT NULL AND $col <> '' GROUP BY $col ORDER BY cnt DESC, $col LIMIT 40",
            $params
        );
        return [
            'team'   => $facet('p.team'),
            'league' => $facet('p.league'),
            'brand'  => $facet('p.brand'),
            'kit'    => $facet('p.kit_type'),
            'gender' => $facet('p.gender'),
            'size'   => db()->select(
                "SELECT v.size AS value, COUNT(DISTINCT p.id) AS cnt FROM products p JOIN product_variants v ON v.product_id = p.id
                  WHERE $where AND v.is_active = 1 AND v.stock > 0 GROUP BY v.size ORDER BY MIN(v.sort_order)",
                $params
            ),
        ];
    }

    public static function productBySlug(string $slug): ?array
    {
        $p = db()->first(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE p.slug = :s AND p.is_active = 1 AND p.deleted_at IS NULL',
            ['s' => $slug]
        );
        if (!$p) {
            return null;
        }
        $p['images'] = db()->select('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order, id', ['id' => $p['id']]);
        $p['variants'] = db()->select('SELECT * FROM product_variants WHERE product_id = :id AND is_active = 1 ORDER BY sort_order, id', ['id' => $p['id']]);
        $p['stock'] = array_sum(array_column($p['variants'], 'stock'));
        return $p;
    }

    public static function related(array $product, int $limit = 12): array
    {
        return db()->select(
            'SELECT ' . self::cardSelect() . ' FROM products p
              WHERE p.is_active = 1 AND p.deleted_at IS NULL AND p.id <> :id
                AND (p.team = :team OR p.league = :league OR p.category_id = :cat)
              ORDER BY (p.team = :team2) DESC, p.sold_count DESC LIMIT ' . $limit,
            ['id' => $product['id'], 'team' => (string) $product['team'], 'league' => (string) $product['league'], 'cat' => $product['category_id'], 'team2' => (string) $product['team']]
        );
    }

    /** Home-page rails. $type: featured | new | bestseller | deals */
    public static function rail(string $type, int $limit = 12, ?int $categoryId = null): array
    {
        $where = 'p.is_active = 1 AND p.deleted_at IS NULL';
        $params = [];
        if ($categoryId) {
            [$in, $params] = Database::in('rc', self::descendantIds($categoryId));
            $where .= " AND p.category_id IN $in";
        }
        [$extra, $order] = match ($type) {
            'featured'   => [' AND p.is_featured = 1', 'p.updated_at DESC'],
            'new'        => ['', 'p.is_new_arrival DESC, p.created_at DESC'],
            'deals'      => [' AND p.mrp > p.price', '(p.mrp - p.price) / p.mrp DESC'],
            default      => ['', 'p.sold_count DESC, p.avg_rating DESC'],
        };
        return db()->select('SELECT ' . self::cardSelect() . " FROM products p WHERE $where$extra ORDER BY $order LIMIT $limit", $params);
    }

    /** Lightweight typeahead for the search box. */
    public static function suggest(string $q): array
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 60)) . '%';
        $products = db()->select(
            'SELECT p.name, p.slug, p.price,
                    (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image
               FROM products p
              WHERE p.is_active = 1 AND p.deleted_at IS NULL AND (p.name LIKE :a OR p.team LIKE :b OR p.league LIKE :c)
              ORDER BY p.sold_count DESC LIMIT 6',
            ['a' => $like, 'b' => $like, 'c' => $like]
        );
        $teams = db()->select(
            "SELECT DISTINCT team FROM products WHERE is_active = 1 AND deleted_at IS NULL AND team LIKE :a LIMIT 4",
            ['a' => $like]
        );
        return [
            'products' => array_map(fn ($p) => ['name' => $p['name'], 'url' => '/p/' . $p['slug'], 'price' => money($p['price']), 'image' => upload_url($p['image'])], $products),
            'teams'    => array_map(fn ($t) => ['name' => $t['team'], 'url' => '/search?q=' . rawurlencode($t['team'])], $teams),
        ];
    }

    public static function banners(string $placement): array
    {
        return db()->select(
            'SELECT * FROM banners WHERE placement = :p AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order, id DESC',
            ['p' => $placement]
        );
    }

    public static function reviews(int $productId, int $limit = 10): array
    {
        return db()->select(
            "SELECT r.*, u.name AS user_name FROM product_reviews r JOIN users u ON u.id = r.user_id
              WHERE r.product_id = :id AND r.status = 'approved' ORDER BY r.created_at DESC LIMIT $limit",
            ['id' => $productId]
        );
    }

    public static function ratingBreakdown(int $productId): array
    {
        $rows = db()->select("SELECT rating, COUNT(*) AS c FROM product_reviews WHERE product_id = :id AND status = 'approved' GROUP BY rating", ['id' => $productId]);
        $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $r) {
            $out[(int) $r['rating']] = (int) $r['c'];
        }
        return $out;
    }

    /** Recompute cached rating aggregates on the product row. */
    public static function refreshRating(int $productId): void
    {
        db()->execute(
            "UPDATE products p SET
                avg_rating = COALESCE((SELECT AVG(rating) FROM product_reviews r WHERE r.product_id = p.id AND r.status = 'approved'), 0),
                review_count = (SELECT COUNT(*) FROM product_reviews r2 WHERE r2.product_id = p.id AND r2.status = 'approved')
              WHERE p.id = :id",
            ['id' => $productId]
        );
    }
}
