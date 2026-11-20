<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Shopware\Service\CarveHookCall;
use MarkupCarve\Shopware\Service\CarveHostExtensions;
use MarkupCarve\Shopware\Service\CarveLintWarning;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveSetup;
use MarkupCarve\Shopware\Service\CarveTree;
use MarkupCarve\Shopware\Service\CarveWriterUnavailable;
use PHPUnit\Framework\TestCase;

class CarveSetupTest extends TestCase
{
    public function testTheFirstHookWithHtmlWinsAndANullKeepsTheCoreRendering(): void
    {
        $setup = new CarveSetup();
        $setup->on('inline', 'badge', static fn (CarveHookCall $call): ?string => null);
        $setup->on('inline', 'badge', static fn (CarveHookCall $call): string => '<b>' . $call->argument() . '|' . $call->attribute('variant') . '</b>');
        $setup->on('inline', 'other', static fn (CarveHookCall $call): ?string => null);

        $html = $setup->render(":badge[New *x*]{variant=ok} :other[y]{.k}\n");

        self::assertSame('<p><b>New x|ok</b> <span class="ext-other k">y</span></p>', $html);
    }

    public function testDivAndFenceHooksReadTitleChildrenAndBody(): void
    {
        $setup = new CarveSetup();
        $setup->on('div', 'box', static fn (CarveHookCall $call): string => '<section title="' . $call->title() . '">' . $call->childrenHtml() . '</section>');
        $setup->on('fence', 'raw', static fn (CarveHookCall $call): string => '<pre data-raw>' . htmlspecialchars($call->argument()) . '</pre>');

        $html = $setup->render("::: box \"Q?\"\nBody *b*\n:::\n\n```raw\na<b\n```\n");

        self::assertStringContainsString('<section title="Q?"><p>Body <strong>b</strong></p></section>', $html);
        self::assertStringContainsString('<pre data-raw>a&lt;b</pre>', $html);
    }

    public function testAHookedTreeKeepsItsClaimedNodeForPreparation(): void
    {
        $setup = new CarveSetup();
        $setup->on('inline', 'product', static fn (CarveHookCall $call): string => $call->argument());
        $found = [];
        CarveTree::walk($setup->parse("::: note\n:product[A-1]{.card} and :product[#B2]\n:::\n"), static function ($node) use (&$found): void {
            $core = CarveTree::core($node);
            if ($core->type === 'inline_extension' && ($core->f['name'] ?? null) === 'product') {
                $found[] = CarveTree::text($core) . (CarveTree::hasClass($core, 'card') ? '+card' : '');
            }
        });

        self::assertSame(['A-1+card', '#B2'], $found);
    }

    public function testExternalLinksGetRelAndTargetAndLocalLinksStayPlain(): void
    {
        $setup = new CarveSetup();
        (new CarveHostExtensions())->externalLinks($setup, 'nofollow noopener', '_blank');

        $html = $setup->render("[out](https://example.com) [in](/catalog) [own](https://example.com){rel=me}\n");

        self::assertStringContainsString('<a href="https://example.com" rel="nofollow noopener" target="_blank">out</a>', $html);
        self::assertStringContainsString('<a href="/catalog">in</a>', $html);
        self::assertStringContainsString('rel="me" target="_blank">own</a>', $html);
    }

    public function testLocaleQuotesKeepApostrophesAndCode(): void
    {
        $setup = new CarveSetup();
        (new CarveHostExtensions())->smartQuotes($setup, 'de');

        $html = $setup->render("\"Hallo\" 'Welt' geht's `\"x\"`\n");

        self::assertSame("<p>\u{201E}Hallo\u{201C} \u{201A}Welt\u{2018} geht\u{2019}s <code>\"x\"</code></p>", $html);
    }

    public function testDetailsDisclosureUsesItsTitle(): void
    {
        $setup = new CarveSetup();
        (new CarveHostExtensions())->register($setup);

        self::assertSame(
            '<details class="details"><summary>More</summary><p>Body</p></details>',
            $setup->render("::: details \"More\"\nBody\n:::\n"),
        );
    }

    public function testAListTableWithMoreHeaderRowsThanRowsStaysWellFormed(): void
    {
        $setup = new CarveSetup();
        (new CarveHostExtensions())->register($setup);

        self::assertSame(
            '<table><thead><tr><th scope="col">A</th><th scope="col">B</th></tr></thead></table>',
            $setup->render("{header-rows=3}\n::: list-table\n- - A\n  - B\n:::\n"),
        );
    }

    public function testMissingWritersFailWithAClearError(): void
    {
        if (Carve::writers()->has('text')) {
            self::markTestSkipped('carve-php registers the text writer now.');
        }
        $this->expectException(CarveWriterUnavailable::class);
        $this->expectExceptionMessage('The Carve "missing" writer is not available yet in carve-php 0.2.');

        CarveRenderer::write('missing', '*bold*');
    }

    public function testLintWarningsCountCharacterColumns(): void
    {
        $warning = CarveLintWarning::at("é line\nab cd", 10, 12, 'rule', 'message');

        self::assertSame([2, 3], [$warning->line, $warning->column]);
    }
}
