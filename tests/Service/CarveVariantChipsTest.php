<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
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

class CarveVariantChipsTest extends TestCase
{
    public function testVariantOptionsUseTheBatchedSalesChannelProductAndEscapeLabels(): void
    {
        $context = $this->createStub(SalesChannelContext::class);
        $framework = Context::createDefaultContext();
        $context->method('getContext')->willReturn($framework);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());
        $group = new PropertyGroupEntity();
        $group->setId(Uuid::randomHex());
        $group->setName('Color & finish');
        $option = new PropertyGroupOptionEntity();
        $option->setId(Uuid::randomHex());
        $option->setName('<Blue>');
        $option->setGroup($group);
        $variant = $this->product('BLUE');
        $variant->setOptions(new PropertyGroupOptionCollection([$option]));
        $plain = $this->product('PLAIN');
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::once())->method('search')->willReturnCallback(
            static function (Criteria $criteria, SalesChannelContext $actual) use ($context, $framework, $variant, $plain): EntitySearchResult {
                self::assertSame($context, $actual);
                self::assertArrayHasKey('options', $criteria->getAssociations());

                return new EntitySearchResult('product', 2, new SalesChannelProductCollection([$variant, $plain]), null, $criteria, $framework);
            },
        );
        $commerce = new CarveCommerce(
            $repository,
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->createStub(CurrencyFormatter::class),
            $this->createStub(TranslatorInterface::class),
            new Environment(new ArrayLoader()),
            $this->createStub(EventDispatcherInterface::class),
        );
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $document = $converter->parse(':variants[BLUE] :variants[PLAIN]');
        $commerce->prepare($document, $context);
        $html = $converter->render($document);

        self::assertStringContainsString('Color &amp; finish: &lt;Blue&gt;', $html);
        self::assertStringContainsString('class="carve-variant-chip"', $html);
        self::assertStringContainsString('PLAIN', $html);
        self::assertStringNotContainsString('<Blue>', $html);
    }

    private function product(string $sku): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber($sku);
        $product->setAvailable(true);

        return $product;
    }
}
