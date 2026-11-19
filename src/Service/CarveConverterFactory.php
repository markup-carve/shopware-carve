<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

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

    public function create(?string $salesChannelId = null, ?string $locale = null, bool $ugc = false): CarveSetup
    {
        $profile = $ugc ? 'comment' : $this->get('profile', $salesChannelId);
        $setup = new CarveSetup([
            'rawHtml' => !$ugc && $this->bool('allowRawHtml', $salesChannelId),
            'symbols' => $ugc ? [] : $this->symbols($salesChannelId),
            'profile' => in_array($profile, ['full', 'article', 'comment', 'minimal'], true) ? $profile : 'full',
        ]);
        $extensions = new CarveHostExtensions();
        $extensions->register($setup);
        $extensions->externalLinks(
            $setup,
            $this->bool('externalLinksNofollow', $salesChannelId, true) ? 'nofollow noopener' : 'noopener',
            $this->bool('externalLinksNewTab', $salesChannelId, true) ? '_blank' : '',
        );
        if ($this->bool('smartQuotes', $salesChannelId)) {
            $configured = $this->get('smartQuotesLocale', $salesChannelId);
            $effective = is_string($configured) && $configured !== '' ? $configured : 'en';
            if ($effective === 'auto') {
                $effective = $locale === 'de-CH' ? 'de-CH' : explode('-', $locale ?? 'en')[0];
            }
            $extensions->smartQuotes($setup, $effective);
        }
        if (!$ugc) {
            (new CarvePresentation())->register($setup);
            (new CarveLocalData())->register($setup);
            foreach (['enableMermaid' => 'mermaid', 'enableCharts' => 'chart', 'enablePlantuml' => 'plantuml'] as $key => $kind) {
                if ($this->bool($key, $salesChannelId)) {
                    $extensions->fenced($setup, $kind);
                }
            }
        }

        return $setup;
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
