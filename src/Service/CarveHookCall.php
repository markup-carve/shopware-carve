<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Node;

/**
 * What a Shopware render hook receives for one claimed `:name[...]`, `::: name` or fence node.
 */
final class CarveHookCall
{
    /**
     * @param string $form `inline`, `div` or `fence`.
     * @param string $name The source name.
     * @param \MarkupCarve\Carve\Node $node The core node the hook claimed.
     * @param array<string, string> $source The source record (body, title).
     * @param \MarkupCarve\Carve\Extension\RenderHost $host
     */
    public function __construct(
        public readonly string $form,
        public readonly string $name,
        public readonly Node $node,
        public readonly array $source,
        private readonly RenderHost $host,
    ) {
    }

    /**
     * The trimmed plain text of the content, the 0.1 "argument" of an extension.
     */
    public function argument(): string
    {
        if ($this->form === 'fence') {
            return trim($this->source['body'] ?? '');
        }

        return trim(CarveTree::text($this->node));
    }

    public function childrenHtml(): string
    {
        $children = $this->node->children ?? [];

        return $this->form === 'inline' ? ($this->host->inlines)($children) : ($this->host->blocks)($children);
    }

    public function title(): string
    {
        return trim($this->source['title'] ?? '');
    }

    public function titleHtml(): string
    {
        $title = $this->node->f['title'] ?? null;

        return is_array($title) ? trim(($this->host->inlines)($title)) : RenderHost::escapeText($this->title());
    }

    /**
     * @param array<\MarkupCarve\Carve\Node> $nodes
     */
    public function inlinesHtml(array $nodes): string
    {
        return ($this->host->inlines)($nodes);
    }

    /**
     * @param array<\MarkupCarve\Carve\Node> $nodes
     */
    public function blocksHtml(array $nodes): string
    {
        return ($this->host->blocks)($nodes);
    }

    public function attribute(string $name): ?string
    {
        return CarveTree::attribute($this->node, $name);
    }

    public function hasClass(string $class): bool
    {
        return CarveTree::hasClass($this->node, $class);
    }

    public function hasAttributes(): bool
    {
        return CarveTree::hasAttributes($this->node);
    }

    /**
     * The core rendering of the claimed node, as if no hook had claimed it.
     */
    public function fallbackHtml(): string
    {
        return $this->form === 'inline' ? ($this->host->inlines)([$this->node]) : ($this->host->blocks)([$this->node]);
    }

    public function nextId(string $prefix): string
    {
        return ($this->host->nextId)($prefix);
    }
}
