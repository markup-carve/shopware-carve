<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveResources;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Error\SyntaxError;

class CarveThemeIconTest extends TestCase
{
    public function testValidNameUsesThemeTemplateAndInvalidNameStaysText(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->with(
            '@ShopwareCarve/storefront/utilities/carve-icon.html.twig',
            ['name' => 'truck'],
        )->willReturn('<span class="carve-icon">icon</span>');
        $resources = new CarveResources(
            $this->createStub(EntityRepository::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(SalesChannelRepository::class),
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->createStub(SystemConfigService::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(EntityRepository::class),
            null,
            $twig,
        );
        $converter = new CarveConverter();
        $resources->register($converter, $this->createStub(SalesChannelContext::class));
        $html = $converter->convert(':icon[truck] :icon[../secret]');

        self::assertStringContainsString('<span class="carve-icon">icon</span>', $html);
        self::assertStringContainsString('../secret', $html);
    }

    public function testMissingStorefrontTagKeepsTheIconNameReadable(): void
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willThrowException(new SyntaxError('Unknown sw_icon tag'));
        $resources = new CarveResources(
            $this->createStub(EntityRepository::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(SalesChannelRepository::class),
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->createStub(SystemConfigService::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(EntityRepository::class),
            null,
            $twig,
        );
        $converter = new CarveConverter();
        $resources->register($converter, $this->createStub(SalesChannelContext::class));

        self::assertStringContainsString('truck', $converter->convert(':icon[truck]'));
    }
}
