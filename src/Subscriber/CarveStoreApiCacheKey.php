<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Subscriber;

use MarkupCarve\Shopware\Service\CarveStorefrontHost;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CarveStoreApiCacheKey implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        $events = [];
        foreach (
            [
                'Shopware\\Core\\Content\\Category\\Event\\CategoryRouteCacheKeyEvent',
                'Shopware\\Core\\Content\\LandingPage\\Event\\LandingPageRouteCacheKeyEvent',
                'Shopware\\Core\\Content\\Product\\Events\\ProductDetailRouteCacheKeyEvent',
            ] as $event
        ) {
            if (class_exists($event)) {
                $events[$event] = 'varyDomain';
            }
        }

        return $events;
    }

    public function varyDomain(StoreApiRouteCacheKeyEvent $event): void
    {
        $event->addPart('carve-domain-' . hash('sha256', CarveStorefrontHost::resolve($event->getRequest(), $event->getContext())));
    }
}
