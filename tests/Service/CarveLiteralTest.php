<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveLiteral;
use PHPUnit\Framework\TestCase;

class CarveLiteralTest extends TestCase
{
    public function testInterpolatedDataCannotIntroduceMarkup(): void
    {
        $value = '[Account](https://example.com/phishing) *Admin* {{ secret.crv }} :product[SKU]';
        $escaped = CarveLiteral::escape($value);
        $html = (new CarveConverter(safeMode: true))->convert('Dear ' . $escaped);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringNotContainsString('<strong>', $html);
        self::assertSame('Dear ' . $value, trim(CarveConverter::plainText()->convert('Dear ' . $escaped)));
    }

    public function testLineBreaksCannotCreateNewBlocks(): void
    {
        $escaped = CarveLiteral::escape("Name\n\n# Heading\r\n<script>alert(1)</script>");
        $html = (new CarveConverter(safeMode: true))->convert('Dear ' . $escaped);
        self::assertSame(1, substr_count($html, '<p>'));
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringNotContainsString('<script>', $html);
    }
}
