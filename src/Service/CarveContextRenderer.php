<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\AdmonitionExtension;
use MarkupCarve\Carve\Extension\AutolinkExtension;
use MarkupCarve\Carve\Extension\CodeGroupExtension;
use MarkupCarve\Carve\Extension\DetailsExtension;
use MarkupCarve\Carve\Extension\ExternalLinksExtension;
use MarkupCarve\Carve\Extension\FencedRenderExtension;
use MarkupCarve\Carve\Extension\InlineFootnotesExtension;
use MarkupCarve\Carve\Extension\ListTableExtension;
use MarkupCarve\Carve\Extension\SmartQuotesExtension;
use MarkupCarve\Carve\Extension\SpoilerExtension;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Extension\TabsExtension;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Shopware\Inline\ProductInlineMatcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Context-aware Carve rendering. Converters are memoized per (SalesChannelContext, config)
 * pair so that a page rendering N product descriptions or review fields builds and
 * registers extensions only once instead of N times.
 *
 * Raw HTML passthrough is controlled by `ShopwareCarve.config.allowRawHtml` (default: false).
 * Dangerous URL schemes (javascript:, data:, vbscript:, file:) and on-event/srcdoc/formaction
 * attributes are ALWAYS stripped regardless of allowRawHtml - they are a baseline provided by
 * carve-php's safeMode and are never toggled off.
 *
 * The `ShopwareCarve.config.profile` setting optionally applies a carve-php Profile to the HTML
 * converter to restrict which node types are rendered (useful for UGC). Applies to HTML output only.
 *
 * `:product[SKU]` is parsed by carve-php as a generic InlineExtension node (type
 * "product"). A render.inline_extension event listener intercepts those nodes and
 * resolves them to <a> links via a product repository lookup. The closure capturing
 * the SalesChannelContext is bound once per context object and reused across calls.
 *
 * Cache key: spl_object_id(context) . '|' . config-signature. Different SalesChannelContext
 * objects (e.g. across test suites or multi-context workers) produce distinct keys and
 * each gets its own converter. Within a normal request a single context object is used,
 * so the cache converges to one entry and the converter is built once.
 *
 * Reuse safety: CarveConverter.render() resets its shared render context and calls clear()
 * on every ResettableExtensionInterface extension before each render. This is the same
 * guarantee the text/markdown singletons in CarveRenderer rely on.
 */
class CarveContextRenderer
{
    /**
     * Memoized converters keyed by context-id + config signature.
     *
     * @var array<string, \MarkupCarve\Carve\CarveConverter>
     */
    private array $converters = [];

    /**
     * @param \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\Content\Product\ProductCollection> $productRepository
     * @param \Shopware\Core\System\SystemConfig\SystemConfigService $systemConfig
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

        return $this->getCachedConverter($context)->convert($source);
    }

    /**
     * Returns a memoized converter for the given context and current config.
     *
     * The cache key combines the object identity of the SalesChannelContext (so
     * different context objects each get their own closure-bound converter) with a
     * normalized config signature (so a config change still yields a fresh converter).
     */
    private function getCachedConverter(SalesChannelContext $context): CarveConverter
    {
        $sig = spl_object_id($context) . '|' . $this->buildContextSignature();

        return $this->converters[$sig] ??= $this->buildConverter($context);
    }

    /**
     * Computes a cache key from every config setting that affects the context converter.
     */
    private function buildContextSignature(): string
    {
        $allow = $this->configBool($this->systemConfig->get('ShopwareCarve.config.allowRawHtml'), false);
        $sq = $this->configBool($this->systemConfig->get('ShopwareCarve.config.smartQuotes'));
        $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
        $mermaid = $this->configBool($this->systemConfig->get('ShopwareCarve.config.enableMermaid'));
        $charts = $this->configBool($this->systemConfig->get('ShopwareCarve.config.enableCharts'));
        $plantuml = $this->configBool($this->systemConfig->get('ShopwareCarve.config.enablePlantuml'));
        $profile = $this->systemConfig->get('ShopwareCarve.config.profile');

        return implode('|', [
            $allow ? '1' : '0',
            $sq ? '1' : '0',
            is_string($loc) ? $loc : '',
            $mermaid ? '1' : '0',
            $charts ? '1' : '0',
            $plantuml ? '1' : '0',
            is_string($profile) ? $profile : '',
        ]);
    }

    private function buildConverter(SalesChannelContext $context): CarveConverter
    {
        $allow = $this->configBool($this->systemConfig->get('ShopwareCarve.config.allowRawHtml'), false);
        $safe = $allow ? false : true;

        $converter = new CarveConverter(safeMode: $safe);

        $converter->addExtensions([
            new AdmonitionExtension(),
            new CodeGroupExtension(),
            new DetailsExtension(),
            new SpoilerExtension(),
            new TabsExtension(),
            new ListTableExtension(),
            new InlineFootnotesExtension(),
            new AutolinkExtension(),
            new ExternalLinksExtension(rel: 'nofollow noopener', target: '_blank'),
            new TableOfContentsExtension(),
        ]);

        if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.smartQuotes'))) {
            $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
            $converter->addExtension(new SmartQuotesExtension(locale: is_string($loc) && $loc !== '' ? $loc : 'en'));
        }

        if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.enableMermaid'))) {
            $converter->addExtension(FencedRenderExtension::mermaid());
        }

        if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.enableCharts'))) {
            $converter->addExtension(FencedRenderExtension::chart());
        }

        if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.enablePlantuml'))) {
            // Built via the generic constructor, not FencedRenderExtension::plantuml(),
            // so it works on the released carve-php (the preset only exists on dev-main).
            $converter->addExtension(new FencedRenderExtension(language: ['plantuml', 'puml'], cssClass: 'plantuml'));
        }

        $profileKey = $this->systemConfig->get('ShopwareCarve.config.profile');
        $profile = $this->resolveProfile(is_string($profileKey) ? $profileKey : null);
        if ($profile !== null) {
            $converter->setProfile($profile);
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

            $name = (string)($product->getTranslation('name') ?? $product->getName() ?? $sku);
            $url = '/detail/' . $product->getId();

            return ['name' => $name, 'url' => $url];
        }))->register($converter);

        return $converter;
    }

    /**
     * Maps a config string to a carve-php Profile preset.
     *
     * Returns null for 'none', null, or any unknown value so that setProfile() is never
     * called and the converter runs without profile restrictions (the default).
     * Only the HTML converter uses profiles; text and markdown targets are not profiled.
     */
    private function resolveProfile(?string $key): ?Profile
    {
        return match ($key) {
            'article' => Profile::article(),
            'comment' => Profile::comment(),
            'minimal' => Profile::minimal(),
            'full' => Profile::full(),
            default => null,
        };
    }

    private function configBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
