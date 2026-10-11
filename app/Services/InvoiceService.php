<?php

declare(strict_types=1);

namespace App\Services;

/**
 * GST tax invoices (CGST Rules 2017, Rule 46).
 *  - Invoice number: consecutive per financial year, e.g. JM/26-27/000042 (≤ 16 chars), allocated once,
 *    under a row lock, when the order is confirmed (COD placed / online payment captured).
 *  - All prices are GST-inclusive; tax is back-calculated per line after the coupon discount.
 *  - Shipping & COD charges are part of a composite supply → taxed at the highest rate in the order.
 *  - Intra-state supply (customer state = store state) → CGST + SGST; otherwise IGST.
 */
final class InvoiceService
{
    public const PREFIX = 'JM';

    public const STATE_CODES = [
        'Jammu and Kashmir' => '01', 'Himachal Pradesh' => '02', 'Punjab' => '03', 'Chandigarh' => '04', 'Uttarakhand' => '05',
        'Haryana' => '06', 'Delhi' => '07', 'Rajasthan' => '08', 'Uttar Pradesh' => '09', 'Bihar' => '10', 'Sikkim' => '11',
        'Arunachal Pradesh' => '12', 'Nagaland' => '13', 'Manipur' => '14', 'Mizoram' => '15', 'Tripura' => '16', 'Meghalaya' => '17',
        'Assam' => '18', 'West Bengal' => '19', 'Jharkhand' => '20', 'Odisha' => '21', 'Chhattisgarh' => '22', 'Madhya Pradesh' => '23',
        'Gujarat' => '24', 'Dadra and Nagar Haveli and Daman and Diu' => '26', 'Maharashtra' => '27', 'Karnataka' => '29', 'Goa' => '30',
        'Lakshadweep' => '31', 'Kerala' => '32', 'Tamil Nadu' => '33', 'Puducherry' => '34', 'Andaman and Nicobar Islands' => '35',
        'Telangana' => '36', 'Andhra Pradesh' => '37', 'Ladakh' => '38',
    ];

