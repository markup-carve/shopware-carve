<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarvePresentation;
use PHPUnit\Framework\TestCase;

class CarveVideoTest extends TestCase
{
    public function testVideoIsOnlyALocalButtonUntilVisitorActivation(): void
    {
        $converter = new CarveConverter();
        (new CarvePresentation())->register($converter);
        $html = $converter->convert(':video[youtube:ABCDEFGHIJK] :video[youtube:bad]');

        self::assertStringContainsString('data-video-id="ABCDEFGHIJK"', $html);
        self::assertStringContainsString('>Load video</button>', $html);
        self::assertStringContainsString('youtube:bad', $html);
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringNotContainsString('youtube.com', $html);
    }
}
