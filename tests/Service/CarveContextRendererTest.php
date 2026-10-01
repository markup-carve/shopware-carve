<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Shopware\Service\CarveCommerce;
use MarkupCarve\Shopware\Service\CarveContextRenderer;
use MarkupCarve\Shopware\Service\CarveConverterFactory;
use MarkupCarve\Shopware\Service\CarveRenderer;
use MarkupCarve\Shopware\Service\CarveResources;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CarveContextRendererTest extends TestCase
{
    public function testStorefrontKeepsCoreRequestUrlReplacement(): void
    {
        $request = new Request();
        $request->attributes->set('_routeScope', ['storefront']);
        $seo = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seo->expects(self::never())->method('replace');
        self::assertSame("<p>Source</p>\n", $this->renderer($seo, $request)->toHtml('Source', $this->context()));
    }

    public function testApiUsesMatchingConfiguredDomainForRequestedStorefrontUrl(): void
    {
        $request = new Request();
        $request->headers->set('sw-storefront-url', 'https://shop.example/de');
        $context = $this->context();
        $seo = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seo->expects(self::once())->method('replace')->with("<p>Source</p>\n", 'https://shop.example/de', $context)->willReturn('Resolved');
        self::assertSame('Resolved', $this->renderer($seo, $request)->toHtml('Source', $context));
    }

    public function testUnassignedHeaderCannotInjectAnArbitraryHost(): void
    {
        $request = new Request();
        $request->headers->set('sw-storefront-url', 'https://attacker.example/');
        $context = $this->context();
        $seo = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seo->expects(self::once())->method('replace')->with("<p>Source</p>\n", 'http://shop.example/de', $context)->willReturn('Resolved');
        self::assertSame('Resolved', $this->renderer($seo, $request)->toHtml('Source', $context));
    }

    private function renderer(SeoUrlPlaceholderHandlerInterface $seo, Request $request): CarveContextRenderer
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturn(null);
        $locales = $this->createStub(LanguageLocaleCodeProvider::class);
        $locales->method('getLocaleForLanguageId')->willReturn('de-DE');
        $requests = new RequestStack();
        $requests->push($request);

        return new CarveContextRenderer(
            new CarveRenderer($config),
            new CarveConverterFactory($config),
            $this->createStub(CarveCommerce::class),
            $locales,
            $this->createStub(CarveResources::class),
            $seo,
            $requests,
        );
    }

    private function context(): SalesChannelContext
    {
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn(str_repeat('a', 32));
        $context->method('getSalesChannelId')->willReturn(str_repeat('b', 32));
        $domains = [];
        foreach (['http://shop.example/de', 'https://shop.example/de'] as $url) {
            $domain = new SalesChannelDomainEntity();
            $domain->setUniqueIdentifier(md5($url));
            $domain->setUrl($url);
            $domain->setLanguageId(str_repeat('a', 32));
            $domains[] = $domain;
        }
        $channel = new SalesChannelEntity();
        $channel->setDomains(new SalesChannelDomainCollection($domains));
        $context->method('getSalesChannel')->willReturn($channel);

        return $context;
    }
}
