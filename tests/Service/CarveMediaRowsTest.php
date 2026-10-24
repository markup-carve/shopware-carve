<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use PHPUnit\Framework\TestCase;

class CarveMediaRowsTest extends TestCase
{
    public function testMediaBlocksKeepTheClassesAndSeparateItemsUsedByStorefrontStyles(): void
    {
        $source = "::: trust-badges\n:media[one]{alt=\"Payment\"}\n\n:media[two]{alt=\"Shipping\"}\n:::"
            . "\n\n::: gallery\n:media[three]{alt=\"Front\"}\n\n:media[four]{alt=\"Side\"}\n:::";
        $converter = new CarveConverter();
        $html = $converter->render($converter->parse($source));

        self::assertStringContainsString('<div class="trust-badges">', $html);
        self::assertStringContainsString('<div class="gallery">', $html);
        self::assertSame(4, substr_count($html, '<p><span class="ext-media"'));
        self::assertStringContainsString('alt="Front"', $html);
    }
}
