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
 * The `ShopwareCarve.config.profile` setting optionally applies a carve-php Profile to the HTML
 * converter. Profiles strip or degrade disallowed node types (e.g. headings, images, tables) and
 * are useful for untrusted user content such as reviews or Q&A. Applies to HTML output only;
 * the text and markdown singletons are not profiled.
 *
 * HTML converters are memoized per request by a config signature (allowRawHtml, smartQuotes,
 * smartQuotesLocale, profile, enableMermaid, enableCharts). Within one DI-singleton lifetime
 * (one request) the config is stable, so the same converter instance is reused across all
 * toHtml() calls - avoiding repeated CarveConverter construction and ~10 addExtension() calls
 * for every rendered field (e.g. a product listing with N descriptions).
 *
 * Reuse is safe: CarveConverter.render() resets its shared render context at the start of each
 * call (HtmlRenderer.sharedRenderContext.reset()), and every stateful extension in the suite
 * implements ResettableExtensionInterface and is cleared via extension.clear() before each
 * render. The existing text/markdown singleton fields in this class already rely on the same
 * guarantee.
 *
 * The text and markdown converters have no config-dependent state and are constructed once
 * as stateless singletons.
 */
class CarveRenderer
{
    private CarveConverter $text;

    private CarveConverter $markdown;

    /**
     * Memoized HTML converters keyed by config signature.
     *
     * A single request normally hits only one key (stable config), so this
     * array stays at 1-2 entries for the lifetime of the service instance.
     *
     * @var array<string, \MarkupCarve\Carve\CarveConverter>
     */
    private array $htmlConverters = [];

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

        return $this->getCachedHtmlConverter()->convert($source);
    }

    /**
     * Renders UGC (user-generated content, e.g. product reviews) to safe HTML.
     *
     * Always forces safe mode on (raw HTML is never passed through) and always
     * applies the comment profile, regardless of global plugin config. This
     * makes the output safe to emit without a separate sanitizer even when
     * allowRawHtml or a permissive profile is configured globally.
     *
     * Smart quotes still respect the global config - they are harmless for UGC.
     */
    public function toHtmlUgc(?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return $this->getCachedUgcConverter()->convert($source);
    }

    /**
     * Returns a memoized HTML converter for the current config.
     *
     * Computes a signature from all config keys that affect the converter.
     * On first call (or config change) builds and caches a fresh converter;
     * on subsequent calls with the same config returns the cached instance.
     */
    private function getCachedHtmlConverter(): CarveConverter
    {
        $sig = $this->buildHtmlSignature();

        return $this->htmlConverters[$sig] ??= $this->buildHtmlConverter();
    }

    /**
     * Returns a memoized UGC converter.
     *
     * UGC converters always use forced safe mode and the comment profile.
     * Only the smartQuotes config can vary, so the cache key is simpler.
     */
    private function getCachedUgcConverter(): CarveConverter
    {
        $sq = $this->configBool($this->systemConfig->get('ShopwareCarve.config.smartQuotes'));
        $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
        $sig = 'ugc|' . ($sq ? '1' : '0') . '|' . (is_string($loc) ? $loc : '');

        return $this->htmlConverters[$sig] ??= $this->buildUgcConverter();
    }

    /**
     * Computes a cache key from every config setting that affects the HTML converter.
     *
     * Values are normalized to their effective form (bool -> 0/1, unknown string -> '')
     * so that raw Shopware config representations ("true"/true/1) map to the same key.
     */
    private function buildHtmlSignature(): string
    {
        $allow = $this->configBool($this->systemConfig->get('ShopwareCarve.config.allowRawHtml'), false);
        $sq = $this->configBool($this->systemConfig->get('ShopwareCarve.config.smartQuotes'));
        $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
        $mermaid = $this->configBool($this->systemConfig->get('ShopwareCarve.config.enableMermaid'));
        $charts = $this->configBool($this->systemConfig->get('ShopwareCarve.config.enableCharts'));
        $profile = $this->systemConfig->get('ShopwareCarve.config.profile');

        return implode('|', [
            $allow ? '1' : '0',
            $sq ? '1' : '0',
            is_string($loc) ? $loc : '',
            $mermaid ? '1' : '0',
            $charts ? '1' : '0',
            is_string($profile) ? $profile : '',
        ]);
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
     * Called by getCachedHtmlConverter() on cache miss only. Reads the current
     * config values and delegates to buildConverter(). Config changes between
     * requests produce a different signature and therefore a fresh build.
     */
    private function buildHtmlConverter(): CarveConverter
    {
        return $this->buildConverter(forceCommentProfile: false, forceSafeMode: false);
    }

    /**
     * Builds a UGC HTML converter with forced safe mode and comment profile.
     *
     * Safe mode is forced on (safeMode: true) so raw HTML is always escaped,
     * regardless of the global allowRawHtml config. The comment profile is always
     * applied so headings, images, tables, raw HTML, etc. are denied even if the
     * global profile is 'none' or 'full'.
     */
    private function buildUgcConverter(): CarveConverter
    {
        return $this->buildConverter(forceCommentProfile: true, forceSafeMode: true);
    }

    private function buildConverter(bool $forceCommentProfile, bool $forceSafeMode): CarveConverter
    {
        if ($forceSafeMode) {
            $safe = true;
        } else {
            $allow = $this->configBool($this->systemConfig->get('ShopwareCarve.config.allowRawHtml'), false);
            $safe = $allow ? false : true;
        }

        $converter = new CarveConverter(safeMode: $safe);

        $converter->addExtensions($this->shopwareExtensions());

        if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.smartQuotes'))) {
            $loc = $this->systemConfig->get('ShopwareCarve.config.smartQuotesLocale');
            $converter->addExtension(new SmartQuotesExtension(locale: is_string($loc) && $loc !== '' ? $loc : 'en'));
        }

        if (!$forceCommentProfile) {
            if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.enableMermaid'))) {
                $converter->addExtension(FencedRenderExtension::mermaid());
            }

            if ($this->configBool($this->systemConfig->get('ShopwareCarve.config.enableCharts'))) {
                $converter->addExtension(FencedRenderExtension::chart());
            }
        }

        if ($forceCommentProfile) {
            $converter->setProfile(Profile::comment());
        } else {
            $profileKey = $this->systemConfig->get('ShopwareCarve.config.profile');
            $profile = $this->resolveProfile(is_string($profileKey) ? $profileKey : null);
            if ($profile !== null) {
                $converter->setProfile($profile);
            }
        }

        return $converter;
    }

    /**
     * Maps a config string to a carve-php Profile preset.
     *
     * Returns null for 'none', null, or any unknown value so that setProfile() is never
     * called and the converter runs without profile restrictions (the default).
     * Only the HTML converter uses profiles; text and markdown singletons are not profiled.
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

    /**
     * Returns the set of unconditionally-enabled HTML extensions for Shopware storefront use.
     *
     * These are pure-PHP, no-extra-JS extensions that add useful rich-content features
     * without requiring any client-side dependencies.
     *
     * @return list<\MarkupCarve\Carve\Extension\ExtensionInterface>
     */
    private function shopwareExtensions(): array
    {
        return [
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
