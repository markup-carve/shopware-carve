<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Subscriber;

use MarkupCarve\Shopware\Subscriber\CarveStoreApiCacheKey;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

class CarveStoreApiCacheKeyTest extends TestCase
{
    public function testDifferentAssignedDomainsGetDifferentRouteCacheKeys(): void
    {
        $domains = [];
        foreach (['https://shop.example', 'https://other.example'] as $url) {
            $domain = new SalesChannelDomainEntity();
            $domain->setUniqueIdentifier(md5($url));
            $domain->setUrl($url);
            $domain->setLanguageId(str_repeat('a', 32));
            $domains[] = $domain;
        }
        $channel = new SalesChannelEntity();
        $channel->setDomains(new SalesChannelDomainCollection($domains));
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn(str_repeat('a', 32));
        $context->method('getSalesChannel')->willReturn($channel);
        $keys = [];
        foreach ($domains as $domain) {
            $request = new Request();
            $request->headers->set('sw-storefront-url', $domain->getUrl());
            $event = new StoreApiRouteCacheKeyEvent(['core-key'], $request, $context, null);
            (new CarveStoreApiCacheKey())->varyDomain($event);
            $keys[] = $event->getParts();
        }
        self::assertNotSame($keys[0], $keys[1]);
        self::assertSame('core-key', $keys[0][0]);
    }
}
