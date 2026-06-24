<?php declare(strict_types=1);

namespace Carve\Shopware\Service;

use Carve\CarveConverter;
use Carve\Extension\AdmonitionExtension;
use Carve\Extension\AutolinkExtension;
use Carve\Extension\DetailsExtension;
use Carve\Extension\ExternalLinksExtension;
use Carve\Extension\FencedRenderExtension;
use Carve\Extension\InlineFootnotesExtension;
use Carve\Extension\ListTableExtension;
use Carve\Extension\SmartQuotesExtension;
use Carve\Extension\TableOfContentsExtension;
use Carve\Shopware\Inline\ProductInlineMatcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Context-aware Carve rendering. Builds a per-call converter with raw HTML passthrough
 * controlled by the `ShopwareCarve.config.allowRawHtml` system config setting (default: false),
 * and with the :product[SKU] inline extension bound to the active sales channel, so authored
 * product references resolve to storefront links for the current channel.
 *
 * Dangerous URL schemes (javascript:, data:, vbscript:, file:) and on-event/srcdoc/formaction
 * attributes are ALWAYS stripped regardless of allowRawHtml - they are a baseline provided by
 * carve-php's safeMode and are never toggled off.
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

        $allow = $this->systemConfig->get('ShopwareCarve.config.allowRawHtml');
        $safe = $allow === true ? false : true;

        $converter = new CarveConverter(safeMode: $safe);

        $converter->addExtensions([
            new AdmonitionExtension(),
            new DetailsExtension(),
            new ListTableExtension(),
            new InlineFootnotesExtension(),
            new AutolinkExtension(),
            new ExternalLinksExtension(rel: 'nofollow noopener', target: '_blank'),
            new TableOfContentsExtension(),
        ]);

        $sq = $this->systemConfig->get('ShopwareCarve.config.smartQuotes');
        if ($sq === true || $sq === '1' || $sq === 1) {
            $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
            $converter->addExtension(new SmartQuotesExtension(locale: is_string($loc) && $loc !== '' ? $loc : 'en'));
        }

        $mermaid = $this->systemConfig->get('ShopwareCarve.config.enableMermaid');
        if ($mermaid === true || $mermaid === '1' || $mermaid === 1) {
            $converter->addExtension(FencedRenderExtension::mermaid());
        }

        $charts = $this->systemConfig->get('ShopwareCarve.config.enableCharts');
        if ($charts === true || $charts === '1' || $charts === 1) {
            $converter->addExtension(FencedRenderExtension::chart());
        }

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
