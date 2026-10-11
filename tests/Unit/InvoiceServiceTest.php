<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InvoiceService;
use Tests\TestCase;

final class InvoiceServiceTest extends TestCase
{
    public function testFinancialYearRunsAprilToMarch(): void
    {
        $this->assertSame('26-27', InvoiceService::financialYear(strtotime('2026-04-01')));
        $this->assertSame('26-27', InvoiceService::financialYear(strtotime('2027-03-31 23:59')));
        $this->assertSame('27-28', InvoiceService::financialYear(strtotime('2027-04-01')));
        $this->assertSame('99-00', InvoiceService::financialYear(strtotime('2099-06-01')));
    }

    public function testAmountInWordsIndianSystem(): void
    {
        $this->assertSame('Rupees Zero Only', InvoiceService::amountInWords(0));
        $this->assertSame('Rupees Three Thousand Two Hundred Forty Five Only', InvoiceService::amountInWords(324500));
        $this->assertSame('Rupees One Lakh Only', InvoiceService::amountInWords(10000000));
        $this->assertSame('Rupees Twelve Lakh Forty Five Thousand Six Hundred Seventy Eight and Ninety Paise Only', InvoiceService::amountInWords(124567890));
        $this->assertSame('Rupees One Crore Five Only', InvoiceService::amountInWords(1000000500));
    }

    private function order(array $o): array
    {
        return $o + ['subtotal' => '0', 'discount' => '0', 'shipping_fee' => '0', 'cod_fee' => '0', 'grand_total' => '0'];
    }

    private function item(string $name, float $unit, int $qty, float $rate): array
    {
        return ['product_name' => $name, 'hsn_code' => '6109', 'qty' => $qty, 'unit_price' => $unit, 'line_total' => $unit * $qty,
                'tax_rate' => $rate, 'sku' => 'SKU', 'size' => 'M', 'custom_name' => null, 'custom_number' => null];
    }

    public function testIntraStateSplitsCgstSgstAndReconcilesToGrandTotal(): void
    {
        $items = [$this->item('Jersey A', 1748, 2, 5)];
        $order = $this->order(['subtotal' => '3496', 'discount' => '300', 'cod_fee' => '49', 'grand_total' => '3245']);
        $inv = InvoiceService::build($order, $items, ['state' => 'West Bengal'], 'West Bengal');

        $this->assertTrue($inv['intra']);
        $this->assertSame('19', $inv['state_code']);
        $l = $inv['lines'][0];
        $this->assertSame(319600, $l['value']);                 // 3496 − 300
        $this->assertSame(304381, $l['taxable']);               // 3196 / 1.05
        $this->assertSame($l['tax'], $l['cgst'] + $l['sgst']);
        $this->assertSame(0, $l['igst']);
        $this->assertCount(1, $inv['charges'], 'COD fee is a taxable charge line');
        $this->assertSame(5.0, $inv['charges'][0]['rate']);
        $this->assertSame(0, $inv['totals']['round_off'], 'invoice lines must add up exactly to the order total');
        $this->assertSame(324500, $inv['totals']['grand']);
    }

    public function testInterStateUsesIgstAndChargesTakeHighestRate(): void
    {
        $items = [$this->item('Cheap jersey', 999, 1, 5), $this->item('Premium jersey', 2999, 1, 18)];
        $order = $this->order(['subtotal' => '3998', 'discount' => '0', 'shipping_fee' => '79', 'grand_total' => '4077']);
        $inv = InvoiceService::build($order, $items, ['state' => 'Maharashtra'], 'West Bengal');

        $this->assertFalse($inv['intra']);
        $this->assertSame('27', $inv['state_code']);
        foreach (array_merge($inv['lines'], $inv['charges']) as $l) {
            $this->assertSame(0, $l['cgst'] + $l['sgst']);
            $this->assertSame($l['tax'], $l['igst']);
        }
        $this->assertSame(18.0, $inv['charges'][0]['rate'], 'composite-supply charges use the highest item rate');
        $this->assertSame(0, $inv['totals']['round_off']);
    }

    public function testDiscountSharesSumExactlyToTheCouponAmount(): void
    {
        // 3 lines with awkward proportions – rounding must be absorbed by the last line.
        $items = [$this->item('A', 333, 1, 5), $this->item('B', 333, 1, 5), $this->item('C', 334, 1, 12)];
        $order = $this->order(['subtotal' => '1000', 'discount' => '100.01', 'grand_total' => '899.99']);
        $inv = InvoiceService::build($order, $items, ['state' => 'Delhi'], 'West Bengal');
        $this->assertSame(10001, array_sum(array_column($inv['lines'], 'discount')));
        $this->assertSame(0, $inv['totals']['round_off']);
        $this->assertSame($inv['totals']['value'], $inv['totals']['taxable'] + $inv['totals']['tax']);
    }
}
