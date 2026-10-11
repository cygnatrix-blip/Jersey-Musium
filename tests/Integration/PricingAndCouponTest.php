<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\CouponService;
use App\Services\PricingService;
use RuntimeException;
use Tests\TestCase;

final class PricingAndCouponTest extends TestCase
{
    private function coupon(array $o): string
    {
        $code = 'T' . strtoupper(bin2hex(random_bytes(3)));
        db()->insert('coupons', $o + ['code' => $code, 'type' => 'percent', 'value' => 10, 'per_user_limit' => 1, 'min_order_amount' => 0]);
        return $code;
    }

    public function testShippingThresholdAndCodFee(): void
    {
        // defaults: free shipping ≥ ₹999, else ₹79; COD fee ₹49
        $below = PricingService::calculate([$this->line(500, 1)], null, null);
        $this->assertSame(7900, $below['shipping']);
        $this->assertSame(50000 + 7900, $below['total']);

        $above = PricingService::calculate([$this->line(999, 1)], null, null, 'cod');
        $this->assertSame(0, $above['shipping']);
        $this->assertSame(4900, $above['cod_fee']);
        $this->assertSame(99900 + 4900, $above['total']);
        $this->assertSame(0, PricingService::calculate([], null, null)['shipping'], 'empty bag has no shipping');
    }

    public function testGstIsBackCalculatedIncludingCharges(): void
    {
        $t = PricingService::calculate([$this->line(1050, 1, 5)], null, null, 'cod');
        // item: 1050 → 50.00 GST ; COD 49 @5% → 2.33 GST
        $this->assertSame(5000 + 233, $t['tax']);
        $this->assertSame(105000 + 4900, $t['total'], 'GST-inclusive: tax never changes the payable amount');
    }

    public function testPercentCouponIsCapped(): void
    {
        $code = $this->coupon(['value' => 20, 'max_discount' => 150]);
        $t = PricingService::calculate([$this->line(2000, 1)], $code, null);
        $this->assertSame(15000, $t['discount']);
        $this->assertNull($t['coupon_error']);
    }

    public function testFlatCouponNeverExceedsEligibleAmount(): void
    {
        $code = $this->coupon(['type' => 'flat', 'value' => 5000]);
        $t = PricingService::calculate([$this->line(1200, 1)], $code, null);
        $this->assertSame(120000, $t['discount']);
        $this->assertGreaterThanOrEqual(0, $t['total']);
    }

    public function testMinimumOrderExpiredAndUsageLimit(): void
    {
        $min = $this->coupon(['min_order_amount' => 1500]);
        $t = PricingService::calculate([$this->line(1000, 1)], $min, null);
        $this->assertSame(0, $t['discount']);
        $this->assertStringContainsString('more to use this coupon', (string) $t['coupon_error']);

        $expired = $this->coupon(['ends_at' => date('Y-m-d H:i:s', time() - 60)]);
        $this->assertStringContainsString('not active', (string) PricingService::calculate([$this->line(1000, 1)], $expired, null)['coupon_error']);

        $used = $this->coupon(['usage_limit' => 5, 'used_count' => 5]);
        $this->assertStringContainsString('fully redeemed', (string) PricingService::calculate([$this->line(1000, 1)], $used, null)['coupon_error']);

        $this->assertSame('Invalid coupon code.', PricingService::calculate([$this->line(1000, 1)], 'NOPE', null)['coupon_error']);
    }

    public function testCategoryRestrictedCoupon(): void
    {
        $kids = (int) db()->scalar("SELECT id FROM categories WHERE slug = 'kids'");
        $code = $this->coupon(['value' => 50, 'category_id' => $kids]);
        $t = PricingService::calculate([$this->line(1000, 1, 5, 0, $kids), $this->line(1000, 1)], $code, null);
        $this->assertSame(50000, $t['discount'], 'only the kids line is discounted');

        $this->expectException(RuntimeException::class);
        CouponService::discountFor(CouponService::findByCode($code), [$this->line(1000, 1)], 100000, null);
    }

    public function testFirstOrderOnlyAndPerUserLimit(): void
    {
        $uid = $this->makeUser();
        $code = $this->coupon(['first_order_only' => 1]);
        $this->assertNull(PricingService::calculate([$this->line(1000, 1)], $code, $uid)['coupon_error']);
        db()->insert('orders', [
            'order_number' => 'JMTFO' . bin2hex(random_bytes(3)), 'user_id' => $uid, 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'cod',
            'subtotal' => 100, 'grand_total' => 100, 'customer_name' => 'x', 'customer_email' => 'x@y.z', 'customer_phone' => '9830012345', 'shipping_address' => '{}',
        ]);
        $this->assertStringContainsString('first order', (string) PricingService::calculate([$this->line(1000, 1)], $code, $uid)['coupon_error']);
    }
}
