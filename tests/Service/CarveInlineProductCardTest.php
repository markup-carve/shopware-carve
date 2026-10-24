<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CarveInlineProductCardTest extends TestCase
{
    public function testInlineCardUsesStorefrontCardTemplate(): void
    {
        $framework = Context::createDefaultContext();
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn($framework);
        $context->method('getSalesChannelId')->willReturn(str_repeat('a', 32));

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU');
        $product->setName('Product');
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
        $seo = $this->createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seo->method('generate')->willReturn('/product');
        $twig = new Environment(new ArrayLoader([
            '@Storefront/storefront/component/product/card/box-standard.html.twig' => '<article class="storefront-card">{{ product.productNumber }}</article>',
        ]));
        $commerce = new CarveCommerce(
            $repository,
            $seo,
            $this->createStub(CurrencyFormatter::class),
            $this->createStub(TranslatorInterface::class),
            $twig,
            $this->createStub(EventDispatcherInterface::class),
        );
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':product[SKU]{.card}');
        $commerce->prepare($document, $context);

        $html = $converter->render($document);
        self::assertStringContainsString('<article class="storefront-card">SKU</article>', $html);
        self::assertStringNotContainsString('<p>', $html);
    }
}
