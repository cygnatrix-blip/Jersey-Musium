<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * DB-persisted shopping bag. Guests are identified by a random cart token kept in the
 * session (survives session-id regeneration); on login the guest bag merges into the user's.
 */
final class CartService
{
    public const MAX_QTY = 10;

    private function token(): string
    {
        $t = session()->get('cart_token');
        if (!$t) {
            $t = bin2hex(random_bytes(24));
            session()->set('cart_token', $t);
        }
        return $t;
    }

    public function cart(bool $create = false): ?array
    {
        $uid = auth()->id();
        $cart = $uid
            ? db()->first('SELECT * FROM carts WHERE user_id = :u', ['u' => $uid])
            : db()->first('SELECT * FROM carts WHERE session_token = :t', ['t' => $this->token()]);
        if (!$cart && $create) {
            $id = db()->insert('carts', $uid ? ['user_id' => $uid] : ['session_token' => $this->token()]);
            $cart = db()->first('SELECT * FROM carts WHERE id = :id', ['id' => $id]);
        }
        return $cart;
    }

    public function items(int $cartId): array
    {
        $rows = db()->select(
            'SELECT ci.id, ci.qty, ci.custom_name, ci.custom_number, ci.variant_id,
                    v.size, v.sku, v.stock, v.is_active AS variant_active,
                    p.id AS product_id, p.name, p.slug, p.mrp, p.price, p.tax_rate, p.hsn_code, p.category_id, p.team,
                    p.allow_customization, p.customization_price, p.is_active, p.deleted_at,
                    (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image
               FROM cart_items ci
               JOIN product_variants v ON v.id = ci.variant_id
               JOIN products p ON p.id = v.product_id
              WHERE ci.cart_id = :c ORDER BY ci.id DESC',
            ['c' => $cartId]
        );
        foreach ($rows as &$r) {
            $custom = ($r['custom_name'] || $r['custom_number']) && $r['allow_customization'] ? paise($r['customization_price']) : 0;
            $r['unit_paise'] = paise($r['price']) + $custom;
            $r['mrp_paise'] = paise($r['mrp']) + $custom;
            $r['qty'] = (int) $r['qty'];
            $r['issue'] = match (true) {
                !$r['is_active'] || $r['deleted_at'] !== null || !$r['variant_active'] => 'This item is no longer available.',
                (int) $r['stock'] <= 0  => 'Out of stock',
                (int) $r['stock'] < $r['qty'] => 'Only ' . (int) $r['stock'] . ' left – reduce quantity',
                default => null,
            };
        }
        return $rows;
    }

    /** Full bag view model: items + totals (+ COD fee when $paymentMethod = 'cod'). */
    public function summary(?string $paymentMethod = null): array
    {
        $cart = $this->cart();
        $items = $cart ? $this->items((int) $cart['id']) : [];
        $totals = PricingService::calculate($items, $cart['coupon_code'] ?? null, auth()->id(), $paymentMethod);

        // Silently drop a coupon that became invalid (expired, min amount not met...).
        if ($cart && $cart['coupon_code'] && $totals['coupon'] === null) {
            db()->update('carts', ['coupon_code' => null], 'id = :id', ['id' => $cart['id']]);
        }

        return [
            'cart'      => $cart,
            'items'     => $items,
            'totals'    => $totals,
            'has_issue' => (bool) array_filter(array_column($items, 'issue')),
            'count'     => array_sum(array_column($items, 'qty')),
        ];
    }

    public function count(): int
    {
        $cart = $this->cart();
        return $cart ? (int) db()->scalar('SELECT COALESCE(SUM(qty), 0) FROM cart_items WHERE cart_id = :c', ['c' => $cart['id']]) : 0;
    }

    public function add(int $variantId, int $qty, ?string $customName = null, ?string $customNumber = null): void
    {
        $v = db()->first(
            'SELECT v.*, p.allow_customization, p.is_active AS p_active, p.deleted_at
               FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = :id',
            ['id' => $variantId]
        );
        if (!$v || !$v['is_active'] || !$v['p_active'] || $v['deleted_at']) {
            throw new RuntimeException('This product is not available.');
        }
        $customName = $v['allow_customization'] && $customName ? mb_strtoupper(mb_substr(preg_replace('/[^\p{L} .\'-]/u', '', $customName), 0, 15)) : null;
        $customNumber = $v['allow_customization'] && $customNumber !== null && $customNumber !== '' ? substr(preg_replace('/\D/', '', $customNumber), 0, 2) : null;

        $cart = $this->cart(true);
        $existing = db()->first(
            'SELECT * FROM cart_items WHERE cart_id = :c AND variant_id = :v AND custom_name <=> :n AND custom_number <=> :num',
            ['c' => $cart['id'], 'v' => $variantId, 'n' => $customName, 'num' => $customNumber]
        );
        $newQty = min(self::MAX_QTY, ($existing['qty'] ?? 0) + max(1, $qty));
        if ($newQty > (int) $v['stock']) {
            throw new RuntimeException((int) $v['stock'] > 0 ? 'Only ' . $v['stock'] . ' left in size ' . $v['size'] . '.' : 'Size ' . $v['size'] . ' is out of stock.');
        }
        if ($existing) {
            db()->update('cart_items', ['qty' => $newQty], 'id = :id', ['id' => $existing['id']]);
        } else {
            db()->insert('cart_items', [
                'cart_id' => $cart['id'], 'variant_id' => $variantId, 'qty' => $newQty,
                'custom_name' => $customName, 'custom_number' => $customNumber,
            ]);
        }
        db()->execute('UPDATE carts SET updated_at = NOW() WHERE id = :id', ['id' => $cart['id']]);
    }

    public function update(int $itemId, int $qty): void
    {
        $cart = $this->cart();
        if (!$cart) {
            return;
        }
        if ($qty <= 0) {
            $this->remove($itemId);
            return;
        }
        $stock = (int) db()->scalar(
            'SELECT v.stock FROM cart_items ci JOIN product_variants v ON v.id = ci.variant_id WHERE ci.id = :id AND ci.cart_id = :c',
            ['id' => $itemId, 'c' => $cart['id']]
        );
        if ($qty > $stock) {
            throw new RuntimeException($stock > 0 ? "Only $stock left in stock." : 'This size is out of stock.');
        }
        db()->update('cart_items', ['qty' => min(self::MAX_QTY, $qty)], 'id = :id AND cart_id = :c', ['id' => $itemId, 'c' => $cart['id']]);
    }

    public function remove(int $itemId): void
    {
        $cart = $this->cart();
        if ($cart) {
            db()->execute('DELETE FROM cart_items WHERE id = :id AND cart_id = :c', ['id' => $itemId, 'c' => $cart['id']]);
        }
    }

    public function applyCoupon(string $code): array
    {
        $cart = $this->cart();
        if (!$cart) {
            throw new RuntimeException('Your bag is empty.');
        }
        $coupon = CouponService::findByCode($code) ?? throw new RuntimeException('Invalid coupon code.');
        $items = $this->items((int) $cart['id']);
        $subtotal = array_sum(array_map(fn ($i) => $i['unit_paise'] * $i['qty'], $items));
        $discount = CouponService::discountFor($coupon, $items, $subtotal, auth()->id());
        db()->update('carts', ['coupon_code' => $coupon['code']], 'id = :id', ['id' => $cart['id']]);
        return ['code' => $coupon['code'], 'discount' => $discount];
    }

    public function removeCoupon(): void
    {
        $cart = $this->cart();
        if ($cart) {
            db()->update('carts', ['coupon_code' => null], 'id = :id', ['id' => $cart['id']]);
        }
    }

    public function clear(int $cartId): void
    {
        db()->execute('DELETE FROM cart_items WHERE cart_id = :c', ['c' => $cartId]);
        db()->update('carts', ['coupon_code' => null], 'id = :id', ['id' => $cartId]);
    }

    /** Called right after login: move the guest bag into the user's bag. */
    public function mergeGuestCart(int $userId): void
    {
        $token = session()->get('cart_token');
        if (!$token) {
            return;
        }
        $guest = db()->first('SELECT * FROM carts WHERE session_token = :t', ['t' => $token]);
        if (!$guest) {
            return;
        }
        db()->transaction(function () use ($guest, $userId) {
            $userCart = db()->first('SELECT * FROM carts WHERE user_id = :u', ['u' => $userId]);
            if (!$userCart) {
                db()->update('carts', ['user_id' => $userId, 'session_token' => null], 'id = :id', ['id' => $guest['id']]);
                return;
            }
            foreach (db()->select('SELECT * FROM cart_items WHERE cart_id = :c', ['c' => $guest['id']]) as $item) {
                $dup = db()->first(
                    'SELECT * FROM cart_items WHERE cart_id = :c AND variant_id = :v AND custom_name <=> :n AND custom_number <=> :num',
                    ['c' => $userCart['id'], 'v' => $item['variant_id'], 'n' => $item['custom_name'], 'num' => $item['custom_number']]
                );
                if ($dup) {
                    db()->update('cart_items', ['qty' => min(self::MAX_QTY, $dup['qty'] + $item['qty'])], 'id = :id', ['id' => $dup['id']]);
                } else {
                    db()->update('cart_items', ['cart_id' => $userCart['id']], 'id = :id', ['id' => $item['id']]);
                }
            }
            if (!$userCart['coupon_code'] && $guest['coupon_code']) {
                db()->update('carts', ['coupon_code' => $guest['coupon_code']], 'id = :id', ['id' => $userCart['id']]);
            }
            db()->execute('DELETE FROM carts WHERE id = :id', ['id' => $guest['id']]);
        });
    }
}
