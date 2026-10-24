<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarvePresentation;
use PHPUnit\Framework\TestCase;

class CarveDisclosureTest extends TestCase
{
    private function convert(string $source): string
    {
        $converter = new CarveConverter(safeMode: true);
        (new CarvePresentation())->register($converter);

        return $converter->convert($source);
    }

    public function testFaqAndAccordionRenderNativeDisclosures(): void
    {
        $html = $this->convert("::: faq \"Shipping?\"\nOrders arrive.\n:::\n\n::: accordion \"Care\"\nWash gently.\n:::\n");

        self::assertStringContainsString('<details class="carve-faq"><summary>Shipping?</summary>', $html);
        self::assertStringContainsString('<p>Orders arrive.</p>', $html);
        self::assertStringContainsString('<details class="carve-accordion"><summary>Care</summary>', $html);
        self::assertStringContainsString('<p>Wash gently.</p>', $html);
        self::assertSame(2, substr_count($html, '</details>'));
    }

    public function testHeaderIsEscapedAndUnrelatedBlocksKeepCoreRendering(): void
    {
        $html = $this->convert("::: faq \"<img src=x>\"\nSafe body.\n:::\n\n::: card\nCard body.\n:::\n");

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<div class="card">', $html);
        self::assertStringContainsString('<p>Card body.</p>', $html);
    }
}
