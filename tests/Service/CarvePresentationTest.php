<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarvePresentation;
use MarkupCarve\Shopware\Service\CarveSetup;
use PHPUnit\Framework\TestCase;

class CarvePresentationTest extends TestCase
{
    public function testBadgeVariantAndTextAreBounded(): void
    {
        $converter = new CarveSetup();
        (new CarvePresentation())->register($converter);

        $html = $converter->render(':badge[Sale|danger] :badge[<script>alert(1)</script>|evil]');

        self::assertStringContainsString('<span class="carve-badge carve-badge--danger">Sale</span>', $html);
        self::assertStringContainsString('class="carve-badge carve-badge--info"', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('carve-badge--evil', $html);
    }

    public function testOtherInlineExtensionsKeepTheirRenderer(): void
    {
        $converter = new CarveSetup();
        (new CarvePresentation())->register($converter);

        self::assertStringContainsString('ext-other', $converter->render(':other[Text]'));
    }
}
