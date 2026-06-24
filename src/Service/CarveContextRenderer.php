<?php declare(strict_types=1);

namespace Carve\Shopware\Service;

use Carve\CarveConverter;
use Carve\Shopware\Inline\ProductInlineMatcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Context-aware Carve rendering. Builds a per-call converter with safe mode controlled by the
 * `ShopwareCarve.config.safeMode` system config setting (default: ON), and with the
 * :product[SKU] inline extension bound to the active sales channel, so authored product
 * references resolve to storefront links for the current channel.
 *
 * `:product[SKU]` is parsed by carve-php as a generic InlineExtension node (type
 * "product"). A render.inline_extension event listener intercepts those nodes and
 * resolves them to <a> links via a product repository lookup.
 *
 * Per-call because the render listener closure captures the SalesChannelContext;
 * do not share this converter across requests.
 */
class CarveContextRenderer
{
    /**
     * @param EntityRepository<\Shopware\Core\Content\Product\ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public function toHtml(?string $source, SalesChannelContext $context): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        $value = $this->systemConfig->get('ShopwareCarve.config.safeMode');
        $safe = $value === null ? true : (bool) $value;

        $converter = new CarveConverter(safeMode: $safe);

        (new ProductInlineMatcher(function (string $sku) use ($context): ?array {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productNumber', $sku));
            $criteria->setLimit(1);

            /** @var \Shopware\Core\Content\Product\ProductEntity|null $product */
            $product = $this->productRepository->search($criteria, $context->getContext())->first();

            if ($product === null) {
                return null;
            }

            $name = (string) ($product->getTranslation('name') ?? $product->getName() ?? $sku);
            $url = '/detail/' . $product->getId();

            return ['name' => $name, 'url' => $url];
        }))->register($converter);

        return $converter->convert($source);
    }
}
