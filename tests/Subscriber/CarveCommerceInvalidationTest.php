<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Subscriber;

use MarkupCarve\Shopware\Subscriber\CarveCommerceInvalidation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class CarveCommerceInvalidationTest extends TestCase
{
    /**
     * @param string $entity
     * @param array<string> $tags
     *
     * @return void
     */
    #[DataProvider('writes')]
    public function testWrittenEntityInvalidatesItsTag(string $entity, array $tags): void
    {
        $context = Context::createDefaultContext();
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($tags === [] ? self::never() : self::once())->method('invalidate')->with($tags);

        (new CarveCommerceInvalidation($invalidator))->written(new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent($entity, [], $context)]),
            [],
        ));
    }

    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function writes(): array
    {
        return [
            'legal layout' => ['cms_page', ['carve-resources']],
            'legal layout name' => ['cms_page_translation', ['carve-resources']],
            'category' => ['category', ['carve-resources']],
            'product' => ['product', ['carve-products-missing']],
            'unrelated' => ['order', []],
        ];
    }
}
