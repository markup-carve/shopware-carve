<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

/**
 * One render of Carve source through the include pass.
 *
 * `expanded` says whether the pass ran at all, so a caller can tell a document
 * with no directives from one whose directives were left literal by the gate.
 */
class CarveIncludeResult
{
    /**
     * Stand-in for an identity that names anything but a path inside the root.
     *
     * @var string
     */
    public const OUTSIDE_ROOT = '[outside-root]';

    /**
     * @param string $html
     * @param bool $expanded
     * @param array<array{rule: string|null, message: string, file: string|null, line: int, column: int}> $warnings
     * @param array<array{path: string, resolved: bool}> $dependencies
     * @param int $suppressedWarnings
     */
    public function __construct(
        public readonly string $html,
        public readonly bool $expanded = false,
        public readonly array $warnings = [],
        public readonly array $dependencies = [],
        public readonly int $suppressedWarnings = 0,
    ) {
    }

    /**
     * @return array{html: string, expanded: bool, warnings: array<array<string, mixed>>, dependencies: array<array{path: string, resolved: bool}>, suppressedWarnings: int}
     */
    public function toArray(): array
    {
        return [
            'html' => $this->html,
            'expanded' => $this->expanded,
            'warnings' => $this->warnings,
            'dependencies' => $this->dependencies,
            'suppressedWarnings' => $this->suppressedWarnings,
        ];
    }
}
