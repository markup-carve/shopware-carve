<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveResources;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CarvePublicValuesTest extends TestCase
{
    public function testOnlyAllowlistedConfigAndBundlePathsResolve(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects(self::once())->method('get')->with('core.basicInformation.shopName', 'channel')->willReturn('Shop <safe>');
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getSalesChannelId')->willReturn('channel');
        $assets = new Packages();
        $assets->addPackage('@ShopwareCarve', new UrlPackage('https://assets.example.test/bundles/shopwarecarve', new EmptyVersionStrategy()));
        $resources = new CarveResources(
            $this->createStub(EntityRepository::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(SalesChannelRepository::class),
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $config,
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(EntityRepository::class),
            $assets,
        );
        $converter = new CarveConverter();
        $resources->register($converter, $context);
        $html = $converter->convert(':config[core.shopName] :config[core.secret] :asset[icons/truck.svg] :asset[../secret]');

        self::assertStringContainsString('Shop &lt;safe&gt;', $html);
        self::assertStringContainsString('core.secret', $html);
        self::assertStringContainsString('https://assets.example.test/bundles/shopwarecarve/icons/truck.svg', $html);
        self::assertStringContainsString('../secret', $html);
        self::assertStringNotContainsString('Shop <safe>', $html);
    }
}
