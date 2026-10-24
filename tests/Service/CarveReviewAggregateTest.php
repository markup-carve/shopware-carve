<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CarveReviewAggregateTest extends TestCase
{
    public function testBothReviewViewsShareOneScopedAggregate(): void
    {
        $framework = Context::createDefaultContext();
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn($framework);
        $context->method('getSalesChannelId')->willReturn(str_repeat('a', 32));
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU');
        $product->setAvailable(true);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::once())->method('search')->willReturn(new EntitySearchResult(
            'product',
            1,
            new SalesChannelProductCollection([$product]),
            null,
            new Criteria(),
            $framework,
        ));
        $matrix = new RatingMatrix([new Bucket('5', 2, null), new Bucket('4', 1, null)]);
        $result = $this->createStub(ProductReviewResult::class);
        $result->method('getMatrix')->willReturn($matrix);
        $loader = $this->createMock(AbstractProductReviewLoader::class);
        $loader->expects(self::once())->method('load')->willReturn($result);
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturn(true);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static function (string $key, array $params = []): string {
            return str_replace(array_keys($params), array_values($params), match ($key) {
                'shopware-carve.reviewCountMany' => '%count% reviews',
                'shopware-carve.reviewRating' => '%rating% out of 5 stars from %reviews%',
                default => $key,
            });
        });
        $commerce = new CarveCommerce(
            $repository,
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->createStub(CurrencyFormatter::class),
            $translator,
            new Environment(new ArrayLoader()),
            $this->createStub(EventDispatcherInterface::class),
            $loader,
            $config,
        );
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':rating[SKU] :reviews-summary[SKU]');
        $commerce->prepare($document, $context);
        $html = $converter->render($document);

        self::assertStringContainsString('4.7 out of 5 stars from 3 reviews', $html);
        self::assertStringContainsString('(3)', $html);
        self::assertStringContainsString('4.7/5</strong> 3 reviews', $html);
    }

    public function testReviewLookupIsOffByDefault(): void
    {
        $framework = Context::createDefaultContext();
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn($framework);
        $context->method('getSalesChannelId')->willReturn(str_repeat('a', 32));
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU');
        $repository = $this->createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'product',
            1,
            new SalesChannelProductCollection([$product]),
            null,
            new Criteria(),
            $framework,
        ));
        $loader = $this->createMock(AbstractProductReviewLoader::class);
        $loader->expects(self::never())->method('load');
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturn(false);
        $commerce = new CarveCommerce(
            $repository,
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->createStub(CurrencyFormatter::class),
            $this->createStub(TranslatorInterface::class),
            new Environment(new ArrayLoader()),
            $this->createStub(EventDispatcherInterface::class),
            $loader,
            $config,
        );
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':rating[SKU]');
        $commerce->prepare($document, $context);

        self::assertStringContainsString('SKU', $converter->render($document));
    }
}
