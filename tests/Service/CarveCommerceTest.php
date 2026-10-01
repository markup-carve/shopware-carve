<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
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

class CarveCommerceTest extends TestCase
{
    public function testReferencesAreBatchedScopedAndMemoizedIncludingMissingSkus(): void
    {
        $context = $this->context();
        $product = $this->product('SKU');
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::once())->method('search')->willReturnCallback(
            function (Criteria $criteria, SalesChannelContext $actual) use ($context, $product): EntitySearchResult {
                self::assertSame($context, $actual);
                $availability = array_values(array_filter($criteria->getFilters(), static fn ($filter): bool => $filter instanceof ProductAvailableFilter));
                self::assertCount(1, $availability);
                self::assertEquals(new ProductAvailableFilter($context->getSalesChannelId(), 10), $availability[0]);
                self::assertSame(2, $criteria->getLimit());

                return new EntitySearchResult('product', 1, new SalesChannelProductCollection([$product]), null, $criteria, $context->getContext());
            },
        );
        $commerce = $this->commerce($repository);
        foreach ([':product[SKU] :product[SKU] :product[MISSING]', ':product[SKU] :product[MISSING]'] as $source) {
            $converter = new CarveConverter();
            $commerce->register($converter, $context);
            $document = $converter->parse($source);
            $commerce->prepare($document, $context);
            $html = $converter->render($document);
            self::assertStringContainsString('href="/shop/de/product"', $html);
            self::assertStringContainsString('Product &lt;name&gt;', $html);
            self::assertStringContainsString('MISSING', $html);
            self::assertStringNotContainsString('/detail/', $html);
        }
    }

    public function testAdvancedPriceForQuantityOneIsUsed(): void
    {
        $context = $this->context();
        $product = $this->product('SKU');
        $product->setCalculatedPrices(new PriceCollection([
            new CalculatedPrice(15, 15, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]));
        $repository = $this->createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('product', 1, new SalesChannelProductCollection([$product]), null, new Criteria(), $context->getContext()));
        $currency = $this->createMock(CurrencyFormatter::class);
        $currency->expects(self::once())->method('formatCurrencyByLanguage')->with(15.0, 'EUR', $context->getLanguageId(), $context->getContext(), 2)->willReturn('15,00 €');
        $commerce = $this->commerce($repository, $currency);
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':price[SKU]');
        $commerce->prepare($document, $context);
        self::assertStringContainsString('15,00 €', $converter->render($document));
    }

    public function testOutOfStockProductsDoNotProduceBuyLinks(): void
    {
        $context = $this->context();
        $product = $this->product('SKU');
        $product->setAvailable(false);
        $repository = $this->createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('product', 1, new SalesChannelProductCollection([$product]), null, new Criteria(), $context->getContext()));
        $commerce = $this->commerce($repository);
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':product[SKU] :stock[SKU]');
        $commerce->prepare($document, $context);
        $html = $converter->render($document);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringContainsString('shopware-carve.outOfStock', $html);
    }

    public function testCardsAndGridsUseCoreTemplateAndSkipUnavailableProducts(): void
    {
        $context = $this->context();
        $available = $this->product('AVAILABLE');
        $unavailable = $this->product('UNAVAILABLE');
        $unavailable->setAvailable(false);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::once())->method('search')->willReturn(new EntitySearchResult(
            'product',
            2,
            new SalesChannelProductCollection([$available, $unavailable]),
            null,
            new Criteria(),
            $context->getContext(),
        ));
        $twig = new Environment(new ArrayLoader([
            '@Storefront/storefront/component/product/card/box-standard.html.twig' => 'CARD {{ product.productNumber }} {{ context.currency.isoCode }}',
        ]));
        $commerce = $this->commerce($repository, twig: $twig);
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse("{sku=\"AVAILABLE\"}\n::: product-card\n:::\n\n::: product-grid\nAVAILABLE, UNAVAILABLE\n:::");
        $commerce->prepare($document, $context);
        $html = $converter->render($document);
        self::assertSame(2, substr_count($html, 'CARD AVAILABLE EUR'));
        self::assertStringNotContainsString('UNAVAILABLE', $html);
        self::assertStringContainsString('carve-product-grid', $html);
    }

    public function testCoreOnlyInstallFallsBackToProductLinksForCards(): void
    {
        $context = $this->context();
        $repository = $this->createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'product',
            1,
            new SalesChannelProductCollection([$this->product('SKU')]),
            null,
            new Criteria(),
            $context->getContext(),
        ));
        $commerce = $this->commerce($repository);
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse("{sku=\"SKU\"}\n::: product-card\n:::");
        $commerce->prepare($document, $context);
        self::assertStringContainsString('<a href="/shop/de/product">Product &lt;name&gt;</a>', $converter->render($document));
    }

    public function testPlainContentDoesNotCollectCommerceCacheTags(): void
    {
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::never())->method('search');
        $tags = $this->createMock(EventDispatcherInterface::class);
        $tags->expects(self::never())->method('dispatch');
        $commerce = $this->commerce($repository, tags: $tags);
        $commerce->prepare((new CarveConverter())->parse('Ordinary prose.'), $this->context());
    }

    /**
     * @param \Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository<\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection> $repository
     * @param \Shopware\Core\System\Currency\CurrencyFormatter|null $currency
     * @param \Twig\Environment|null $twig
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null $tags
     */
    private function commerce(
        SalesChannelRepository $repository,
        ?CurrencyFormatter $currency = null,
        ?Environment $twig = null,
        ?EventDispatcherInterface $tags = null,
    ): CarveCommerce {
        $seo = $this->createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seo->method('generate')->willReturn('/shop/de/product');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CarveCommerce(
            $repository,
            $seo,
            $currency ?? $this->createStub(CurrencyFormatter::class),
            $translator,
            $twig ?? new Environment(new ArrayLoader()),
            $tags ?? $this->createStub(EventDispatcherInterface::class),
        );
    }

    private function context(): SalesChannelContext
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

        return $context;
    }

    private function product(string $sku): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber($sku);
        $product->setName('Product <name>');
        $product->setAvailable(true);
        $product->setCalculatedPrice(new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $product->setCalculatedPrices(new PriceCollection());

        return $product;
    }
}
