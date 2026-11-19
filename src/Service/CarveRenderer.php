<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Closure;
use MarkupCarve\Carve\Carve;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CarveRenderer
{
    private readonly CarveConverterFactory $factory;

    /**
     * Memoized render setups keyed by config signature.
     *
     * A single request normally hits only one key (stable config), so this
     * array stays at 1-2 entries for the lifetime of the service instance.
     *
     * @var array<string, \MarkupCarve\Shopware\Service\CarveSetup>
     */
    private array $htmlConverters = [];

    public function __construct(
        SystemConfigService $systemConfig,
        private readonly ?CarveIncludeGate $includeGate = null,
        ?CarveConverterFactory $factory = null,
    ) {
        $this->factory = $factory ?? new CarveConverterFactory($systemConfig);
    }

    /**
     * @return list<\MarkupCarve\Shopware\Service\CarveLintWarning>
     */
    public function lint(string $source, ?string $salesChannelId = null, ?string $locale = null, bool $referenceChecks = true, ?string $html = null): array
    {
        $setup = $this->factory->create($salesChannelId, $locale);

        return (new CarvePreviewLinter())->lint($source, $setup, $html ?? $setup->render($source), $referenceChecks);
    }

    public function toHtml(?string $source, ?string $salesChannelId = null, ?string $locale = null, ?string $namespace = null): string
    {
        if ($source === null || trim($source) === '') {
            return '';
        }

        return FragmentNamespace::apply($this->converter($salesChannelId, $locale)->render($source), $namespace);
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
     * @param \MarkupCarve\Shopware\Service\CarveSetup|null $setup
     * @param \Closure(\MarkupCarve\Carve\Node): void|null $prepare
     */
    public function toHtmlWithIncludes(
        ?string $source,
        Context $context,
        ?string $salesChannelId = null,
        ?string $locale = null,
        ?string $namespace = null,
        ?CarveSetup $setup = null,
        ?Closure $prepare = null,
    ): CarveIncludeResult {
        if ($source === null || trim($source) === '') {
            return new CarveIncludeResult('');
        }

        $setup ??= $this->converter($salesChannelId, $locale);
        $resolver = $this->includeGate?->resolver();
        if ($this->includeGate === null || $resolver === null || !$this->includeGate->isGranted($context)) {
            $document = $setup->parse($source);
            $prepare?->__invoke($document);

            return new CarveIncludeResult(FragmentNamespace::apply($setup->render($document), $namespace));
        }

        // No current path: CMS source lives in the database, so the root is the
        // only base a relative directive can resolve against.
        $parsed = Carve::parseWithDiagnostics($source, $setup->parseOptions(['includes' => $resolver]));
        $document = $parsed->tree;

        $prepare?->__invoke($document);

        $warnings = [];
        foreach ($parsed->diagnostics as $diagnostic) {
            if (!str_starts_with($diagnostic->code, 'include-')) {
                continue;
            }
            $text = $diagnostic->file === null ? $source : $this->includedText($diagnostic->file);
            $at = CarveLintWarning::fromDiagnostic($diagnostic, $text ?? '');
            $warnings[] = [
                'rule' => $diagnostic->code,
                'message' => $diagnostic->message,
                'file' => $this->includeGate->containedIdentity($diagnostic->file),
                'line' => $text === null ? 0 : $at->line,
                'column' => $text === null ? 0 : $at->column,
            ];
        }

        return new CarveIncludeResult(
            html: FragmentNamespace::apply($setup->render($document), $namespace),
            expanded: true,
            warnings: $warnings,
            dependencies: array_map(fn (array $dependency): array => [
                'path' => $this->includeGate->containedIdentity((string)($dependency['file'] ?? $dependency['path'])) ?? CarveIncludeResult::OUTSIDE_ROOT,
                'resolved' => (bool)$dependency['read'],
            ], Carve::includeDependencies($document)),
        );
    }

    /**
     * The text of an included file named by its id below the include root, for line and column numbers.
     */
    private function includedText(string $file): ?string
    {
        $root = $this->includeGate?->root();
        $contained = $this->includeGate?->containedIdentity($file);
        if ($root === null || $contained === null || $contained === CarveIncludeResult::OUTSIDE_ROOT) {
            return null;
        }
        $path = $root . DIRECTORY_SEPARATOR . $contained;
        $text = is_file($path) ? file_get_contents($path) : false;

        return $text === false ? null : $text;
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

        return $this->converter($salesChannelId, $locale, true)->render($source);
    }

    public function converter(?string $salesChannelId = null, ?string $locale = null, bool $ugc = false): CarveSetup
    {
        $signature = ($ugc ? 'ugc|' : '') . $this->factory->signature($salesChannelId, $locale, $ugc);
        if (count($this->htmlConverters) > 32) {
            $this->htmlConverters = [];
        }

        return $this->htmlConverters[$signature] ??= $this->factory->create($salesChannelId, $locale, $ugc);
    }

    public function toText(?string $source): string
    {
        return $source === null || trim($source) === '' ? '' : self::write('text', $source);
    }

    public function toMarkdown(?string $source): string
    {
        return $source === null || trim($source) === '' ? '' : self::write('markdown', $source);
    }

    /**
     * Writes source through the engine's writer registry.
     *
     * @throws \MarkupCarve\Shopware\Service\CarveWriterUnavailable
     */
    public static function write(string $target, string $source): string
    {
        $writers = Carve::writers();
        if (!$writers->has($target)) {
            throw new CarveWriterUnavailable($target);
        }

        return $writers->write($target, Carve::parse($source));
    }
}
