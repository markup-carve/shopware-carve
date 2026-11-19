<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Diagnostics\Diagnostic;

/**
 * One lint finding with a line and a character column, the shape the preview, commands and write guard report.
 */
final class CarveLintWarning
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
        public readonly string $rule,
        public readonly string $message,
        public readonly int $start,
        public readonly int $end,
        public readonly ?string $file = null,
    ) {
    }

    /**
     * @param \MarkupCarve\Carve\Diagnostics\Diagnostic $diagnostic
     * @param string $source The text the diagnostic's byte range points into.
     * @param string|null $file
     */
    public static function fromDiagnostic(Diagnostic $diagnostic, string $source, ?string $file = null): self
    {
        return self::at($source, $diagnostic->startOffset, $diagnostic->endOffset, $diagnostic->code, $diagnostic->message, $file ?? $diagnostic->file);
    }

    public static function at(string $source, int $start, int $end, string $rule, string $message, ?string $file = null): self
    {
        $start = max(0, min($start, strlen($source)));
        $before = substr($source, 0, $start);
        $lineStart = strrpos($before, "\n");
        $column = mb_strlen($lineStart === false ? $before : substr($before, $lineStart + 1), 'UTF-8') + 1;

        return new self(substr_count($before, "\n") + 1, $column, $rule, $message, $start, max($start, $end), $file);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'line' => $this->line,
            'column' => $this->column,
            'rule' => $this->rule,
            'message' => $this->message,
            'start' => $this->start,
            'end' => $this->end,
        ];
        if ($this->file !== null) {
            $result['file'] = $this->file;
        }

        return $result;
    }
}
