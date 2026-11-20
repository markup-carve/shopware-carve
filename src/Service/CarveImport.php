<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
use RuntimeException;

/**
 * Converts HTML, Markdown, GitHub Flavored Markdown or Djot to Carve source.
 */
final class CarveImport
{
    private const FORMATS = ['html', 'markdown', 'gfm', 'djot'];

    public static function supports(string $format): bool
    {
        return in_array($format, self::FORMATS, true);
    }

    /**
     * @param string $format `html`, `markdown`, `gfm` or `djot`.
     * @param string $input
     *
     * @throws \RuntimeException For a format the engine does not import.
     *
     * @return array{source: string, diagnostics: array<\MarkupCarve\Carve\Diagnostics\Diagnostic>, report: array<string, mixed>}
     */
    public static function convert(string $format, string $input): array
    {
        if (!self::supports($format)) {
            throw new RuntimeException(sprintf('The Carve "%s" importer is not available in carve-php 0.2.', $format));
        }
        $result = $format === 'html' ? Carve::importHtml($input) : Carve::importDocument($input, ['from' => $format]);

        return [
            'source' => $result->source,
            'diagnostics' => $result->diagnostics,
            'report' => [
                'sourceFormat' => $format,
                'diagnostics' => array_map(static fn (Diagnostic $diagnostic): array => $diagnostic->toArray(), $result->diagnostics),
            ],
        ];
    }
}
