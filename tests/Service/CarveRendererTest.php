<?php declare(strict_types=1);

namespace Carve\Shopware\Tests\Service;

use Carve\Shopware\Service\CarveRenderer;
use PHPUnit\Framework\TestCase;

class CarveRendererTest extends TestCase
{
    private CarveRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new CarveRenderer();
    }

    public function testBlankReturnsEmpty(): void
    {
        self::assertSame('', $this->renderer->toHtml(null));
        self::assertSame('', $this->renderer->toHtml('   '));
    }

    public function testRendersBasicMarkup(): void
    {
        $html = $this->renderer->toHtml('*bold* text');
        self::assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function testNeutralizesJavascriptScheme(): void
    {
        $html = $this->renderer->toHtml('[x](javascript:alert(1))');
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testEscapesRawScript(): void
    {
        $html = $this->renderer->toHtml('<script>alert(2)</script>');
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testPlainTextStripsFormatting(): void
    {
        $text = $this->renderer->toText('*bold*');
        self::assertStringNotContainsString('<strong>', $text);
        self::assertStringContainsString('bold', $text);
    }

    public function testMarkdownRoundTrips(): void
    {
        $md = $this->renderer->toMarkdown('*bold*');
        self::assertStringContainsString('bold', $md);
    }
}
