<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Single source of truth for order maths (used by cart page, checkout and order creation,
 * so what the customer sees is exactly what is charged).
 * All prices are GST-inclusive; GST is back-calculated per line after discount.
 * Internally everything is integer paise to avoid floating point errors.
 */
final class PricingService
{
    /**
     * @param array $items lines with: unit_paise, mrp_paise, qty, tax_rate, category_id
     * @return array{subtotal:int, mrp_total:int, discount:int, shipping:int, cod_fee:int, tax:int, total:int, coupon:?array, coupon_error:?string, line_tax:array}
     */
    public static function calculate(array $items, ?string $couponCode, ?int $userId, ?string $paymentMethod = null): array
    {
        $subtotal = 0;
        $mrpTotal = 0;
        foreach ($items as $it) {
            $subtotal += $it['unit_paise'] * $it['qty'];
            $mrpTotal += $it['mrp_paise'] * $it['qty'];
        }

        $discount = 0;
        $coupon = null;
        $couponError = null;
        if ($couponCode && $items) {
            $coupon = CouponService::findByCode($couponCode);
            try {
                if (!$coupon) {
                    throw new RuntimeException('Invalid coupon code.');
                }
                $discount = CouponService::discountFor($coupon, $items, $subtotal, $userId);
            } catch (RuntimeException $e) {
                $couponError = $e->getMessage();
                $coupon = null;
                $discount = 0;
            }
        }

        $afterDiscount = $subtotal - $discount;
        $shipping = 0;
        if ($items && $afterDiscount < paise(setting('free_shipping_threshold'))) {
            $shipping = paise(setting('shipping_fee'));
        }
        $codFee = $paymentMethod === 'cod' ? paise(setting('cod_fee')) : 0;

        // GST back-calculation (same algorithm as InvoiceService::build so order totals == invoice):
        // discount spread proportionally over lines (last line absorbs rounding); shipping & COD
        // charges are part of a composite supply and taxed at the highest item rate.
        $tax = 0;
        $lineTax = [];
        $maxRate = 0.0;
        $remaining = $discount;
        $keys = array_keys($items);
        foreach ($keys as $pos => $i) {
            $it = $items[$i];
            $line = $it['unit_paise'] * $it['qty'];
            $share = $pos === count($keys) - 1 ? $remaining : ($subtotal > 0 ? intdiv($discount * $line, $subtotal) : 0);
            $remaining -= $share;
            $value = $line - $share;
            $rate = (float) $it['tax_rate'];
            $maxRate = max($maxRate, $rate);
            $lt = $value - (int) round($value / (1 + $rate / 100));
            $lineTax[$i] = $lt;
            $tax += $lt;
        }
        foreach ([$shipping, $codFee] as $charge) {
            if ($charge > 0) {
                $tax += $charge - (int) round($charge / (1 + $maxRate / 100));
            }
        }

        return [
            'subtotal'     => $subtotal,
            'mrp_total'    => $mrpTotal,
            'discount'     => $discount,
            'shipping'     => $shipping,
            'cod_fee'      => $codFee,
            'tax'          => $tax,
            'total'        => $afterDiscount + $shipping + $codFee,
            'coupon'       => $coupon,
            'coupon_error' => $couponError,
            'line_tax'     => $lineTax,
        ];
    }
}
