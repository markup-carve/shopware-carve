<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveLiteral;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveSetup;
use PHPUnit\Framework\TestCase;

class CarveLiteralTest extends TestCase
{
    public function testInterpolatedDataCannotIntroduceMarkup(): void
    {
        $value = '[Account](https://example.com/phishing) *Admin* {{ secret.crv }} :product[SKU]';
        $escaped = CarveLiteral::escape($value);
        $html = (new CarveSetup())->render('Dear ' . $escaped);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringNotContainsString('<strong>', $html);
    }

    public function testInterpolatedDataRoundTripsToPlainText(): void
    {
        $value = '[Account](https://example.com/phishing) *Admin* {{ secret.crv }} :product[SKU]';
        self::assertSame('Dear ' . $value, trim(CarveRenderer::write('text', 'Dear ' . CarveLiteral::escape($value))));
    }

    public function testLineBreaksCannotCreateNewBlocks(): void
    {
        $escaped = CarveLiteral::escape("Name\n\n# Heading\r\n<script>alert(1)</script>");
        $html = (new CarveSetup())->render('Dear ' . $escaped);
        self::assertSame(1, substr_count($html, '<p>'));
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringNotContainsString('<script>', $html);
    }
}
