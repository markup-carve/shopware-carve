<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use PHPUnit\Framework\TestCase;

class CarveLayoutTest extends TestCase
{
    public function testButtonAndCtaUseSafeLinks(): void
    {
        $converter = new CarveConverter(safeMode: true);
        $html = $converter->convert("[Shop](https://example.com){.button}\n\n::: cta\n[Browse](/catalog)\n:::\n");

        self::assertStringContainsString('<a href="https://example.com" class="button">Shop</a>', $html);
        self::assertStringContainsString('<div class="cta">', $html);
        self::assertStringContainsString('<a href="/catalog">Browse</a>', $html);
    }

    public function testNestedCardsAndUspListKeepTheirClasses(): void
    {
        $converter = new CarveConverter(safeMode: true);
        $html = $converter->convert("::: columns\n::: card\nFast delivery\n:::\n::: card\nEasy returns\n:::\n:::\n\n::: usp\n- Repairable\n- Reusable\n:::\n");

        self::assertStringContainsString('<div class="columns">', $html);
        self::assertSame(2, substr_count($html, '<div class="card">'));
        self::assertStringContainsString('<div class="usp">', $html);
        self::assertStringContainsString('<li>Repairable</li>', $html);
    }
}
