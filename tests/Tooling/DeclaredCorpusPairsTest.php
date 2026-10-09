<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Tooling;

use MarkupCarve\Shopware\Tooling\DeclaredCorpusPairs;
use PHPUnit\Framework\TestCase;

class DeclaredCorpusPairsTest extends TestCase
{
    /**
     * The engine-drift population gate used to count `::: compare` OPEN LINES, so a
     * block holding several pairs reported one. This page holds three.
     */
    public function testCountsEveryCarveFenceInACompareBlock(): void
    {
        $page = implode("\n", [
            '::: compare',
            '```carve',
            'one',
            '```',
            '```html',
            '<p>one</p>',
            '```',
            '````carve',
            '```carve',
            'nested, not a pair',
            '```',
            '````',
            '```html',
            '<pre>two</pre>',
            '```',
            '```carve',
            'three',
            '```',
            '```html',
            '<p>three</p>',
            '```',
            ':::',
            '```carve',
            'outside any block',
            '```',
        ]);

        $this->assertSame(3, DeclaredCorpusPairs::countInSource($page));
    }

    public function testACarveFenceOutsideAnyBlockDeclaresNoPair(): void
    {
        $this->assertSame(0, DeclaredCorpusPairs::countInSource("```carve\nx\n```\n"));
    }

    public function testABlockMarkerLongerThanThreeColonsClosesOnlyOnItsOwnRun(): void
    {
        $page = implode("\n", [
            ':::: compare',
            '```carve',
            'one',
            '```',
            ':::',
            '```carve',
            'still inside',
            '```',
            '::::',
            '```carve',
            'outside',
            '```',
        ]);

        $this->assertSame(2, DeclaredCorpusPairs::countInSource($page));
    }
}
