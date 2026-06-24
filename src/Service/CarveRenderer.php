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
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Thin wrapper around markup-carve/carve-php.
 *
 * HTML rendering respects the `ShopwareCarve.config.allowRawHtml` system config setting
 * (default: false). When allowRawHtml is false (the default), authored raw HTML
 * (fenced ```=html blocks and inline `...`{=html} spans) is escaped rather than passed through.
 * Dangerous URL schemes (javascript:, data:, vbscript:, file:) and on-event/srcdoc/formaction
 * attributes are ALWAYS stripped regardless of this setting - they are a baseline provided by
 * carve-php's safeMode and are never toggled off by allowRawHtml. Output is therefore safe to
 * emit into the storefront without further sanitizing, which is why the Twig `carve` filter is
 * marked is_safe => html.
 *
 * The HTML converter is built per call in toHtml() because it depends on runtime config
 * (allowRawHtml / smart quotes) that can change in the Shopware admin without a cache:clear.
 * The text and markdown converters have no config-dependent state and are constructed once
 * as stateless singletons.
 */
class CarveRenderer
{
    private CarveConverter $text;
    private CarveConverter $markdown;

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
        $this->text = CarveConverter::plainText();
        $this->markdown = CarveConverter::markdown();
    }

    public function toHtml(?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return $this->buildHtmlConverter()->convert($source);
    }

    public function toText(?string $source): string
    {
        return $this->render($this->text, $source);
    }

    public function toMarkdown(?string $source): string
    {
        return $this->render($this->markdown, $source);
    }

    /**
     * Builds a fresh HTML converter from current system config.
     *
     * Called on every toHtml() invocation so that allowRawHtml / smart-quotes changes
     * made in the Shopware admin take effect immediately without requiring cache:clear.
     */
    private function buildHtmlConverter(): CarveConverter
    {
        $allow = $this->configBool($this->systemConfig->get('ShopwareCarve.config.allowRawHtml'), false);
        $safe = $allow ? false : true;

        $converter = new CarveConverter(safeMode: $safe);

        $converter->addExtensions($this->shopwareExtensions());

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

        return $converter;
    }

    /**
     * Returns the set of unconditionally-enabled HTML extensions for Shopware storefront use.
     *
     * These are pure-PHP, no-extra-JS extensions that add useful rich-content features
     * without requiring any client-side dependencies.
     *
     * @return list<\Carve\Extension\ExtensionInterface>
     */
    private function shopwareExtensions(): array
    {
        return [
            new AdmonitionExtension(),
            new DetailsExtension(),
            new ListTableExtension(),
            new InlineFootnotesExtension(),
            new AutolinkExtension(),
            new ExternalLinksExtension(rel: 'nofollow noopener', target: '_blank'),
            new TableOfContentsExtension(),
        ];
    }

    private function render(CarveConverter $converter, ?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return $converter->convert($source);
    }

    private function configBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
