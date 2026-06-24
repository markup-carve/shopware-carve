<?php declare(strict_types=1);

namespace Carve\Shopware\Tests\Service;

use Carve\Shopware\Service\CarveRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveRendererTest extends TestCase
{
    private CarveRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new CarveRenderer($this->makeConfigMock(null));
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

    public function testSafeModeCanBeDisabledViaConfig(): void
    {
        $renderer = new CarveRenderer($this->makeConfigMock(false));

        // Raw HTML block (``` =html fence) passes through unescaped when safe mode is off.
        $rawHtmlSource = "``` =html\n<script>alert(2)</script>\n```";
        $html = $renderer->toHtml($rawHtmlSource);

        self::assertStringContainsString('<script>', $html);
    }

    public function testSmartQuotesUsesConfiguredLocale(): void
    {
        // carve-php emits English-style typographic quotes by default, so an "en" locale
        // would look identical whether or not the extension is wired. Use "de" instead:
        // German opens with U+201E (low double quote), which only appears when the
        // SmartQuotesExtension is actually added with the configured locale.
        $renderer = new CarveRenderer($this->makeConfigMock(null, true, 'de'));

        $html = $renderer->toHtml('"hi"');

        // U+201E DOUBLE LOW-9 QUOTATION MARK (German opening quote) proves the extension
        // was added with locale "de".
        self::assertStringContainsString("\u{201E}", $html);
        // The plain ASCII double quote must not survive.
        self::assertStringNotContainsString('"', $html);
    }

    public function testSmartQuotesDisabledKeepsDefaultStyle(): void
    {
        // With smart quotes off, no locale extension is added, so the default
        // English-style opening quote (U+201C) is used and the German low quote
        // (U+201E) must not appear.
        $renderer = new CarveRenderer($this->makeConfigMock(null, false, 'de'));

        $html = $renderer->toHtml('"hi"');

        self::assertStringContainsString("\u{201C}", $html);
        self::assertStringNotContainsString("\u{201E}", $html);
    }

    /**
     * @return SystemConfigService&MockObject
     */
    private function makeConfigMock(
        bool|null $safeModeValue,
        bool|null $smartQuotesValue = null,
        string|null $smartQuotesLocale = null,
    ): SystemConfigService {
        $mock = $this->createMock(SystemConfigService::class);

        $configMap = [
            'ShopwareCarve.config.safeMode' => $safeModeValue,
            'ShopwareCarve.config.smartQuotes' => $smartQuotesValue,
            'ShopwareCarve.config.smartQuotesLocale' => $smartQuotesLocale,
        ];

        $mock->method('get')
            ->willReturnCallback(static function (string $key) use ($configMap): mixed {
                return $configMap[$key] ?? null;
            });

        return $mock;
    }
}
