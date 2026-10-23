<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use ArrayIterator;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use MarkupCarve\Shopware\Service\CarveStoredSource;
use MarkupCarve\Shopware\Service\CarveStoredSources;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class CarveStoredSourcesTest extends TestCase
{
    public function testFieldSourcesReadTheLiveVersionOfEachTranslation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('iterateAssociative')->with(
            self::stringContains('FROM product_manufacturer_translation WHERE product_manufacturer_version_id = :version'),
            self::callback(static fn (array $params): bool => $params['path'] === '$.carve_manufacturer_body'),
        )->willReturn(new ArrayIterator([['id' => 'aa', 'language' => 'bb', 'source' => "Text.\n"]]));

        $sources = iterator_to_array((new CarveStoredSources($connection))->sources(['manufacturer']), false);

        self::assertEquals([new CarveStoredSource('manufacturer', 'aa', 'bb', 'carve_manufacturer_body', "Text.\n")], $sources);
    }

    public function testCmsSourcesKeepOnlyStaticContent(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('iterateAssociative')->willReturn(new ArrayIterator([
            ['id' => 'a1', 'language' => 'bb', 'config' => json_encode(['content' => ['source' => 'static', 'value' => 'Static.']])],
            ['id' => 'a2', 'language' => 'bb', 'config' => json_encode(['content' => ['source' => 'mapped', 'value' => 'product.name']])],
            ['id' => 'a3', 'language' => 'bb', 'config' => null],
        ]));

        $sources = iterator_to_array((new CarveStoredSources($connection))->sources(['cms']), false);

        self::assertCount(1, $sources);
        self::assertSame('Static.', $sources[0]->source);
        self::assertSame('cms:a1:bb:content', $sources[0]->label());
    }

    public function testDescriptionsReportExistingCarveSource(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('iterateAssociative')->willReturn(new ArrayIterator([
            ['id' => 'a1', 'language' => 'bb', 'description' => '<p>A</p>', 'carve' => null],
            ['id' => 'a2', 'language' => 'bb', 'description' => '<p>B</p>', 'carve' => 'null'],
            ['id' => 'a3', 'language' => 'bb', 'description' => '<p>C</p>', 'carve' => 'Kept.'],
        ]));

        $rows = iterator_to_array((new CarveStoredSources($connection))->descriptions(['product']), false);

        self::assertSame([null, null, 'Kept.'], array_column($rows, 'carve'));
        self::assertSame('<p>A</p>', $rows[0]['source']->source);
    }

    public function testWriteGoesThroughTheTranslationOfTheDalEntity(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $repository->expects(self::once())->method('update')->with([
            [
                'id' => 'aa',
                'translations' => ['bb' => ['customFields' => ['carve_manufacturer_body' => "Carve.\n"]]],
            ],
        ], $context);
        $sources = new CarveStoredSources($this->createStub(Connection::class), ['product_manufacturer' => $repository]);

        $sources->write(new CarveStoredSource('manufacturer', 'aa', 'bb', 'carve_manufacturer_body', '<p>x</p>'), "Carve.\n", $context);
    }

    public function testAnUnknownEntityIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new CarveStoredSources($this->createStub(Connection::class)))->sources(['order']));
    }
}
