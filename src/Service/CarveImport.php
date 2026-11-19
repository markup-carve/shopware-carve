<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
use RuntimeException;

/**
 * Converts HTML (and, while carve-php 0.2 has no importer for it, refuses Markdown) to Carve source.
 */
final class CarveImport
{
    public static function supports(string $format): bool
    {
        return $format === 'html';
    }

    /**
     * @param string $format `html` or `markdown`.
     * @param string $input
     *
     * @throws \RuntimeException For `markdown`: carve-php 0.2 has no Markdown importer.
     *
     * @return array{source: string, diagnostics: array<\MarkupCarve\Carve\Diagnostics\Diagnostic>, report: array<string, mixed>}
     */
    public static function convert(string $format, string $input): array
    {
        if (!self::supports($format)) {
            throw new RuntimeException(sprintf('The Carve "%s" importer is not available in carve-php 0.2; only HTML can be imported.', $format));
        }
        $result = Carve::importHtml($input);

        return [
            'source' => $result->source,
            'diagnostics' => $result->diagnostics,
            'report' => [
                'sourceFormat' => 'html',
                'diagnostics' => array_map(static fn (Diagnostic $diagnostic): array => $diagnostic->toArray(), $result->diagnostics),
            ],
        ];
    }
}
