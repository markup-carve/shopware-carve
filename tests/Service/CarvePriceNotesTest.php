<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CarvePriceNotesTest extends TestCase
{
    public function testNotesFollowTaxStateAndThresholdUsesChannelCurrency(): void
    {
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects(self::never())->method('search');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => [
            'shopware-carve.taxGross' => 'Incl. VAT',
            'shopware-carve.taxNet' => 'Excl. VAT',
            'shopware-carve.taxFree' => 'VAT exempt',
            'shopware-carve.plusShipping' => 'plus shipping',
            'shopware-carve.shippingFrom' => 'Free shipping from',
        ][$key]);
        $formatter = $this->createMock(CurrencyFormatter::class);
        $formatter->expects(self::once())->method('formatCurrencyByLanguage')->with(50.0, 'EUR')->willReturn('€50.00');
        $commerce = new CarveCommerce(
            $repository,
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $formatter,
            $translator,
            new Environment(new ArrayLoader()),
            $this->createStub(EventDispatcherInterface::class),
        );
        $context = $this->context(CartPrice::TAX_STATE_GROSS);
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $html = $converter->convert(':vat-note[] :price-note[] :shipping-from[50] :shipping-from[-1]');

        self::assertStringContainsString('Incl. VAT</span>', $html);
        self::assertStringContainsString('Incl. VAT, plus shipping', $html);
        self::assertStringContainsString('Free shipping from €50.00', $html);
        self::assertStringNotContainsString('Free shipping from -1', $html);
        self::assertStringContainsString('-1', $html);
    }

    public function testNetAndTaxFreeNotesUseTheirOwnMessages(): void
    {
        foreach ([CartPrice::TAX_STATE_NET => 'Excl. VAT', CartPrice::TAX_STATE_FREE => 'VAT exempt'] as $state => $message) {
            $translator = $this->createStub(TranslatorInterface::class);
            $translator->method('trans')->willReturn($message);
            $commerce = new CarveCommerce(
                $this->createStub(SalesChannelRepository::class),
                $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
                $this->createStub(CurrencyFormatter::class),
                $translator,
                new Environment(new ArrayLoader()),
                $this->createStub(EventDispatcherInterface::class),
            );
            $converter = new CarveConverter();
            $commerce->register($converter, $this->context($state));
            self::assertStringContainsString($message, $converter->convert(':vat-note[]'));
        }
    }

    private function context(string $state): SalesChannelContext
    {
        $context = $this->createStub(SalesChannelContext::class);
        $framework = Context::createDefaultContext();
        $context->method('getContext')->willReturn($framework);
        $context->method('getTaxState')->willReturn($state);
        $context->method('getLanguageId')->willReturn($framework->getLanguageId());
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        return $context;
    }
}
