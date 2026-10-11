<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CouponService
{
    public static function findByCode(string $code): ?array
    {
        return db()->first('SELECT * FROM coupons WHERE code = :c', ['c' => strtoupper(trim($code))]);
    }

    /**
     * Validate a coupon against the cart and return the discount in paise.
     * Throws RuntimeException with a customer-friendly message when not applicable.
     *
     * @param array $items cart lines with keys: unit_paise, qty, category_id
     */
    public static function discountFor(array $coupon, array $items, int $subtotalPaise, ?int $userId): int
    {
        $now = time();
        if (!$coupon['is_active']
            || ($coupon['starts_at'] && strtotime($coupon['starts_at']) > $now)
            || ($coupon['ends_at'] && strtotime($coupon['ends_at']) < $now)) {
            throw new RuntimeException('This coupon is not active right now.');
        }
        if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
            throw new RuntimeException('This coupon has been fully redeemed.');
        }
        if ($userId) {
            $used = (int) db()->scalar(
                "SELECT COUNT(*) FROM coupon_redemptions cr JOIN orders o ON o.id = cr.order_id
                  WHERE cr.coupon_id = :c AND cr.user_id = :u AND o.status NOT IN ('cancelled','payment_failed')",
                ['c' => $coupon['id'], 'u' => $userId]
            );
            if ($used >= (int) $coupon['per_user_limit']) {
                throw new RuntimeException('You have already used this coupon.');
            }
            if ($coupon['first_order_only']) {
                $orders = (int) db()->scalar(
                    "SELECT COUNT(*) FROM orders WHERE user_id = :u AND status NOT IN ('cancelled','payment_failed','pending_payment')",
                    ['u' => $userId]
                );
                if ($orders > 0) {
                    throw new RuntimeException('This coupon is valid on your first order only.');
                }
            }
        }

        $eligible = $subtotalPaise;
        if ($coupon['category_id']) {
            $catIds = CatalogService::descendantIds((int) $coupon['category_id']);
            $eligible = 0;
            foreach ($items as $it) {
                if (in_array((int) $it['category_id'], $catIds, true)) {
                    $eligible += $it['unit_paise'] * $it['qty'];
                }
            }
            if ($eligible === 0) {
                throw new RuntimeException('This coupon does not apply to the items in your bag.');
            }
        }

        $min = paise($coupon['min_order_amount']);
        if ($subtotalPaise < $min) {
            throw new RuntimeException('Add items worth ' . money(($min - $subtotalPaise) / 100) . ' more to use this coupon.');
        }

        if ($coupon['type'] === 'percent') {
            $discount = intdiv($eligible * (int) round((float) $coupon['value'] * 100), 10000);
            if ($coupon['max_discount'] !== null) {
                $discount = min($discount, paise($coupon['max_discount']));
            }
        } else {
            $discount = min(paise($coupon['value']), $eligible);
        }
        return max(0, $discount);
    }

    /** Public offers shown on PDP & cart. */
    public static function publicOffers(int $limit = 5): array
    {
        return db()->select(
            "SELECT code, description, type, value, max_discount, min_order_amount FROM coupons
              WHERE is_active = 1 AND is_public = 1
                AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
                AND (usage_limit IS NULL OR used_count < usage_limit)
              ORDER BY value DESC LIMIT $limit"
        );
    }

    public static function describe(array $c): string
    {
        $v = $c['type'] === 'percent' ? rtrim(rtrim((string) $c['value'], '0'), '.') . '% off' : money($c['value']) . ' off';
        if ($c['type'] === 'percent' && $c['max_discount']) {
            $v .= ' up to ' . money($c['max_discount']);
        }
        if ((float) $c['min_order_amount'] > 0) {
            $v .= ' on orders above ' . money($c['min_order_amount']);
        }
        return $v;
    }
}
