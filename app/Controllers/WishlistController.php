<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;

final class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        seo()->title('My Wishlist')->noindex();
        $items = db()->select(
            'SELECT ' . CatalogService::cardSelect() . ' FROM wishlists w JOIN products p ON p.id = w.product_id
              WHERE w.user_id = :u AND p.deleted_at IS NULL ORDER BY w.created_at DESC',
            ['u' => auth()->id()]
        );
        return view('store/account/wishlist', ['items' => $items]);
    }

    public function toggle(Request $request): Response
    {
        $productId = $request->int('product_id');
        $exists = db()->scalar('SELECT 1 FROM wishlists WHERE user_id = :u AND product_id = :p', ['u' => auth()->id(), 'p' => $productId]);
        if ($exists) {
            db()->execute('DELETE FROM wishlists WHERE user_id = :u AND product_id = :p', ['u' => auth()->id(), 'p' => $productId]);
        } elseif (db()->scalar('SELECT 1 FROM products WHERE id = :p AND is_active = 1 AND deleted_at IS NULL', ['p' => $productId])) {
            db()->insert('wishlists', ['user_id' => auth()->id(), 'product_id' => $productId]);
        }
        $added = !$exists;
        return $request->wantsJson()
            ? json(['ok' => true, 'added' => $added, 'message' => $added ? 'Added to wishlist' : 'Removed from wishlist'])
            : back()->with('success', $added ? 'Added to wishlist' : 'Removed from wishlist');
    }
}
