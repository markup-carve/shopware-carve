<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Tooling;

/**
 * Counts the corpus pairs the spec's example pages declare.
 *
 * This lives in a file rather than inside the engine-drift workflow because a
 * counter embedded in a YAML `run:` block cannot be unit-tested, and the
 * one-pair-per-block defect it replaces propagated across the org unnoticed for
 * exactly that reason (carve#2824).
 *
 * Port of the spec's scripts/lib/example-pair-census.mjs: every `carve` fence
 * inside a `::: compare` block is one pair, and nothing inside an open fence is
 * markup.
 */
final class DeclaredCorpusPairs
{
    /**
     * @param array<int, string> $lines
     */
    public static function countInLines(array $lines): int
    {
        $declared = 0;
        $marker = null;
        $fence = null;

        foreach ($lines as $line) {
            if ($fence !== null) {
                if (str_starts_with($line, $fence) && trim(substr($line, strlen($fence))) === '') {
                    $fence = null;
                }

                continue;
            }

            $ticks = strspn($line, '`');
            if ($ticks >= 3) {
                $fence = substr($line, 0, $ticks);
                if ($marker !== null && trim(substr($line, $ticks)) === 'carve') {
                    $declared++;
                }

                continue;
            }

            $trimmed = trim($line);
            $colons = strspn($trimmed, ':');
            if ($colons < 3) {
                continue;
            }

            if ($marker === null) {
                if (preg_match('/^[ \t]+compare(?:[ \t]|$)/', substr($trimmed, $colons)) === 1) {
                    $marker = substr($trimmed, 0, $colons);
                }

                continue;
            }

            if ($trimmed === $marker) {
                $marker = null;
            }
        }

        return $declared;
    }

    public static function countInSource(string $source): int
    {
        return self::countInLines(explode("\n", $source));
    }
}
