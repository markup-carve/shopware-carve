<?php declare(strict_types=1);

namespace Carve\Shopware\Tests\Inline;

use Carve\CarveConverter;
use Carve\Shopware\Inline\ProductInlineMatcher;
use PHPUnit\Framework\TestCase;

class ProductInlineMatcherTest extends TestCase
{
    private function convert(string $src, callable $lookup): string
    {
        $converter = new CarveConverter(safeMode: true);
        (new ProductInlineMatcher($lookup))->register($converter);

        return $converter->convert($src);
    }

    public function testKnownSkuRendersLink(): void
    {
        $html = $this->convert(':product[ABC-1]', static fn (string $sku): ?array =>
            $sku === 'ABC-1' ? ['name' => 'Steel Rope', 'url' => '/p/abc-1'] : null);
        self::assertStringContainsString('<a', $html);
        self::assertStringContainsString('/p/abc-1', $html);
        self::assertStringContainsString('Steel Rope', $html);
    }

    public function testUnknownSkuRendersInertText(): void
    {
        $html = $this->convert(':product[NOPE]', static fn (string $sku): ?array => null);
        self::assertStringNotContainsString('<a', $html);
        self::assertStringContainsString('NOPE', $html);
    }

    public function testNonProductColonUntouched(): void
    {
        $html = $this->convert('ratio 10:20 end', static fn (string $sku): ?array => null);
        self::assertStringContainsString('10:20', $html);
    }
}
