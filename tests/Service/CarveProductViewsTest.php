<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CarveProductViewsTest extends TestCase
{
    public function testSaleImageAndInlineCardUseOneScopedProductLookup(): void
    {
        $context = $this->createStub(SalesChannelContext::class);
        $framework = Context::createDefaultContext();
        $context->method('getContext')->willReturn($framework);
        $context->method('getSalesChannelId')->willReturn(str_repeat('a', 32));
        $context->method('getLanguageId')->willReturn($framework->getLanguageId());
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU');
        $product->setName('Product <name>');
        $product->setAvailable(true);
        $product->setCalculatedPrice(new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection(), 1, null, ListPrice::createFromUnitPrice(20, 25)));
        $product->setCalculatedPrices(new PriceCollection());
        $media = new MediaEntity();
        $media->setUrl('/media/product.jpg');
        $media->setMimeType('image/jpeg');
        $cover = new ProductMediaEntity();
        $cover->setMedia($media);
        $product->setCover($cover);

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::once())->method('search')->willReturn(new EntitySearchResult('product', 1, new SalesChannelProductCollection([$product]), null, new Criteria(), $framework));
        $seo = $this->createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seo->method('generate')->willReturn('/product');
        $formatter = $this->createStub(CurrencyFormatter::class);
        $formatter->method('formatCurrencyByLanguage')->willReturnCallback(static fn (float $value): string => number_format($value, 2) . ' EUR');
        $commerce = new CarveCommerce($repository, $seo, $formatter, $this->createStub(TranslatorInterface::class), new Environment(new ArrayLoader()), $this->createStub(EventDispatcherInterface::class));
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':was-now[SKU] :discount[SKU] :product-image[SKU] :product[SKU]{.card}');
        $commerce->prepare($document, $context);
        $html = $converter->render($document);
        self::assertStringContainsString('<s>25.00 EUR</s> <strong>20.00 EUR</strong>', $html);
        self::assertStringContainsString('carve-discount">-20%', $html);
        self::assertStringContainsString('src="/media/product.jpg" alt="Product &lt;name&gt;"', $html);
        self::assertStringContainsString('carve-product-card__body', $html);
        self::assertStringContainsString('href="/product"', $html);

        $media->setUrl('javascript:alert(1)');
        $unsafe = $converter->render($document);
        self::assertStringNotContainsString('<img ', $unsafe);
        self::assertStringNotContainsString('javascript:', $unsafe);
    }
}
