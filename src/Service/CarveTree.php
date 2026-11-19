<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use Closure;
use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Node;

/**
 * Read helpers over the Carve 0.2 tree (`Node`), which has no typed node classes.
 */
final class CarveTree
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const FIELDS = [
        'text' => ['value', ''],
        'code' => ['value', ''],
        'literal_inline' => ['value', ''],
        'math' => ['value', ''],
        'image' => ['alt', ''],
        'abbreviation' => ['key', ''],
        'mention' => ['target', '@'],
        'tag' => ['name', '#'],
    ];

    /**
     * Visits a node and every node below it, slots and titles included.
     *
     * @param \MarkupCarve\Carve\Node $node
     * @param \Closure(\MarkupCarve\Carve\Node): void $visit
     */
    public static function walk(Node $node, Closure $visit): void
    {
        $visit($node);
        foreach (self::kids($node) as $kid) {
            self::walk($kid, $visit);
        }
    }

    /**
     * @return array<\MarkupCarve\Carve\Node>
     */
    public static function kids(Node $node): array
    {
        $kids = $node->children ?? [];
        foreach ($node->f as $value) {
            self::collect($value, $kids);
        }

        return $kids;
    }

    /**
     * Declares an extension in a document's header, which a document transform must do for an extension node it adds.
     */
    public static function declare(Node $document, string $name, string $version): void
    {
        $declared = $document->f['extensions'] ?? [];
        foreach ($declared as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return;
            }
        }
        $declared[] = ['name' => $name, 'version' => $version, 'required' => false, 'slots' => []];
        $document->f['extensions'] = $declared;
    }

    /**
     * The core node a host hook claimed (`:name[...]`, `::: name`, fence), or the node itself.
     */
    public static function core(Node $node): Node
    {
        return CarveSetup::claimed($node) ?? $node;
    }

    /**
     * The plain text of a node, as the engine's C2-H-143 plain text reads the inline types an extension holds.
     */
    public static function text(Node $node): string
    {
        $field = self::FIELDS[$node->type] ?? null;
        if ($field !== null) {
            return $field[1] . (string)($node->f[$field[0]] ?? '');
        }

        return match ($node->type) {
            'soft_break', 'hard_break' => ' ',
            'non_breaking_space' => "\u{00A0}",
            'smart_punctuation' => (string)($node->f['glyph'] ?? ''),
            'symbol' => ':' . (string)($node->f['name'] ?? '') . ':',
            'footnote_ref', 'inline_footnote', 'xref', 'raw_inline', 'comment', 'critic_comment' => '',
            default => implode('', array_map(self::text(...), $node->children ?? [])),
        };
    }

    public static function attribute(Node $node, string $name): ?string
    {
        $attrs = $node->f['attrs'] ?? null;
        if (!$attrs instanceof Attrs) {
            return null;
        }
        if ($name === 'id') {
            return $attrs->id;
        }
        $value = null;
        foreach ($attrs->pairs as [$key, $pairValue]) {
            if ($key === $name) {
                $value = (string)$pairValue;
            }
        }

        return $value;
    }

    public static function hasClass(Node $node, string $class): bool
    {
        $attrs = $node->f['attrs'] ?? null;

        return $attrs instanceof Attrs && in_array($class, $attrs->classes, true);
    }

    public static function hasAttributes(Node $node): bool
    {
        $attrs = $node->f['attrs'] ?? null;

        return $attrs instanceof Attrs && !$attrs->isEmpty();
    }

    /**
     * @param mixed $value
     * @param array<\MarkupCarve\Carve\Node> $kids
     */
    private static function collect(mixed $value, array &$kids): void
    {
        if ($value instanceof Node) {
            $kids[] = $value;

            return;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::collect($item, $kids);
            }
        }
    }
}
