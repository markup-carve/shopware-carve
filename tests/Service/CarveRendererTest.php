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

    public function testSmartQuotesAcceptsStringTrueFromCliConfig(): void
    {
        // Shopware's system:config:set and config imports store booleans as strings.
        // The configBool() helper must accept string "true" just as it does real bool true.
        // When smartQuotes is the string "true" with locale "de", the German low quote (U+201E)
        // must appear, proving the SmartQuotesExtension was added with the configured locale.
        $renderer = new CarveRenderer($this->makeConfigMock(null, 'true', 'de'));

        $html = $renderer->toHtml('"x"');

        // String "true" must enable the extension, producing German low-9 quote.
        self::assertStringContainsString("\u{201E}", $html);
        self::assertStringNotContainsString('"', $html);
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

    public function testMermaidEnabledEmitsHydrationMarkup(): void
    {
        $renderer = new CarveRenderer($this->makeConfigMock(null, null, null, true, null));

        $html = $renderer->toHtml("``` mermaid\ngraph LR\nA --> B\n```");

        // FencedRenderExtension::mermaid() emits <pre class="mermaid">...</pre>
        self::assertStringContainsString('<pre class="mermaid">', $html);
        // Must NOT fall back to a plain code block
        self::assertStringNotContainsString('<code class="language-mermaid">', $html);
    }

    public function testMermaidDisabledByDefaultYieldsCodeBlock(): void
    {
        // Default renderer (enableMermaid = null/false) must not emit the hydration markup
        $html = $this->renderer->toHtml("``` mermaid\ngraph LR\nA --> B\n```");

        self::assertStringNotContainsString('<pre class="mermaid">', $html);
        self::assertStringContainsString('<code', $html);
    }

    public function testChartEnabledEmitsHydrationMarkup(): void
    {
        $renderer = new CarveRenderer($this->makeConfigMock(null, null, null, null, true));

        $json = '{"type":"bar","data":{"labels":["A"],"datasets":[{"data":[1]}]}}';
        $html = $renderer->toHtml("``` chart\n{$json}\n```");

        // FencedRenderExtension::chart() emits <div class="chart"><script type="application/json">...</script></div>
        self::assertStringContainsString('<div class="chart">', $html);
        self::assertStringContainsString('<script type="application/json">', $html);
        self::assertStringNotContainsString('<code class="language-chart">', $html);
    }

    public function testChartsDisabledByDefaultYieldsCodeBlock(): void
    {
        $html = $this->renderer->toHtml("``` chart\n{\"type\":\"bar\"}\n```");

        self::assertStringNotContainsString('<div class="chart">', $html);
        self::assertStringContainsString('<code', $html);
    }

    public function testSpoilerBlockRendersAsNativeDetails(): void
    {
        // SpoilerExtension converts ::: spoiler "Title" to a native <details> disclosure
        // widget that is collapsed by default - no JS required.
        $html = $this->renderer->toHtml("::: spoiler \"My Secret\"\nHidden content.\n:::");

        self::assertStringContainsString('<details class="spoiler">', $html);
        self::assertStringContainsString('<summary>My Secret</summary>', $html);
        self::assertStringContainsString('Hidden content.', $html);
        // Must not fall back to a plain div wrapper.
        self::assertStringNotContainsString('<div class="spoiler">', $html);
    }

    public function testSpoilerInlineRendersAsSpan(): void
    {
        // SpoilerExtension converts :spoiler[text] to <span class="spoiler"> (CSS blur reveal,
        // no JS). Must not fall back to the generic ext-spoiler class.
        $html = $this->renderer->toHtml('Before :spoiler[secret text] after.');

        self::assertStringContainsString('<span class="spoiler">secret text</span>', $html);
        self::assertStringNotContainsString('ext-spoiler', $html);
    }

    public function testProfileCommentDisallowsHeadings(): void
    {
        // The 'comment' preset allowBlock list does not include 'heading', so a heading in the
        // source must NOT produce an <h1>-<h6> tag - it degrades to plain text per the profile's
        // default ACTION_TO_TEXT action. With profile 'none' the same source renders an <h1>.
        $renderer = new CarveRenderer($this->makeConfigMock(null, null, null, null, null, 'comment'));
        $source = '# Section Heading';

        $html = $renderer->toHtml($source);

        // Heading tag must not appear in profiled output
        self::assertStringNotContainsString('<h1', $html);
        // The heading text itself must survive (degraded to plain text, not stripped)
        self::assertStringContainsString('Section Heading', $html);
    }

    public function testProfileNoneAllowsHeadings(): void
    {
        // With profile 'none' (the default / no restriction), headings render normally.
        $renderer = new CarveRenderer($this->makeConfigMock(null, null, null, null, null, 'none'));
        $source = '# Section Heading';

        $html = $renderer->toHtml($source);

        self::assertStringContainsString('<h1', $html);
        self::assertStringContainsString('Section Heading', $html);
    }

    public function testCodeGroupRendersRadioTabMarkup(): void
    {
        // CodeGroupExtension converts ::: code-group with labeled fences to a CSS radio-tab
        // pattern: all inputs+labels first, then all panels. Tab switching is CSS-only - no JS.
        $source = implode("\n", [
            '::: code-group',
            '``` bash [npm]',
            'npm install',
            '```',
            '``` bash [pnpm]',
            'pnpm install',
            '```',
            ':::',
        ]);

        $html = $this->renderer->toHtml($source);

        // Radio inputs for CSS switching
        self::assertStringContainsString('class="code-group-radio"', $html);
        // Labels carry the tab titles - the key fix: [npm]/[pnpm] must not be lost
        self::assertStringContainsString('class="code-group-label"', $html);
        self::assertStringContainsString('>npm<', $html);
        self::assertStringContainsString('>pnpm<', $html);
        // Panels wrap the code blocks
        self::assertStringContainsString('class="code-group-panel"', $html);
        // Must not fall back to a plain fenced code block without group structure
        self::assertStringNotContainsString('[npm]', $html);
    }

    // -----------------------------------------------------------------------
    // toHtmlUgc tests
    // -----------------------------------------------------------------------

    public function testUgcBlankReturnsEmpty(): void
    {
        self::assertSame('', $this->renderer->toHtmlUgc(null));
        self::assertSame('', $this->renderer->toHtmlUgc('   '));
    }

    public function testUgcStripsHeadingEvenWhenGlobalProfileIsNone(): void
    {
        // toHtmlUgc must always apply the comment profile, regardless of global config.
        // Global profile = 'none' and allowRawHtml = true are both set, yet headings
        // must still be stripped because toHtmlUgc forces comment profile + safe mode.
        $renderer = new CarveRenderer(
            $this->makeConfigMock(allowRawHtmlValue: true, profile: 'none'),
        );

        $html = $renderer->toHtmlUgc('# Heading text');

        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('Heading text', $html);
    }

    public function testUgcEscapesRawScriptEvenWhenAllowRawHtmlIsTrue(): void
    {
        // toHtmlUgc must force safe mode on so raw HTML stays escaped even if
        // allowRawHtml is globally enabled.
        $renderer = new CarveRenderer(
            $this->makeConfigMock(allowRawHtmlValue: true),
        );

        $rawHtmlSource = "``` =html\n<script>alert('xss')</script>\n```";
        $html = $renderer->toHtmlUgc($rawHtmlSource);

        self::assertStringNotContainsString('<script>', $html);
    }

    public function testUgcRendersBasicInlineFormatting(): void
    {
        // Bold and italic are allowed by the comment profile.
        $html = $this->renderer->toHtmlUgc('*bold* and _italic_');

        self::assertStringContainsString('<strong>bold</strong>', $html);
    }

    /**
     * @return SystemConfigService&MockObject
     */
    private function makeConfigMock(
        bool|null $allowRawHtmlValue,
        bool|string|null $smartQuotesValue = null,
        string|null $smartQuotesLocale = null,
        bool|null $enableMermaid = null,
        bool|null $enableCharts = null,
        string|null $profile = null,
    ): SystemConfigService {
        $mock = $this->createMock(SystemConfigService::class);

        $configMap = [
            'ShopwareCarve.config.allowRawHtml' => $allowRawHtmlValue,
            'ShopwareCarve.config.smartQuotes' => $smartQuotesValue,
            'ShopwareCarve.config.smartQuotesLocale' => $smartQuotesLocale,
            'ShopwareCarve.config.enableMermaid' => $enableMermaid,
            'ShopwareCarve.config.enableCharts' => $enableCharts,
            'ShopwareCarve.config.profile' => $profile,
        ];

        $mock->method('get')
            ->willReturnCallback(static function (string $key) use ($configMap): mixed {
                return $configMap[$key] ?? null;
            });

        return $mock;
    }
}
