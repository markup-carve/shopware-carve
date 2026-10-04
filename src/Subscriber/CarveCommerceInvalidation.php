<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Subscriber;

use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CarveCommerceInvalidation implements EventSubscriberInterface
{
    public function __construct(private readonly CacheInvalidator $invalidator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [InvalidateProductCache::class => 'invalidate', EntityWrittenContainerEvent::class => 'written'];
    }

    public function written(EntityWrittenContainerEvent $event): void
    {
        $tags = [];
        foreach (['product', 'product_translation', 'product_visibility'] as $entity) {
            if ($event->getEventByEntityName($entity) !== null) {
                $tags[] = 'carve-products-missing';
            }
        }
        foreach (['category', 'category_translation', 'product_manufacturer', 'product_manufacturer_translation', 'media', 'media_translation', 'media_thumbnail', 'cms_page', 'cms_page_translation'] as $entity) {
            if ($event->getEventByEntityName($entity) !== null) {
                $tags[] = 'carve-resources';
            }
        }
        if ($event->getEventByEntityName('snippet') !== null) {
            $tags[] = 'carve-snippets';
        }
        if ($tags !== []) {
            $this->invalidator->invalidate(array_values(array_unique($tags)));
        }
    }

    public function invalidate(): void
    {
        // Includes unresolved SKUs, so publishing a product also refreshes those pages.
        $this->invalidator->invalidate(['carve-products-missing']);
    }
}
