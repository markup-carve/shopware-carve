<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Block\Div;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Node\Node;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use UnexpectedValueException;
use WeakMap;

class CarveCommerce implements ResetInterface
{
    /**
     * @var \WeakMap<\Shopware\Core\System\SalesChannel\SalesChannelContext, array<string, \Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity|null>>
     */
    private WeakMap $products;

    /**
     * @param \Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository<\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection> $productsRepository
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $cacheTags
     * @param \Twig\Environment $twig
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     * @param \Shopware\Core\System\Currency\CurrencyFormatter $currency
     * @param \Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface $seo
     */
    public function __construct(
        private readonly SalesChannelRepository $productsRepository,
        private readonly SeoUrlPlaceholderHandlerInterface $seo,
        private readonly CurrencyFormatter $currency,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
        private readonly EventDispatcherInterface $cacheTags,
    ) {
        $this->products = new WeakMap();
    }

    public function reset(): void
    {
        $this->products = new WeakMap();
    }

    public function prepare(Document $document, SalesChannelContext $context): void
    {
        $skus = [];
        $visit = function (Node $node) use (&$visit, &$skus): void {
            if ($node instanceof InlineExtension && in_array($node->getExtensionType(), ['product', 'price', 'stock', 'datasheet'], true)) {
                $skus[] = $this->argument($node);
            }
            if ($node instanceof Div && ($node->hasClass('specs') || $node->hasClass('product-card'))) {
                $skus[] = $node->getAttribute('sku') ?? '';
            }
            if ($node instanceof Div && $node->hasClass('product-grid')) {
                array_push($skus, ...$this->gridSkus($node));
            }
            foreach ($node->getChildren() as $child) {
                $visit($child);
            }
        };
        $visit($document);
        $cached = $this->products[$context] ?? [];
        $missing = array_values(array_filter(array_unique($skus), static fn (string $sku): bool => $sku !== '' && !array_key_exists($sku, $cached)));
        // Bound one document's catalog work independently of the parser's byte limit.
        $missing = array_slice($missing, 0, 100);
        if ($missing !== []) {
            $criteria = new Criteria();
            $criteria->setLimit(count($missing));
            $criteria->addFilter(new EqualsAnyFilter('productNumber', $missing));
            $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));
            $criteria->addAssociation('properties.group');
            $criteria->addAssociation('media.media');
            foreach ($missing as $sku) {
                $cached[$sku] = null;
            }
            foreach ($this->productsRepository->search($criteria, $context) as $product) {
                $cached[$product->getProductNumber()] = $this->salesChannelProduct($product);
            }
            $this->products[$context] = $cached;
        }
        foreach (array_unique($skus) as $sku) {
            $product = $cached[$sku] ?? null;
            if ($product === null) {
                $this->cacheTags->dispatch(new AddCacheTagEvent('carve-products-missing'));
            } else {
                $this->cacheTags->dispatch(new AddCacheTagEvent(ProductDetailRoute::buildName($product->getId())));
                if ($product->getParentId() !== null) {
                    $this->cacheTags->dispatch(new AddCacheTagEvent(ProductDetailRoute::buildName($product->getParentId())));
                }
            }
        }
    }

    private function salesChannelProduct(ProductEntity $product): SalesChannelProductEntity
    {
        if (!$product instanceof SalesChannelProductEntity) {
            throw new UnexpectedValueException('The sales-channel repository returned a product without calculated prices.');
        }

        return $product;
    }

    public function register(CarveConverter $converter, SalesChannelContext $context): void
    {
        $converter->on('render.inline_extension', function (RenderEvent $event) use ($context): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension) {
                return;
            }
            $type = $node->getExtensionType();
            $argument = $this->argument($node);
            if ($type === 'snippet') {
                $this->cacheTags->dispatch(new AddCacheTagEvent('carve-snippets'));
                $event->setHtml($this->escape($this->translator->trans($argument)));

                return;
            }
            if (!in_array($type, ['product', 'price', 'stock', 'datasheet'], true)) {
                return;
            }
            $product = ($this->products[$context] ?? [])[$argument] ?? null;
            if ($product === null) {
                $event->setHtml($this->escape($argument));

                return;
            }
            if ($type === 'stock') {
                $event->setHtml('<span class="carve-stock">' . $this->escape($this->translator->trans(
                    $product->getAvailable() ? 'shopware-carve.inStock' : 'shopware-carve.outOfStock',
                )) . '</span>');

                return;
            }
            if (!$product->getAvailable()) {
                $event->setHtml($this->escape($argument));

                return;
            }
            $name = (string)($product->getTranslation('name') ?? $product->getName() ?? $argument);
            $url = $this->seo->generate('frontend.detail.page', ['productId' => $product->getId()]);
            $html = match ($type) {
                'price' => $this->escape($this->currency->formatCurrencyByLanguage(
                    ($product->getCalculatedPrices()->first() ?? $product->getCalculatedPrice())->getUnitPrice(),
                    $context->getCurrency()->getIsoCode(),
                    $context->getLanguageId(), $context->getContext(), $context->getItemRounding()->getDecimals(),
                )),
                'datasheet' => $this->datasheet($product, $argument),
                default => '<a href="' . $this->escape($url) . '">' . $this->escape($name) . '</a>',
            };
            if ($type === 'product' && $node->hasClass('card')) {
                $html = '<span class="carve-product-card">' . $html . '</span>';
            }
            $event->setHtml($html);
        });
        $converter->on('render.div', function (RenderEvent $event) use ($context): void {
            $node = $event->getNode();
            if ($node instanceof Div && ($node->hasClass('specs') || $node->hasClass('product-card'))) {
                $sku = $node->getAttribute('sku') ?? '';
                $product = ($this->products[$context] ?? [])[$sku] ?? null;
                $event->setHtml($product === null ? $this->escape($sku)
                    : ($node->hasClass('specs') ? $this->specs($product) : ($product->getAvailable() ? $this->card($product, $context) : $this->escape($sku))));

                return;
            }
            if (!$node instanceof Div || !$node->hasClass('product-grid')) {
                return;
            }
            $html = '';
            foreach ($this->gridSkus($node) as $sku) {
                $product = ($this->products[$context] ?? [])[$sku] ?? null;
                if ($product !== null && $product->getAvailable()) {
                    $html .= '<div class="carve-product-grid__item">' . $this->card($product, $context) . '</div>';
                }
            }
            $event->setHtml('<div class="carve-product-grid">' . $html . '</div>');
        });
    }

    private function card(SalesChannelProductEntity $product, SalesChannelContext $context): string
    {
        $template = '@Storefront/storefront/component/product/card/box-standard.html.twig';
        if ($this->twig->getLoader()->exists($template)) {
            try {
                return $this->twig->render($template, [
                    'product' => $product,
                    'context' => $context,
                    'displayMode' => 'standard',
                    'layout' => 'standard',
                    'element' => ['config' => ['boxHeadlineLevel' => ['value' => 3]]],
                ]);
            } catch (TwigError) {
                // API previews and core-only installations may lack the Storefront runtime.
            }
        }
        $url = $this->seo->generate('frontend.detail.page', ['productId' => $product->getId()]);

        return '<a href="' . $this->escape($url) . '">' . $this->escape(
            (string)($product->getTranslation('name') ?? $product->getName() ?? $product->getProductNumber()),
        ) . '</a>';
    }

    private function specs(SalesChannelProductEntity $product): string
    {
        $html = '';
        foreach ($product->getProperties() ?? [] as $property) {
            $group = $property->getGroup();
            $html .= '<dt>' . $this->escape((string)($group?->getTranslation('name') ?? $group?->getName()))
                . '</dt><dd>' . $this->escape((string)($property->getTranslation('name') ?? $property->getName())) . '</dd>';
        }

        return '<dl class="carve-specs">' . $html . '</dl>';
    }

    private function datasheet(SalesChannelProductEntity $product, string $fallback): string
    {
        foreach ($product->getMedia() ?? [] as $productMedia) {
            $media = $productMedia->getMedia();
            if ($media === null || $media->getMimeType() !== 'application/pdf' || $media->isPrivate()) {
                continue;
            }
            $url = $media->getUrl();
            if (!preg_match('~^(?:https?://|/(?!/))~i', $url)) {
                continue;
            }

            return '<a href="' . $this->escape($url) . '">' . $this->escape(
                (string)($media->getTranslation('title') ?? $media->getFileName() ?? $fallback),
            ) . '</a>';
        }

        return $this->escape($fallback);
    }

    private function argument(Node $node): string
    {
        $document = new Document();
        // Rendering a temporary document must not reparent the commerce node's children.
        foreach ($node->getChildren() as $child) {
            $document->appendChild(clone $child);
        }

        return trim((new PlainTextRenderer())->render($document));
    }

    /**
     * @return list<string>
     */
    private function gridSkus(Div $node): array
    {
        return array_slice(array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->argument($node)) ?: []))), 0, 24);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
