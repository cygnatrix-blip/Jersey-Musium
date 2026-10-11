<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HelpersTest extends TestCase
{
    #[DataProvider('moneyCases')]
    public function testMoneyUsesIndianGrouping(float|int $amount, bool $decimals, string $expected): void
    {
        $this->assertSame($expected, money($amount, $decimals));
    }

    public static function moneyCases(): array
    {
        return [
            [0, false, '₹0'], [999, false, '₹999'], [1000, false, '₹1,000'], [123456, false, '₹1,23,456'],
            [12345678.9, true, '₹1,23,45,678.90'], [0.995, true, '₹1.00'], [1499.5, false, '₹1,500'], [-250, false, '-₹250'],
        ];
    }

    public function testPaiseConversionAvoidsFloatErrors(): void
    {
        $this->assertSame(1999, paise('19.99'));
        $this->assertSame(30, paise(0.1 + 0.2));
        $this->assertSame('1748.00', rupees(174800));
    }

    public function testDiscountPercent(): void
    {
        $this->assertSame(40, discount_percent(2499, 1499));
        $this->assertSame(0, discount_percent(999, 999));
        $this->assertSame(0, discount_percent(0, 100));
    }

    public function testEscapingNeutralisesHtml(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        $this->assertSame('&#039;', e("'"));
        $this->assertSame('', e(null));
    }

    public function testSlugify(): void
    {
        $this->assertSame('argentina-2026-home-jersey', slugify('Argentina 2026 Home Jersey!'));
        $this->assertSame('cafe-jersey', slugify('Café Jersey'));
    }

    #[DataProvider('xssPayloads')]
    public function testSafeHtmlStripsXss(string $payload, string $mustNotContain): void
    {
        $this->assertStringNotContainsStringIgnoringCase($mustNotContain, safe_html($payload));
    }

    public static function xssPayloads(): array
    {
        return [
            ['<script>alert(1)</script>', '<script'],
            ['<img src=x onerror=alert(1)>', 'onerror'],
            ['<a href="javascript:alert(1)">x</a>', 'javascript'],
            ['<a href="jav&#x61;script:alert(1)">x</a>', 'script:'],
            ['<a href="  JaVaScRiPt:alert(1)">x</a>', 'script:'],
            ['<p style="background:url(x)" onclick="x()">y</p>', 'onclick'],
            ['<svg onload=alert(1)><circle/></svg>', 'svg'],
            ['<iframe src="//evil"></iframe>', 'iframe'],
            ['<a href="//evil.com">x</a>', 'evil.com'],
            ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
        ];
    }

    public function testSafeHtmlKeepsAllowedFormatting(): void
    {
        $html = '<h2>Returns</h2><p>Within <strong>7 days</strong>. <a href="https://example.com">More</a></p><ul><li>One</li></ul>';
        $out = safe_html($html);
        $this->assertStringContainsString('<h2>Returns</h2>', $out);
        $this->assertStringContainsString('<strong>7 days</strong>', $out);
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('rel="noopener nofollow"', $out);
        $this->assertStringContainsString('<li>One</li>', $out);
    }
}
