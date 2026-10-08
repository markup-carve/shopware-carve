<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\Carve\Lint\MarkdownHabitLinter;
use MarkupCarve\Carve\Lint\ReferenceLinter;
use MarkupCarve\Carve\Lint\RetiredSpellingLinter;
use MarkupCarve\Carve\Lint\SourceLinter;
use MarkupCarve\Carve\Lint\SourceOffsets;
use MarkupCarve\Carve\Node\Inline\Link;

class CarvePreviewLinter
{
    /**
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    public function lint(string $source, CarveConverter $converter, string $html, bool $referenceChecks = true): array
    {
        $warnings = array_merge(
            (new MarkdownHabitLinter())->lint($source),
            (new RetiredSpellingLinter())->lint($source),
            (new SourceLinter())->lint($source),
        );
        if ($referenceChecks) {
            foreach ((new ReferenceLinter())->lint($source, ['extensions' => array_values($converter->getExtensions())]) as $warning) {
                if ($warning->rule !== 'broken-fragment-link') {
                    $warnings[] = $warning;
                }
            }
        }
        array_push($warnings, ...$this->fragmentWarnings($source, $converter, $html));

        return $warnings;
    }

    /**
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    private function fragmentWarnings(string $source, CarveConverter $converter, string $html): array
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
        $converter->getParser()->enablePositionTracking();
        $ast = $converter->parse($source);
        $converter->render($ast);
        $pending = [$ast];
        $warnings = [];
        $map = SourceOffsets::map($source);
        $length = strlen($source);
        while ($pending !== []) {
            $node = array_pop($pending);
            array_push($pending, ...$node->getChildren());
            if (!$node instanceof Link) {
                continue;
            }
            $href = $node->getDestination() ?? '';
            if (!str_starts_with($href, '#') || !isset($hrefs[$href])) {
                continue;
            }
            $fragment = explode(':~:', substr($href, 1), 2)[0];
            $decoded = rawurldecode($fragment);
            if ($fragment === '' || strtolower($decoded) === 'top' || isset($ids[$fragment]) || isset($ids[$decoded])) {
                continue;
            }
            $pos = $node->getPos();
            if ($pos !== null) {
                $warnings[] = new LintWarning(
                    $pos->startLine,
                    $pos->startColumn,
                    'broken-fragment-link',
                    'Link to "' . $href . '" matches no id in this preview.',
                    SourceOffsets::toByte($pos->startOffset, $map, $length),
                    SourceOffsets::toByte($pos->endOffset, $map, $length),
                );
            }
        }

        return $warnings;
    }
}