    /** Indian financial year label (April–March): 2026-10-11 → "26-27", 2027-02-01 → "26-27". */
    public static function financialYear(int $timestamp): string
    {
        $y = (int) date('Y', $timestamp);
        $start = (int) date('n', $timestamp) >= 4 ? $y : $y - 1;
        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    /** Allocate the next invoice number for an order (idempotent). Returns the number. */
    public static function assign(int $orderId): string
    {
        return db()->transaction(function () use ($orderId) {
            $o = db()->first('SELECT id, invoice_number FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if ($o['invoice_number']) {
                return $o['invoice_number'];
            }
            $fy = self::financialYear(time());
            db()->execute('INSERT IGNORE INTO invoice_sequences (fy, last_no) VALUES (:fy, 0)', ['fy' => $fy]);
            $last = (int) db()->scalar('SELECT last_no FROM invoice_sequences WHERE fy = :fy FOR UPDATE', ['fy' => $fy]);
            $next = $last + 1;
            db()->execute('UPDATE invoice_sequences SET last_no = :n WHERE fy = :fy', ['n' => $next, 'fy' => $fy]);
            $number = sprintf('%s/%s/%06d', self::PREFIX, $fy, $next);
            db()->update('orders', ['invoice_number' => $number, 'invoice_date' => now()], 'id = :id', ['id' => $orderId]);
            return $number;
        });
    }

    /** Render the printable invoice (shared by customer account & admin). */
    public static function render(array $order): \App\Core\Response
    {
        if (!self::eligible($order)) {
            throw new \App\Core\HttpException(404, 'Your GST invoice is generated when the order is dispatched.');
        }
        if (!$order['invoice_number']) {
            self::assign((int) $order['id']);
            $order = OrderService::find((int) $order['id']);
        }
        $items = OrderService::items((int) $order['id']);
        $address = OrderService::address($order);
        return view('shared/invoice', [
            'order' => $order, 'items' => $items, 'address' => $address,
            'inv'   => self::build($order, $items, $address, (string) setting('store_state')),
        ], null);
    }

    /** Orders that may have an invoice (anything confirmed onwards, including cancelled-after-payment). */
    public static function eligible(array $order): bool
    {
        return !empty($order['invoice_number'])
            || in_array($order['status'], ['shipped', 'out_for_delivery', 'delivered', 'return_requested', 'returned'], true);
    }

    /**
     * Compute every figure printed on the invoice. Pure function (no DB) → unit-testable.
     * @return array{intra:bool, state_code:string, lines:array, charges:array, totals:array}
     */
    public static function build(array $order, array $items, array $address, string $storeState): array
    {
        $intra = strcasecmp(trim((string) ($address['state'] ?? '')), trim($storeState)) === 0;
        $subtotal = paise($order['subtotal']);
        $discount = paise($order['discount']);
        $maxRate = 0.0;
        $lines = [];
        $remaining = $discount;
        $n = count($items);

        foreach (array_values($items) as $i => $it) {
            $gross = paise($it['line_total']);
            // discount share proportional to line value; last line absorbs rounding so shares sum exactly
            $share = $i === $n - 1 ? $remaining : ($subtotal > 0 ? intdiv($discount * $gross, $subtotal) : 0);
            $remaining -= $share;
            $value = $gross - $share;
            $rate = (float) $it['tax_rate'];
            $maxRate = max($maxRate, $rate);
            $lines[] = self::taxLine($it['product_name'], (string) $it['hsn_code'], (int) $it['qty'], $value, $rate, $intra) + [
                'sku' => $it['sku'], 'size' => $it['size'], 'unit_price' => paise($it['unit_price']), 'gross' => $gross, 'discount' => $share,
                'custom' => trim(($it['custom_name'] ?? '') . ' ' . ($it['custom_number'] ?? '')),
            ];
        }

        $charges = [];
        foreach (['shipping_fee' => 'Shipping & handling', 'cod_fee' => 'Cash on delivery charges'] as $col => $label) {
            $amt = paise($order[$col] ?? 0);
            if ($amt > 0) {
                $charges[] = self::taxLine($label, '9965', 1, $amt, $maxRate, $intra);
            }
        }

        $all = array_merge($lines, $charges);
        $totals = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'tax' => 0, 'value' => 0];
        foreach ($all as $l) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $l[$k];
            }
        }
        $totals['grand'] = paise($order['grand_total']);
        $totals['round_off'] = $totals['grand'] - $totals['value']; // should be 0 – asserted in tests

        return [
            'intra'      => $intra,
            'state_code' => self::STATE_CODES[$address['state'] ?? ''] ?? '—',
            'lines'      => $lines,
            'charges'    => $charges,
            'totals'     => $totals,
        ];
    }

    /** Split a GST-inclusive amount (paise) into taxable value and tax components. */
    private static function taxLine(string $desc, string $hsn, int $qty, int $value, float $rate, bool $intra): array
    {
        $taxable = (int) round($value / (1 + $rate / 100));
        $tax = $value - $taxable;
        $cgst = $intra ? intdiv($tax, 2) : 0;
        $sgst = $intra ? $tax - $cgst : 0;
        return [
            'description' => $desc, 'hsn' => $hsn, 'qty' => $qty, 'rate' => $rate,
            'value' => $value, 'taxable' => $taxable, 'tax' => $tax,
            'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $intra ? 0 : $tax,
        ];
    }

    /** 12345.50 → "Rupees Twelve Thousand Three Hundred Forty Five and Fifty Paise Only" (Indian numbering). */
    public static function amountInWords(int $paise): string
    {
        $r = intdiv($paise, 100);
        $p = $paise % 100;
        $words = 'Rupees ' . ($r === 0 ? 'Zero' : self::words($r));
        if ($p > 0) {
            $words .= ' and ' . self::words($p) . ' Paise';
        }
        return $words . ' Only';
    }

    private static function words(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
                 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn (int $x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        $out = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $name]) {
            if ($n >= $div) {
                $q = intdiv($n, $div);
                $out[] = ($q >= 100 ? self::words($q) : $two($q)) . ' ' . $name;
                $n %= $div;
            }
        }
        if ($n > 0) {
            $out[] = $two($n);
        }
        return implode(' ', $out);
    }
}
