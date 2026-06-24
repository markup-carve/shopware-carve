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

    public function testRawHtmlAllowedViaConfig(): void
    {
        $renderer = new CarveRenderer($this->makeConfigMock(true));

        // Raw HTML block (``` =html fence) passes through unescaped when allowRawHtml is true.
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

    public function testAdmonitionRendersWithCorrectClasses(): void
    {
        // AdmonitionExtension emits <div class="admonition warning" role="alert">
        // with a <p class="admonition-title"> child when defaultTitle is true (the default).
        $html = $this->renderer->toHtml("::: warning\nWatch out!\n:::");

        self::assertStringContainsString('class="admonition warning"', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('class="admonition-title"', $html);
    }

    public function testListTableRendersAsTable(): void
    {
        // ListTableExtension converts ::: list-table blocks to real <table> markup.
        $source = implode("\n", [
            '{header-rows=1}',
            '::: list-table',
            '- - Product',
            '  - Price',
            '- - Widget',
            '  - 9.99',
            ':::',
        ]);

        $html = $this->renderer->toHtml($source);

        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('<th>', $html);
        self::assertStringContainsString('<td>', $html);
        // Must not fall back to a plain div wrapper.
        self::assertStringNotContainsString('class="list-table"', $html);
    }

    /**
     * @return SystemConfigService&MockObject
     */
    private function makeConfigMock(
        bool|null $allowRawHtmlValue,
        bool|null $smartQuotesValue = null,
        string|null $smartQuotesLocale = null,
    ): SystemConfigService {
        $mock = $this->createMock(SystemConfigService::class);

        $configMap = [
            'ShopwareCarve.config.allowRawHtml' => $allowRawHtmlValue,
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
