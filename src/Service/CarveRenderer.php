<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Closure;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Transform\IncludeExpander;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveRenderer
{
    private readonly CarveConverterFactory $factory;

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

    public function __construct(
        SystemConfigService $systemConfig,
        private readonly ?CarveIncludeGate $includeGate = null,
        ?CarveConverterFactory $factory = null,
    ) {
        $this->factory = $factory ?? new CarveConverterFactory($systemConfig);
        $this->text = CarveConverter::plainText();
        $this->markdown = CarveConverter::markdown();
    }

    /**
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    public function lint(string $source, ?string $salesChannelId = null, ?string $locale = null, bool $referenceChecks = true, ?string $html = null): array
    {
        $converter = $this->factory->create($salesChannelId, $locale);

        return (new CarvePreviewLinter())->lint($source, $converter, $html ?? $converter->convert($source), $referenceChecks);
    }

    public function toHtml(?string $source, ?string $salesChannelId = null, ?string $locale = null, ?string $namespace = null): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return FragmentNamespace::apply($this->converter($salesChannelId, $locale)->convert($source), $namespace);
    }

    /**
     * Renders CMS-authored source, expanding include directives when the gate
     * admits this context.
     *
     * Only the CMS element and the administration preview reach this method.
     * Product, category and manufacturer fields go through toHtml(), whoever
     * wrote them, so a directive stored in one of those stays literal.
     *
     * @param string|null $source
     * @param \Shopware\Core\Framework\Context $context
     * @param string|null $salesChannelId
     * @param string|null $locale
     * @param string|null $namespace
     * @param \MarkupCarve\Carve\CarveConverter|null $converter
     * @param \Closure(\MarkupCarve\Carve\Node\Document): void|null $prepare
     */
    public function toHtmlWithIncludes(
        ?string $source,
        Context $context,
        ?string $salesChannelId = null,
        ?string $locale = null,
        ?string $namespace = null,
        ?CarveConverter $converter = null,
        ?Closure $prepare = null,
    ): CarveIncludeResult {
        if ($source === null || trim($source) === '') {
            return new CarveIncludeResult('');
        }

        $converter ??= $this->converter($salesChannelId, $locale);
        $resolver = $this->includeGate?->resolver();
        if ($this->includeGate === null || $resolver === null || !$this->includeGate->isGranted($context)) {
            $document = $converter->parse($source);
            $prepare?->__invoke($document);

            return new CarveIncludeResult(FragmentNamespace::apply($converter->render($document), $namespace));
        }

        // No current path: CMS source lives in the database, so the root is the
        // only base a relative directive can resolve against.
        $expander = new IncludeExpander(
            resolver: $resolver,
            source: $source,
            extensions: $converter->getExtensions(),
        );
        $document = $converter->transform($converter->parse($source), $expander);

        $prepare?->__invoke($document);

        return new CarveIncludeResult(
            html: FragmentNamespace::apply($converter->render($document), $namespace),
            expanded: true,
            warnings: array_map(fn ($warning): array => [
                'rule' => $warning->getRule(),
                'message' => $warning->getMessage(),
                'file' => $this->includeGate->containedIdentity($warning->getFile()),
                'line' => $warning->getLine(),
                'column' => $warning->getColumn(),
            ], $expander->getWarnings()),
            dependencies: array_map(fn ($dependency): array => [
                'path' => $this->includeGate->containedIdentity($dependency->getTarget()) ?? CarveIncludeResult::OUTSIDE_ROOT,
                'resolved' => $dependency->isResolved(),
            ], $expander->getDependencies()),
            suppressedWarnings: $expander->getSuppressedWarnings(),
        );
    }

    /**
     * Renders UGC (user-generated content, e.g. product reviews) to safe HTML.
     *
     * Always forces safe mode on (raw HTML is never passed through) and always
     * applies the comment profile, regardless of global plugin config. This
     * makes the output safe to emit without a separate sanitizer even when
     * allowRawHtml or a permissive profile is configured globally.
     *
     * Smart quotes follow channel settings. Configured symbols are excluded from UGC.
     */
    public function toHtmlUgc(?string $source, ?string $salesChannelId = null, ?string $locale = null): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return $this->converter($salesChannelId, $locale, true)->convert($source);
    }

    public function converter(?string $salesChannelId = null, ?string $locale = null, bool $ugc = false): CarveConverter
    {
        $signature = ($ugc ? 'ugc|' : '') . $this->factory->signature($salesChannelId, $locale, $ugc);
        if (count($this->htmlConverters) > 32) {
            $this->htmlConverters = [];
        }

        return $this->htmlConverters[$signature] ??= $this->factory->create($salesChannelId, $locale, $ugc);
    }

    public function toText(?string $source): string
    {
        return $source === null || trim($source) === '' ? '' : $this->text->convert($source);
    }

    public function toMarkdown(?string $source): string
    {
        return $source === null || trim($source) === '' ? '' : $this->markdown->convert($source);
    }
}
