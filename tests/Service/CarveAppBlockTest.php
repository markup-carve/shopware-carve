<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tests\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Shopware\Event\CarveAppBlockEvent;
use MarkupCarve\Shopware\Service\CarveCommerce;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CarveAppBlockTest extends TestCase
{
    public function testNamedAppBlockCanBeResolvedByAnotherService(): void
    {
        $context = $this->createStub(SalesChannelContext::class);
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $event) use ($context): object {
                self::assertInstanceOf(CarveAppBlockEvent::class, $event);
                self::assertSame('inventory.stock', $event->getName());
                self::assertSame($context, $event->getContext());
                self::assertStringContainsString('Fallback', $event->getFallbackHtml());
                $event->setHtml('<strong>Available</strong>');

                return $event;
            },
        );
        $commerce = $this->commerce($events);
        $converter = new CarveConverter();
        $commerce->register($converter, $context);
        $html = $converter->convert("{name=\"inventory.stock\"}\n::: app-block\nFallback\n:::");

        self::assertStringContainsString('<strong>Available</strong>', $html);
        self::assertStringNotContainsString('Fallback', $html);
    }

    public function testUnhandledAndInvalidBlocksKeepTheirAuthoredContent(): void
    {
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::once())->method('dispatch')->willReturnArgument(0);
        $converter = new CarveConverter();
        $this->commerce($events)->register($converter, $this->createStub(SalesChannelContext::class));
        $html = $converter->convert("{name=\"inventory.stock\"}\n::: app-block\nFallback\n:::"
            . "\n\n{name=\"bad name\"}\n::: app-block\nUnchanged\n:::");

        self::assertStringContainsString('Fallback', $html);
        self::assertStringContainsString('Unchanged', $html);
    }

    private function commerce(EventDispatcherInterface $events): CarveCommerce
    {
        return new CarveCommerce(
            $this->createStub(SalesChannelRepository::class),
            $this->createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->createStub(CurrencyFormatter::class),
            $this->createStub(TranslatorInterface::class),
            new Environment(new ArrayLoader()),
            $events,
        );
    }
}
