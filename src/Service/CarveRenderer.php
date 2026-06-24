<?php declare(strict_types=1);

namespace Carve\Shopware\Service;

use Carve\CarveConverter;
use Carve\Extension\SmartQuotesExtension;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Thin wrapper around markup-carve/carve-php.
 *
 * HTML rendering runs with safe mode controlled by the `ShopwareCarve.config.safeMode` system
 * config setting (default: ON). When safe mode is ON, authored raw HTML is escaped, dangerous
 * URL schemes (javascript:, data:, vbscript:, file:) are neutralized, and
 * on-event/srcdoc/formaction attribute values are stripped. Output is therefore safe to emit
 * into the storefront without further sanitizing - the reason the Twig `carve`
 * filter may mark it is_safe => html.
 *
 * The HTML converter is built per call in toHtml() because it depends on runtime config
 * (safe mode / smart quotes) that can change in the Shopware admin without a cache:clear.
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
     * Called on every toHtml() invocation so that safe-mode / smart-quotes changes
     * made in the Shopware admin take effect immediately without requiring cache:clear.
     */
    private function buildHtmlConverter(): CarveConverter
    {
        $value = $this->systemConfig->get('ShopwareCarve.config.safeMode');
        $safe = $value === null ? true : (bool) $value;

        $converter = new CarveConverter(safeMode: $safe);

        $sq = $this->systemConfig->get('ShopwareCarve.config.smartQuotes');
        if ($sq === true || $sq === '1' || $sq === 1) {
            $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
            $converter->addExtension(new SmartQuotesExtension(locale: is_string($loc) && $loc !== '' ? $loc : 'en'));
        }

        return $converter;
    }

    private function render(CarveConverter $converter, ?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return $converter->convert($source);
    }
}
