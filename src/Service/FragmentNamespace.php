<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use DOMDocument;
use DOMElement;
use RuntimeException;
use Symfony\Contracts\Service\ResetInterface;

class FragmentNamespace implements ResetInterface
{
    private int $sequence = 0;

    public function next(): string
    {
        return 'fragment-' . ++$this->sequence;
    }

    public function reset(): void
    {
        $this->sequence = 0;
    }

    public static function apply(string $html, ?string $namespace): string
    {
        if ($namespace === null || $html === '') {
            return $html;
        }
        $prefix = 'carve-' . substr(hash('sha256', $namespace), 0, 16) . '-';
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $ids = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('id')) {
                $id = $element->getAttribute('id');
                $ids[$id] = $prefix . $id;
                $element->setAttribute('id', $prefix . $id);
            }
            if ($element->tagName === 'input' && $element->getAttribute('type') === 'radio' && $element->hasAttribute('name')) {
                $element->setAttribute('name', $prefix . $element->getAttribute('name'));
            }
        }
        foreach ($document->getElementsByTagName('*') as $element) {
            $href = $element->getAttribute('href');
            if (str_starts_with($href, '#') && isset($ids[substr($href, 1)])) {
                $element->setAttribute('href', '#' . $ids[substr($href, 1)]);
            }
            self::rewriteReferences($element, $ids);
        }
        $output = '';
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            throw new RuntimeException('Unable to namespace the rendered HTML fragment.');
        }
        foreach ($body->childNodes as $node) {
            $output .= $document->saveHTML($node);
        }

        return $output;
    }

    /**
     * @param \DOMElement $element
     * @param array<string, string> $ids
     */
    private static function rewriteReferences(DOMElement $element, array $ids): void
    {
        foreach (['for', 'aria-controls', 'aria-labelledby', 'aria-describedby', 'headers'] as $attribute) {
            if (!$element->hasAttribute($attribute)) {
                continue;
            }
            $references = preg_split('/\s+/', $element->getAttribute($attribute)) ?: [];
            $element->setAttribute($attribute, implode(' ', array_map(static fn (string $id): string => $ids[$id] ?? $id, $references)));
        }
    }
}
