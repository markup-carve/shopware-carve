<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveConverterFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveLocalDataTest extends TestCase
{
    public function testTrustedContentRendersBoundedSeriesAndEscapedCsvCells(): void
    {
        $factory = new CarveConverterFactory($this->createStub(SystemConfigService::class));
        $html = $factory->create()->convert(":spark[1,4,2,8]\n\n```csv\nName,Qty\n\"A,<B>\",2\n```");

        self::assertStringContainsString('class="carve-spark"', $html);
        self::assertStringContainsString('points="0,24 33.3,13.7 66.7,20.6 100,0"', $html);
        self::assertStringContainsString('<th scope="col">Name</th>', $html);
        self::assertStringContainsString('<td>A,&lt;B&gt;</td>', $html);
        self::assertStringNotContainsString('<B>', $html);
    }

    public function testInvalidDataKeepsReadableSource(): void
    {
        $factory = new CarveConverterFactory($this->createStub(SystemConfigService::class));
        $html = $factory->create()->convert(":spark[1,unsafe]\n\n```csv\nName,Qty\nA\n```");

        self::assertStringContainsString('1,unsafe', $html);
        self::assertStringNotContainsString('class="carve-spark"', $html);
        self::assertStringContainsString('language-csv', $html);
        self::assertStringNotContainsString('carve-csv-table', $html);
    }
}
