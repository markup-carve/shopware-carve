<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Extension\DocumentTransform;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Node;
use MarkupCarve\Shopware\Event\CarveAppBlockEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
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
     * @var array<string>
     */
    private const PRODUCT_TYPES = ['product', 'price', 'stock', 'datasheet', 'was-now', 'discount', 'product-image', 'variants', 'rating', 'reviews-summary'];

    /**
     * @var string
     */
    private const CARD = 'shopware.product-card';

    /**
     * @var \WeakMap<\Shopware\Core\System\SalesChannel\SalesChannelContext, array<string, \Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity|null>>
     */
    private WeakMap $products;

    /**
     * @var \WeakMap<\Shopware\Core\System\SalesChannel\SalesChannelContext, array<string, \Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix>>
     */
    private WeakMap $reviews;

    /**
     * @param \Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository<\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection> $productsRepository
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $cacheTags
     * @param \Shopware\Core\System\SystemConfig\SystemConfigService|null $config
     * @param \Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader|null $reviewLoader
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
        private readonly ?AbstractProductReviewLoader $reviewLoader = null,
        private readonly ?SystemConfigService $config = null,
    ) {
        $this->products = new WeakMap();
        $this->reviews = new WeakMap();
    }

    public function reset(): void
    {
        $this->products = new WeakMap();
        $this->reviews = new WeakMap();
    }

    public function prepare(Node $document, SalesChannelContext $context): void
    {
        $skus = [];
        $reviewSkus = [];
        CarveTree::walk($document, function (Node $found) use (&$skus, &$reviewSkus): void {
            $node = CarveTree::core($found);
            $name = (string)($node->f['name'] ?? '');
            if ($node->type === 'inline_extension' && in_array($name, self::PRODUCT_TYPES, true)) {
                $skus[] = $this->argument($node);
                if (in_array($name, ['rating', 'reviews-summary'], true)) {
                    $reviewSkus[] = $this->argument($node);
                }
            }
            if ($node->type === 'div' && (CarveTree::hasClass($node, 'specs') || CarveTree::hasClass($node, 'product-card'))) {
                $skus[] = CarveTree::attribute($node, 'sku') ?? '';
            }
            if ($node->type === 'div' && CarveTree::hasClass($node, 'product-grid')) {
                array_push($skus, ...$this->gridSkus($node));
            }
        });
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
            $criteria->addAssociation('cover.media');
            $criteria->addAssociation('options.group');
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
        if ($this->reviewLoader !== null && $this->config?->get('ShopwareCarve.config.enableReviewAggregates', $context->getSalesChannelId()) === true) {
            if ($reviewSkus !== []) {
                $this->cacheTags->dispatch(new AddCacheTagEvent('carve-reviews'));
            }
            $reviews = $this->reviews[$context] ?? [];
            foreach (array_slice(array_unique($reviewSkus), 0, 12) as $sku) {
                $product = $cached[$sku] ?? null;
                if ($product === null || isset($reviews[$product->getId()])) {
                    continue;
                }
                $reviews[$product->getId()] = $this->reviewLoader->load(
                    new Request(),
                    $context,
                    $product->getId(),
                    $product->getParentId(),
                )->getMatrix();
            }
            $this->reviews[$context] = $reviews;
        }
    }

    private function salesChannelProduct(ProductEntity $product): SalesChannelProductEntity
    {
        if (!$product instanceof SalesChannelProductEntity) {
            throw new UnexpectedValueException('The sales-channel repository returned a product without calculated prices.');
        }

        return $product;
    }

    public function register(CarveSetup $setup, SalesChannelContext $context): void
    {
        $setup->on('inline', 'snippet', function (CarveHookCall $call): string {
            $this->cacheTags->dispatch(new AddCacheTagEvent('carve-snippets'));

            return $this->escape($this->translator->trans($call->argument()));
        });
        foreach (['vat-note', 'price-note'] as $type) {
            $setup->on('inline', $type, function (CarveHookCall $call) use ($context, $type): string {
                $argument = $call->argument();
                if ($argument !== '') {
                    return $this->escape($argument);
                }
                $taxKey = match ($context->getTaxState()) {
                    CartPrice::TAX_STATE_GROSS => 'shopware-carve.taxGross',
                    CartPrice::TAX_STATE_NET => 'shopware-carve.taxNet',
                    CartPrice::TAX_STATE_FREE => 'shopware-carve.taxFree',
                    default => 'shopware-carve.taxNet',
                };
                $note = $this->escape($this->translator->trans($taxKey));
                if ($type === 'price-note') {
                    $note .= ', ' . $this->escape($this->translator->trans('shopware-carve.plusShipping'));
                }

                return '<span class="carve-price-note">' . $note . '</span>';
            });
        }
        $setup->on('inline', 'shipping-from', function (CarveHookCall $call) use ($context): string {
            $argument = $call->argument();
            if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $argument) !== 1) {
                return $this->escape($argument);
            }
            $label = $this->escape($this->translator->trans('shopware-carve.shippingFrom'));

            return '<span class="carve-shipping-from">' . $label . ' ' . $this->formatPrice((float)$argument, $context) . '</span>';
        });
        foreach (self::PRODUCT_TYPES as $type) {
            $setup->on('inline', $type, fn (CarveHookCall $call): string => $this->productInline($call, $type, $context));
        }
        $setup->addExtension(new ExtensionDefinition(
            self::CARD,
            '1',
            renderer: function (Node $node) use ($context): string {
                $product = ($this->products[$context] ?? [])[(string)($node->f['props']['sku'] ?? '')] ?? null;

                return $product === null ? '' : $this->card($product, $context);
            },
        ));
        $setup->addTransform(new DocumentTransform(self::CARD, function (Node $document) use ($context): Node {
            if ($this->replaceCardParagraphs($document, $context)) {
                CarveTree::declare($document, self::CARD, '1');
            }

            return $document;
        }));
        $setup->on('div', 'app-block', function (CarveHookCall $call) use ($context): ?string {
            $name = $call->attribute('name') ?? '';
            if (preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,63}$/D', $name) !== 1) {
                return null;
            }
            $block = new CarveAppBlockEvent($name, $call->childrenHtml(), $context);
            $this->cacheTags->dispatch($block);

            return $block->getHtml();
        });
        foreach (['specs', 'product-card'] as $kind) {
            $setup->on('div', $kind, function (CarveHookCall $call) use ($context, $kind): string {
                $sku = $call->attribute('sku') ?? '';
                $product = ($this->products[$context] ?? [])[$sku] ?? null;
                if ($product === null) {
                    return $this->escape($sku);
                }

                return $kind === 'specs' ? $this->specs($product) : ($product->getAvailable() ? $this->card($product, $context) : $this->escape($sku));
            });
        }
        $setup->on('div', 'product-grid', function (CarveHookCall $call) use ($context): string {
            $html = '';
            foreach ($this->gridSkus($call->node) as $sku) {
                $product = ($this->products[$context] ?? [])[$sku] ?? null;
                if ($product !== null && $product->getAvailable()) {
                    $html .= '<div class="carve-product-grid__item">' . $this->card($product, $context) . '</div>';
                }
            }

            return '<div class="carve-product-grid">' . $html . '</div>';
        });
    }

    private function productInline(CarveHookCall $call, string $type, SalesChannelContext $context): string
    {
        $argument = $call->argument();
        $product = ($this->products[$context] ?? [])[$argument] ?? null;
        if ($product === null) {
            return $this->escape($argument);
        }
        if ($type === 'rating' || $type === 'reviews-summary') {
            $matrix = ($this->reviews[$context] ?? [])[$product->getId()] ?? null;

            return $matrix === null ? $this->escape($argument) : $this->reviewSummary($matrix, $type);
        }
        if ($type === 'stock') {
            return '<span class="carve-stock">' . $this->escape($this->translator->trans(
                $product->getAvailable() ? 'shopware-carve.inStock' : 'shopware-carve.outOfStock',
            )) . '</span>';
        }
        if (!$product->getAvailable()) {
            return $this->escape($argument);
        }
        $name = (string)($product->getTranslation('name') ?? $product->getName() ?? $argument);
        $url = $this->seo->generate('frontend.detail.page', ['productId' => $product->getId()]);
        $html = match ($type) {
            'price' => $this->price($product, $context),
            'was-now' => $this->salePrice($product, $context),
            'discount' => $this->discount($product),
            'product-image' => $this->productImage($product, $name) ?: $this->escape($argument),
            'variants' => $this->variants($product, $argument),
            'datasheet' => $this->datasheet($product, $argument),
            default => '<a href="' . $this->escape($url) . '">' . $this->escape($name) . '</a>',
        };
        if ($type === 'product' && $call->hasClass('card')) {
            $image = $this->productImage($product, $name);
            $html = '<span class="carve-product-card">' . $image . '<span class="carve-product-card__body">'
                . $html . ' ' . $this->salePrice($product, $context) . '</span></span>';
        }

        return $html;
    }

    /**
     * A paragraph that holds nothing but `:product[SKU]{.card}` of an available product becomes the block card.
     */
    private function replaceCardParagraphs(Node $node, SalesChannelContext $context): bool
    {
        $replaced = false;
        foreach ($node->children ?? [] as $index => $child) {
            $children = $child->children ?? [];
            $only = count($children) === 1 ? CarveTree::core($children[0]) : null;
            if (
                $child->type === 'paragraph' && !CarveTree::hasAttributes($child) && $only !== null
                && $only->type === 'inline_extension' && ($only->f['name'] ?? null) === 'product' && CarveTree::hasClass($only, 'card')
            ) {
                $sku = $this->argument($only);
                $product = ($this->products[$context] ?? [])[$sku] ?? null;
                if ($product !== null && $product->getAvailable()) {
                    $card = new Node('block_extension', []);
                    $card->f = ['name' => self::CARD, 'version' => '1', 'slots' => [], 'props' => ['sku' => $sku], 'fallback' => [$child]];
                    $node->children[$index] = $card;
                    $replaced = true;
                }

                continue;
            }
            $replaced = $this->replaceCardParagraphs($child, $context) || $replaced;
            foreach ($child->f['fallback'] ?? [] as $fallback) {
                if ($fallback instanceof Node) {
                    $replaced = $this->replaceCardParagraphs($fallback, $context) || $replaced;
                }
            }
        }

        return $replaced;
    }

    private function price(SalesChannelProductEntity $product, SalesChannelContext $context): string
    {
        $price = $product->getCalculatedPrices()->first() ?? $product->getCalculatedPrice();

        return $this->formatPrice($price->getUnitPrice(), $context);
    }

    private function formatPrice(float $value, SalesChannelContext $context): string
    {
        return $this->escape($this->currency->formatCurrencyByLanguage(
            $value,
            $context->getCurrency()->getIsoCode(),
            $context->getLanguageId(),
            $context->getContext(),
            $context->getItemRounding()->getDecimals(),
        ));
    }

    private function salePrice(SalesChannelProductEntity $product, SalesChannelContext $context): string
    {
        $price = $product->getCalculatedPrices()->first() ?? $product->getCalculatedPrice();
        $list = $price->getListPrice();
        if ($list === null || $list->getPrice() <= $price->getUnitPrice()) {
            return $this->price($product, $context);
        }

        return '<span class="carve-sale-price"><s>' . $this->formatPrice($list->getPrice(), $context)
            . '</s> <strong>' . $this->price($product, $context) . '</strong></span>';
    }

    private function discount(SalesChannelProductEntity $product): string
    {
        $price = $product->getCalculatedPrices()->first() ?? $product->getCalculatedPrice();
        $list = $price->getListPrice();
        if ($list === null || $list->getPrice() <= $price->getUnitPrice()) {
            return '';
        }

        return '<span class="carve-discount">-' . $this->escape((string)round($list->getPercentage())) . '%</span>';
    }

    private function productImage(SalesChannelProductEntity $product, string $name): string
    {
        $media = $product->getCover()?->getMedia();
        $url = $media?->getUrl();
        if (
            $media === null || $media->isPrivate() || !str_starts_with($media->getMimeType() ?? '', 'image/')
            || !is_string($url) || preg_match('~^(?:https?://|/(?!/))[^\s]+$~i', $url) !== 1
        ) {
            return '';
        }

        return '<img class="carve-product-image" src="' . $this->escape($url) . '" alt="'
            . $this->escape($name) . '" loading="lazy" decoding="async">';
    }

    private function variants(SalesChannelProductEntity $product, string $sku): string
    {
        $chips = '';
        foreach ($product->getOptions() ?? [] as $option) {
            $value = (string)($option->getTranslation('name') ?? $option->getName() ?? '');
            if ($value === '') {
                continue;
            }
            $group = $option->getGroup();
            $groupName = (string)($group?->getTranslation('name') ?? $group?->getName() ?? '');
            $label = $groupName === '' ? $value : $groupName . ': ' . $value;
            $chips .= '<span class="carve-variant-chip">' . $this->escape($label) . '</span>';
        }

        return $chips === '' ? $this->escape($sku) : '<span class="carve-variants">' . $chips . '</span>';
    }

    private function reviewSummary(RatingMatrix $matrix, string $type): string
    {
        $count = $matrix->getTotalReviewCount();
        $rating = round($matrix->getAverageRating(), 1);
        $ratingText = number_format($rating, 1, '.', '');
        $countText = $this->translator->trans(
            $count === 1 ? 'shopware-carve.reviewCountOne' : 'shopware-carve.reviewCountMany',
            ['%count%' => (string)$count],
        );
        $label = $this->translator->trans('shopware-carve.reviewRating', [
            '%rating%' => $ratingText,
            '%reviews%' => $countText,
        ]);
        $stars = '<span class="carve-rating-stars" role="img" aria-label="' . $this->escape($label) . '">'
            . '<span aria-hidden="true">★★★★★</span><span class="carve-rating-stars__fill" aria-hidden="true" style="width: '
            . ($rating * 20) . '%">★★★★★</span></span>';

        return $type === 'rating'
            ? '<span class="carve-review-rating">' . $stars . ' (' . $count . ')</span>'
            : '<span class="carve-reviews-summary">' . $stars . ' <strong>' . $ratingText . '/5</strong> '
                . $this->escape($countText) . '</span>';
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
        return trim(CarveTree::text($node));
    }

    /**
     * @return list<string>
     */
    private function gridSkus(Node $node): array
    {
        return array_slice(array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->argument($node)) ?: []))), 0, 24);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
