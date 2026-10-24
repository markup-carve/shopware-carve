<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarvePresentation;
use PHPUnit\Framework\TestCase;

class CarveInteractiveWidgetsTest extends TestCase
{
    private function convert(string $source): string
    {
        $converter = new CarveConverter(safeMode: true);
        (new CarvePresentation())->register($converter);

        return $converter->convert($source);
    }

    public function testCouponAndCountdownProvideReadableFallbacks(): void
    {
        $html = $this->convert(':coupon[SAVE-20] :countdown[2027-07-01]');

        self::assertStringContainsString('<code>SAVE-20</code>', $html);
        self::assertStringContainsString('data-code="SAVE-20"', $html);
        self::assertStringContainsString('aria-label="Copy coupon SAVE-20"', $html);
        self::assertStringContainsString('<time class="carve-countdown" datetime="2027-07-01" data-target="2027-07-01">2027-07-01</time>', $html);
    }

    public function testInvalidValuesDoNotBecomeActionableMarkup(): void
    {
        $html = $this->convert(':coupon[CODE SPACE] :countdown[2027-02-30]');

        self::assertStringNotContainsString('data-code=', $html);
        self::assertStringNotContainsString('data-target=', $html);
        self::assertStringContainsString('CODE SPACE', $html);
        self::assertStringContainsString('2027-02-30', $html);
    }
}
