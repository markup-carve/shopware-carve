<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveResources;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CarveResourcesTest extends TestCase
{
    public function testLegalLinkResolvesTheConfiguredCmsLayout(): void
    {
        $pageId = Uuid::randomHex();
        $page = new CmsPageEntity();
        $page->setId($pageId);
        $page->setTranslated(['name' => 'Privacy & <data>']);
        $categories = $this->createMock(SalesChannelRepository::class);
        $categories->expects(self::never())->method('search');

        $html = $this->render(':legal[privacy]', ['core.basicInformation.privacyPage' => $pageId], [$page], $categories);

        self::assertStringContainsString('href="/widgets/cms/' . $pageId . '"', $html);
        self::assertStringContainsString('data-ajax-modal="true" data-url="/widgets/cms/' . $pageId . '"', $html);
        self::assertStringContainsString('>Privacy &amp; &lt;data&gt;</a>', $html);
    }

    public function testLegalLinkStaysTextWithoutAConfiguredLayout(): void
    {
        self::assertSame("<p>privacy unknown</p>\n", $this->render(':legal[privacy] :legal[unknown]', [], []));
    }

    /**
     * @param string $source
     * @param array<string, string> $settings
     * @param array<\Shopware\Core\Content\Cms\CmsPageEntity> $pages
     * @param \Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository<\Shopware\Core\Content\Category\CategoryCollection>|null $categories
     *
     * @return string
     */
    private function render(string $source, array $settings, array $pages, ?SalesChannelRepository $categories = null): string
    {
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (string $key): ?string => $settings[$key] ?? null);
        $cmsPages = $this->createStub(EntityRepository::class);
        $cmsPages->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $actual): EntitySearchResult => new EntitySearchResult(
                'cms_page',
                count($pages),
                new CmsPageCollection(array_filter($pages, static fn (CmsPageEntity $page): bool => in_array($page->getId(), $criteria->getIds(), true))),
                null,
                $criteria,
                $actual,
            ),
        );
        $seo = $this->createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seo->method('generate')->willReturnCallback(static fn (string $name, array $parameters): string => '/widgets/cms/' . $parameters['id']);
        $resources = new CarveResources(
            $this->createStub(EntityRepository::class),
            $this->createStub(EntityRepository::class),
            $categories ?? $this->createStub(SalesChannelRepository::class),
            $seo,
            $config,
            $this->createStub(EventDispatcherInterface::class),
            $cmsPages,
        );
        $converter = new CarveConverter();
        $resources->register($converter, $context);

        return $converter->render($converter->parse($source));
    }
}
