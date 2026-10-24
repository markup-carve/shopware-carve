<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarvePresentation;
use PHPUnit\Framework\TestCase;

class CarvePresentationTest extends TestCase
{
    public function testBadgeVariantAndTextAreBounded(): void
    {
        $converter = new CarveConverter(safeMode: true);
        (new CarvePresentation())->register($converter);

        $html = $converter->convert(':badge[Sale|danger] :badge[<script>alert(1)</script>|evil]');

        self::assertStringContainsString('<span class="carve-badge carve-badge--danger">Sale</span>', $html);
        self::assertStringContainsString('class="carve-badge carve-badge--info"', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('carve-badge--evil', $html);
    }

    public function testOtherInlineExtensionsKeepTheirRenderer(): void
    {
        $converter = new CarveConverter(safeMode: true);
        (new CarvePresentation())->register($converter);

        self::assertStringContainsString('ext-other', $converter->convert(':other[Text]'));
    }
}
