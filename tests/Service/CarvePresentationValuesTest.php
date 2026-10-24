<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarvePresentation;
use PHPUnit\Framework\TestCase;

class CarvePresentationValuesTest extends TestCase
{
    private function convert(string $source): string
    {
        $converter = new CarveConverter(safeMode: true);
        (new CarvePresentation())->register($converter);

        return $converter->convert($source);
    }

    public function testRatingAndProgressUseBoundedValues(): void
    {
        $html = $this->convert(':rating-stars[4.5] :progress[70] :progress[101] :rating-stars[-1]');

        self::assertStringContainsString('aria-label="4.5 out of 5 stars"', $html);
        self::assertStringContainsString('style="width: 90%"', $html);
        self::assertStringContainsString('<progress class="carve-progress" value="70" max="100">70%</progress>', $html);
        self::assertStringNotContainsString('value="101"', $html);
        self::assertStringNotContainsString('width: -20%', $html);
    }

    public function testColorRejectsCssInjectionAndUnitEscapesText(): void
    {
        $html = $this->convert(':color[#ff8800] :color[#fff; background:url(x)] :unit[6 mm] :unit[6 <img src=x>]');

        self::assertStringContainsString('style="background-color: #ff8800"', $html);
        self::assertSame(1, substr_count($html, 'background-color:'));
        self::assertStringContainsString('<span class="carve-unit">6&#8239;mm</span>', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function testPaletteKeepsTheCoreBlockClass(): void
    {
        self::assertStringContainsString('<div class="palette">', $this->convert("::: palette\n:color[#abc]\n:::"));
    }
}
