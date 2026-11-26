<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarvePresentation;
use MarkupCarve\Shopware\Service\CarveSetup;
use PHPUnit\Framework\TestCase;

class CarveVideoTest extends TestCase
{
    public function testVideoIsOnlyALocalButtonUntilVisitorActivation(): void
    {
        $converter = new CarveSetup();
        (new CarvePresentation())->register($converter);
        $html = $converter->render(':video[youtube:ABCDEFGHIJK] :video[youtube:bad]');

        self::assertStringContainsString('data-video-id="ABCDEFGHIJK"', $html);
        self::assertStringContainsString('>Load video</button>', $html);
        self::assertStringContainsString('youtube:bad', $html);
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringNotContainsString('youtube.com', $html);
    }
}
