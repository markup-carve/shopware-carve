<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Subscriber;

use MarkupCarve\Shopware\Subscriber\CarveCommerceInvalidation;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class CarveReviewInvalidationTest extends TestCase
{
    public function testReviewWriteInvalidatesRenderedAggregates(): void
    {
        $context = Context::createDefaultContext();
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(self::once())->method('invalidate')->with(['carve-reviews']);

        (new CarveCommerceInvalidation($invalidator))->written(new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('product_review', [], $context)]),
            [],
        ));
    }
}
