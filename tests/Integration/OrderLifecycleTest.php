<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\RateLimiter;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\ReturnService;
use RuntimeException;
use Tests\TestCase;

final class OrderLifecycleTest extends TestCase
{
    private function order(string $status, string $payment = 'cod', string $paymentStatus = 'cod_pending', float $total = 1048): array
    {
        $uid = $this->makeUser();
        [$pid, $v] = $this->makeProduct([], ['M' => 10]);
        $id = db()->insert('orders', [
            'order_number' => 'JMT' . strtoupper(bin2hex(random_bytes(4))), 'user_id' => $uid, 'status' => $status,
            'payment_status' => $paymentStatus, 'payment_method' => $payment, 'subtotal' => 999, 'cod_fee' => $payment === 'cod' ? 49 : 0,
            'grand_total' => $total, 'customer_name' => 'Unit', 'customer_email' => 'u@example.com', 'customer_phone' => '9830012345',
            'shipping_address' => json_encode(['name' => 'Unit', 'state' => 'West Bengal', 'pincode' => '700001']),
            'delivered_at' => $status === 'delivered' ? date('Y-m-d H:i:s') : null,
        ]);
        db()->insert('order_items', [
            'order_id' => $id, 'product_id' => $pid, 'variant_id' => $v['M'], 'product_name' => 'Test', 'product_slug' => 'test', 'sku' => 'S',
            'size' => 'M', 'qty' => 1, 'unit_price' => 999, 'mrp' => 1999, 'tax_rate' => 5, 'line_total' => 999,
        ]);
        return OrderService::find($id) + ['_variant' => $v['M']];
    }

    public function testStockCanNeverGoNegativeAndEveryChangeIsLedgered(): void
    {
        [, $v] = $this->makeProduct([], ['L' => 2]);
        InventoryService::adjust($v['L'], -2, 'order', 'T1');
        $this->assertSame(0, (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $v['L']]));
        try {
            InventoryService::adjust($v['L'], -1, 'order', 'T2');
            $this->fail('overselling must be impossible');
        } catch (RuntimeException) {
        }
        $this->assertSame(1, (int) db()->scalar('SELECT COUNT(*) FROM inventory_movements WHERE variant_id = :id', ['id' => $v['L']]));
    }

    public function testIllegalTransitionsAreRejected(): void
    {
        $o = $this->order('confirmed');
        foreach (['delivered', 'returned', 'pending_payment'] as $bad) {
            try {
                OrderService::transition($o, $bad);
                $this->fail("confirmed → $bad must be rejected");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Cannot move order', $e->getMessage());
            }
        }
        $o = OrderService::transition($o, 'packed', 'unit', 'admin');
        $this->assertSame('packed', OrderService::find((int) $o['id'])['status']);
        $this->assertSame(1, (int) db()->scalar('SELECT COUNT(*) FROM order_status_history WHERE order_id = :id', ['id' => $o['id']]));
    }

    public function testDeliveryMarksCodPaid(): void
    {
        $o = $this->order('out_for_delivery');
        OrderService::transition($o, 'delivered', null, 'admin');
        $o = OrderService::find((int) $o['id']);
        $this->assertSame('paid', $o['payment_status']);
        $this->assertNotNull($o['delivered_at']);
    }

    public function testCancelRestoresStockAndCustomerCannotCancelShipped(): void
    {
        $o = $this->order('confirmed');
        $before = (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $o['_variant']]);
        OrderService::cancel($o, 'unit test', 'customer');
        $this->assertSame('cancelled', OrderService::find((int) $o['id'])['status']);
        $this->assertSame($before + 1, (int) db()->scalar('SELECT stock FROM product_variants WHERE id = :id', ['id' => $o['_variant']]));

        $shipped = $this->order('shipped');
        $this->expectException(RuntimeException::class);
        OrderService::cancel($shipped, 'too late', 'customer');
    }

    public function testRefundsCannotExceedAmountPaid(): void
    {
        $o = $this->order('delivered', 'cod', 'paid', 1048);
        RefundService::create($o, 50000, 'partial', null, 'manual', 'UTR1');
        $o = OrderService::find((int) $o['id']);
        $this->assertSame('partially_refunded', $o['payment_status']);
        $this->assertSame(54800, RefundService::refundable($o));

        $this->expectException(RuntimeException::class);
        RefundService::create($o, 54801, 'one paisa too many', null, 'manual', 'UTR2');
    }

    public function testCodOrdersCannotUseGatewayRefund(): void
    {
        $o = $this->order('delivered', 'cod', 'paid');
        $this->expectExceptionMessage('manually');
        RefundService::create($o, 1000, 'x', null, 'gateway');
    }

    public function testInvoiceNumbersAreSequentialUniqueAndIdempotent(): void
    {
        $a = $this->order('shipped');
        $b = $this->order('shipped');
        $na = InvoiceService::assign((int) $a['id']);
        $nb = InvoiceService::assign((int) $b['id']);
        $this->assertMatchesRegularExpression('#^JM/\d{2}-\d{2}/\d{6}$#', $na);
        $this->assertLessThanOrEqual(16, strlen($na), 'GST Rule 46: max 16 characters');
        $this->assertSame((int) substr($na, -6) + 1, (int) substr($nb, -6), 'consecutive');
        $this->assertSame($na, InvoiceService::assign((int) $a['id']), 'idempotent');
        $this->assertFalse(InvoiceService::eligible($this->order('confirmed')), 'no invoice before dispatch');
    }

    public function testReturnWindowAndPersonalisedItemsExcluded(): void
    {
        $o = $this->order('delivered');
        $this->assertCount(1, ReturnService::returnableItems($o));
        db()->execute('UPDATE orders SET delivered_at = NOW() - INTERVAL 30 DAY WHERE id = :id', ['id' => $o['id']]);
        $this->assertCount(0, ReturnService::returnableItems(OrderService::find((int) $o['id'])), 'outside return window');

        $p = $this->order('delivered');
        db()->execute("UPDATE order_items SET custom_name = 'MESSI' WHERE order_id = :id", ['id' => $p['id']]);
        $this->assertCount(0, ReturnService::returnableItems(OrderService::find((int) $p['id'])), 'personalised jerseys are not returnable');
    }

    public function testRateLimiterBlocksAfterLimit(): void
    {
        $key = 'unit|' . bin2hex(random_bytes(4));
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue(RateLimiter::hit($key, 3, 60));
        }
        $this->assertFalse(RateLimiter::hit($key, 3, 60));
        RateLimiter::clear($key);
        $this->assertTrue(RateLimiter::hit($key, 3, 60));
    }
}
