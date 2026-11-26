<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Node;

class CarvePreviewLinter
{
    /**
     * Rules whose findings an included file can answer, so they are dropped when reference checks are off.
     *
     * @var array<string>
     */
    private const REFERENCE_RULES = [
        'broken-crossref', 'duplicate-footnote-definition', 'duplicate-heading-id', 'empty-reference-definition',
        'footnote-labels-differ-only-in-whitespace', 'unresolved-footnote', 'unresolved-reference-link',
        'unused-footnote-definition', 'unused-link-reference-definition',
    ];

    /**
     * @return list<\MarkupCarve\Shopware\Service\CarveLintWarning>
     */
    public function lint(string $source, CarveSetup $setup, string $html, bool $referenceChecks = true): array
    {
        $warnings = [];
        foreach (Carve::lint($source, $setup->parseOptions()) as $diagnostic) {
            // The preview checks fragments against its own rendered ids below.
            if ($diagnostic->code === 'broken-fragment-link' || (!$referenceChecks && in_array($diagnostic->code, self::REFERENCE_RULES, true))) {
                continue;
            }
            $warnings[] = CarveLintWarning::fromDiagnostic($diagnostic, $source);
        }
        array_push($warnings, ...$this->fragmentWarnings($source, $setup, $html));
        usort($warnings, static fn (CarveLintWarning $a, CarveLintWarning $b): int => [$a->line, $a->column] <=> [$b->line, $b->column]);

        return $warnings;
    }

    /**
     * @return list<\MarkupCarve\Shopware\Service\CarveLintWarning>
     */
    private function fragmentWarnings(string $source, CarveSetup $setup, string $html): array
    {
        if (!str_contains($source, '#')) {
            return [];
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $ids = [];
        foreach ($xpath->query('//*[@id][not(ancestor::template)] | //a[@name][not(ancestor::template)]') ?: [] as $element) {
            if ($element instanceof DOMElement) {
                foreach (strtolower($element->tagName) === 'a' ? ['id', 'name'] : ['id'] as $attribute) {
                    if ($element->hasAttribute($attribute)) {
                        $ids[$element->getAttribute($attribute)] = true;
                    }
                }
            }
        }
        $hrefs = [];
        foreach ($xpath->query('//a[@href][not(ancestor::template)]') ?: [] as $element) {
            if ($element instanceof DOMElement) {
                $hrefs[$element->getAttribute('href')] = true;
            }
        }
        $warnings = [];
        CarveTree::walk($setup->parse($source), static function (Node $node) use ($hrefs, $ids, $source, &$warnings): void {
            $href = $node->f['href'] ?? null;
            if ($node->type !== 'link' || !is_string($href) || !str_starts_with($href, '#') || !isset($hrefs[$href]) || $node->start < 0) {
                return;
            }
            $fragment = explode(':~:', substr($href, 1), 2)[0];
            $decoded = rawurldecode($fragment);
            if ($fragment === '' || strtolower($decoded) === 'top' || isset($ids[$fragment]) || isset($ids[$decoded])) {
                return;
            }
            $warnings[] = CarveLintWarning::at(
                $source,
                $node->start,
                $node->end,
                'broken-fragment-link',
                'Link to "' . $href . '" matches no id in this preview.',
            );
        });

        return $warnings;
    }
}
