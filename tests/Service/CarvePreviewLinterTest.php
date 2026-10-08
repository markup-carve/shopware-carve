<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarvePreviewLinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CarvePreviewLinterTest extends TestCase
{
    #[DataProvider('fragmentCases')]
    public function testFragmentWarningsMatchTheVisiblePreview(string $source, string $html, bool $broken): void
    {
        $warnings = (new CarvePreviewLinter())->lint($source, new CarveConverter(), $html);
        self::assertSame($broken, in_array('broken-fragment-link', array_column($warnings, 'rule'), true));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function fragmentCases(): array
    {
        return [
            'generated id' => ['[go](#widget)', '<div id="widget"></div><a href="#widget">go</a>', false],
            'missing target' => ['[go](#missing)', '<a href="#missing">go</a>', true],
            'input name is not an anchor' => ['[go](#missing)', '<input id="field" name="missing"><a href="#missing">go</a>', true],
            'comment is not an anchor' => ['[go](#missing)', '<!-- <div id="missing"> --><a href="#missing">go</a>', true],
            'template is not an anchor' => ['[go](#missing)', '<template><div id="missing"></div></template><a href="#missing">go</a>', true],
            'named anchor' => ['[go](#legacy)', '<a name="legacy"></a><a href="#legacy">go</a>', false],
            'encoded unicode' => ['[go](#caf%C3%A9)', '<div id="café"></div><a href="#caf%C3%A9">go</a>', false],
            'case matters' => ['[go](#widget)', '<div id="Widget"></div><a href="#widget">go</a>', true],
            'top' => ['[go](#TOP)', '<a href="#TOP">go</a>', false],
            'text directive' => ['[go](#widget:~:text=word)', '<div id="widget"></div><a href="#widget:~:text=word">go</a>', false],
            'namespaced target' => ['[go](#widget)', '<div id="carve-ns-widget"></div><a href="#carve-ns-widget">go</a>', false],
            'omitted link' => ['[go](#missing)', '<p>go</p>', false],
            'code is not a link' => ['`[go](#missing)`', '<code>[go](#missing)</code><a href="#missing">other</a>', false],
        ];
    }

    public function testUnicodeBeforeTheLinkKeepsItsSourceByteRange(): void
    {
        $source = 'é [go](#missing)';
        $warnings = (new CarvePreviewLinter())->lint($source, new CarveConverter(), '<p>é <a href="#missing">go</a></p>');
        $warning = array_values(array_filter($warnings, static fn ($warning): bool => $warning->rule === 'broken-fragment-link'))[0];
        self::assertSame(1, $warning->line);
        self::assertSame(3, $warning->column);
        self::assertSame('[go](#missing)', substr($source, $warning->start, $warning->end - $warning->start));
    }
}
