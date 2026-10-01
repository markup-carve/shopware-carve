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

class CarveConverterFactory
{
    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function signature(?string $salesChannelId = null, ?string $locale = null, bool $ugc = false): string
    {
        $values = [$salesChannelId, $locale, $ugc];
        foreach (
            [
                'allowRawHtml', 'smartQuotes', 'smartQuotesLocale', 'profile', 'enableMermaid',
                'enableCharts', 'enablePlantuml', 'symbols', 'externalLinksNofollow', 'externalLinksNewTab',
            ] as $key
        ) {
            $values[] = $this->get($key, $salesChannelId);
        }

        return hash('sha256', serialize($values));
    }

    public function create(?string $salesChannelId = null, ?string $locale = null, bool $ugc = false): CarveConverter
    {
        $converter = new CarveConverter(
            safeMode: $ugc || !$this->bool('allowRawHtml', $salesChannelId),
            symbols: $ugc ? [] : $this->symbols($salesChannelId),
        );
        $converter->addExtensions([
            new AdmonitionExtension(), new CodeGroupExtension(), new DetailsExtension(),
            new SpoilerExtension(), new TabsExtension(), new ListTableExtension(),
            new InlineFootnotesExtension(), new AutolinkExtension(),
            new ExternalLinksExtension(
                rel: $this->bool('externalLinksNofollow', $salesChannelId, true) ? 'nofollow noopener' : 'noopener',
                target: $this->bool('externalLinksNewTab', $salesChannelId, true) ? '_blank' : '',
            ),
            new TableOfContentsExtension(),
        ]);
        if ($this->bool('smartQuotes', $salesChannelId)) {
            $configured = $this->get('smartQuotesLocale', $salesChannelId);
            $effective = is_string($configured) && $configured !== '' ? $configured : 'en';
            if ($effective === 'auto') {
                $effective = $locale === 'de-CH' ? 'de-CH' : explode('-', $locale ?? 'en')[0];
            }
            $converter->addExtension(new SmartQuotesExtension(locale: $effective));
        }
        if (!$ugc) {
            foreach (['enableMermaid' => 'mermaid', 'enableCharts' => 'chart', 'enablePlantuml' => 'plantuml'] as $key => $language) {
                if (!$this->bool($key, $salesChannelId)) {
                    continue;
                }
                $converter->addExtension(match ($language) {
                    'mermaid' => FencedRenderExtension::mermaid(),
                    'chart' => FencedRenderExtension::chart(),
                    default => new FencedRenderExtension(language: ['plantuml', 'puml'], cssClass: 'plantuml'),
                });
            }
        }
        $profile = $ugc ? Profile::comment() : match ($this->get('profile', $salesChannelId)) {
            'article' => Profile::article(), 'comment' => Profile::comment(),
            'minimal' => Profile::minimal(), 'full' => Profile::full(), default => null,
        };
        if ($profile !== null) {
            $converter->setProfile($profile);
        }

        return $converter;
    }

    public function get(string $name, ?string $salesChannelId = null): mixed
    {
        return $this->systemConfig->get('ShopwareCarve.config.' . $name, $salesChannelId);
    }

    public function bool(string $name, ?string $salesChannelId = null, bool $default = false): bool
    {
        $value = $this->get($name, $salesChannelId);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, string>
     */
    private function symbols(?string $salesChannelId): array
    {
        $configured = $this->get('symbols', $salesChannelId);
        $symbols = [];
        foreach (preg_split('/\R/', is_string($configured) ? $configured : '') ?: [] as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $name) === 1) {
                $symbols[$name] = $value;
            }
        }

        return $symbols;
    }
}
