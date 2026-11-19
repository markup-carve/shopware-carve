<?php

declare(strict_types=1);

namespace MarkupCarve\Shopware\Service;

use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Extension\DocumentTransform;
use MarkupCarve\Carve\Extension\Tabs;
use MarkupCarve\Carve\Node;

/**
 * The 0.1 bundled extensions this plugin enabled, rebuilt on carve-php 0.2 means until the engine's
 * extension catalog ships them: spoiler, details, code group, list table, diagram fences, external
 * link attributes and locale quotes. Tabs use the engine's standard `carve.tabs` family.
 */
class CarveHostExtensions
{
    /**
     * @var array<string, array{string, string, string, string}>
     */
    private const QUOTES = [
        'de' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"],
        'de-CH' => ["\u{00AB}", "\u{00BB}", "\u{2039}", "\u{203A}"],
        'fr' => ["\u{00AB}\u{00A0}", "\u{00A0}\u{00BB}", "\u{2039}\u{00A0}", "\u{00A0}\u{203A}"],
        'pl' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"],
        'ru' => ["\u{00AB}", "\u{00BB}", "\u{201E}", "\u{201C}"],
        'ja' => ["\u{300C}", "\u{300D}", "\u{300E}", "\u{300F}"],
        'zh' => ["\u{300C}", "\u{300D}", "\u{300E}", "\u{300F}"],
        'sv' => ["\u{201D}", "\u{201D}", "\u{2019}", "\u{2019}"],
        'da' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"],
        'fi' => ["\u{201D}", "\u{201D}", "\u{2019}", "\u{2019}"],
        'cs' => ["\u{201E}", "\u{201C}", "\u{201A}", "\u{2018}"],
        'hu' => ["\u{201E}", "\u{201D}", "\u{201A}", "\u{2019}"],
        'it' => ["\u{00AB}", "\u{00BB}", "\u{201C}", "\u{201D}"],
        'es' => ["\u{00AB}", "\u{00BB}", "\u{201C}", "\u{201D}"],
        'pt' => ["\u{00AB}", "\u{00BB}", "\u{201C}", "\u{201D}"],
        'nb' => ["\u{00AB}", "\u{00BB}", "\u{2018}", "\u{2019}"],
        'nn' => ["\u{00AB}", "\u{00BB}", "\u{2018}", "\u{2019}"],
        'uk' => ["\u{00AB}", "\u{00BB}", "\u{201E}", "\u{201C}"],
    ];

    public function register(CarveSetup $setup): void
    {
        $setup->addExtension(...Tabs::extensions(true));
        $setup->on('div', 'spoiler', fn (CarveHookCall $call): string => $this->disclosure('spoiler', $call, 'Spoiler'));
        $setup->on('div', 'details', fn (CarveHookCall $call): string => $this->disclosure('details', $call, 'Details'));
        $setup->on('inline', 'spoiler', static fn (CarveHookCall $call): string => '<span class="spoiler">' . $call->childrenHtml() . '</span>');
        $setup->on('div', 'code-group', fn (CarveHookCall $call): ?string => $this->codeGroup($call));
        $setup->on('div', 'list-table', fn (CarveHookCall $call): ?string => $this->listTable($call));
    }

    /**
     * Text-mode diagram fences (`mermaid`, `plantuml`, `puml`) and the JSON-mode `chart` fence, as 0.1
     * FencedRenderExtension wrote them for client-side hydration.
     */
    public function fenced(CarveSetup $setup, string $kind): void
    {
        $languages = $kind === 'plantuml' ? ['plantuml', 'puml'] : [$kind];
        foreach ($languages as $language) {
            $setup->on('fence', $language, static function (CarveHookCall $call) use ($kind): string {
                $body = $call->source['body'] ?? '';
                if ($kind === 'chart') {
                    return '<div class="chart" role="img" aria-label="chart"><script type="application/json">'
                        . str_replace('</', '<\/', $body) . '</script></div>';
                }

                return '<pre class="' . $kind . '" role="img" aria-label="' . $kind . '">'
                    . str_replace(['&', '<'], ['&amp;', '&lt;'], $body) . '</pre>';
            });
        }
    }

    /**
     * Adds `rel` and `target` to every absolute http(s) link, as 0.1 ExternalLinksExtension did.
     */
    public function externalLinks(CarveSetup $setup, string $rel, string $target): void
    {
        $setup->addTransform(new DocumentTransform('shopware.external-links', static function (Node $document) use ($rel, $target): Node {
            CarveTree::walk($document, static function (Node $node) use ($rel, $target): void {
                $href = $node->f['href'] ?? null;
                if ($node->type !== 'link' || !is_string($href) || preg_match('#^https?://[^/?\#\s]#i', $href) !== 1) {
                    return;
                }
                $attrs = $node->f['attrs'] ?? null;
                if (!$attrs instanceof Attrs) {
                    $attrs = new Attrs();
                    $node->f['attrs'] = $attrs;
                }
                $keys = array_column($attrs->pairs, 0);
                if ($target !== '' && !in_array('target', $keys, true)) {
                    $attrs->pairs[] = ['target', $target];
                }
                if ($rel !== '' && !in_array('rel', $keys, true)) {
                    $attrs->pairs[] = ['rel', $rel];
                }
            });

            return $document;
        }));
    }

    /**
     * Rewrites the engine's English typographic quotes to the quote style of a locale. Text inside
     * `code`, `pre`, `kbd`, `samp`, `script` and `style` keeps its characters.
     */
    public function smartQuotes(CarveSetup $setup, string $locale): void
    {
        $quotes = self::QUOTES[$locale] ?? self::QUOTES[explode('-', $locale)[0]] ?? null;
        if ($quotes === null) {
            return;
        }
        $setup->addPostProcessor(static fn (string $html): string => self::requote($html, $quotes));
    }

    /**
     * @param string $html
     * @param array{string, string, string, string} $quotes
     */
    public static function requote(string $html, array $quotes): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $verbatim = 0;
        $singleOpen = 0;
        foreach ($parts as $index => $part) {
            if ($part !== '' && $part[0] === '<') {
                if (preg_match('#^<(/?)(code|pre|kbd|samp|script|style)\b#i', $part, $tag) === 1) {
                    $verbatim = max(0, $verbatim + ($tag[1] === '/' ? -1 : 1));
                }

                continue;
            }
            if ($verbatim > 0 || $part === '') {
                continue;
            }
            $out = '';
            foreach (mb_str_split($part) as $char) {
                if ($char === "\u{201C}") {
                    $out .= $quotes[0];
                } elseif ($char === "\u{201D}") {
                    $out .= $quotes[1];
                } elseif ($char === "\u{2018}") {
                    $singleOpen++;
                    $out .= $quotes[2];
                } elseif ($char === "\u{2019}" && $singleOpen > 0) {
                    // A closing single quote only where one was opened; otherwise it is an apostrophe.
                    $singleOpen--;
                    $out .= $quotes[3];
                } else {
                    $out .= $char;
                }
            }
            $parts[$index] = $out;
        }

        return implode('', $parts);
    }

    private function disclosure(string $class, CarveHookCall $call, string $default): string
    {
        $title = $call->titleHtml();

        return '<details class="' . $class . '"><summary>' . ($title !== '' ? $title : $default)
            . '</summary>' . $call->childrenHtml() . '</details>';
    }

    private function codeGroup(CarveHookCall $call): ?string
    {
        $items = [];
        foreach ($call->node->children ?? [] as $child) {
            if ($child->type !== 'code_block') {
                return null;
            }
            $label = (string)($child->f['groupLabel'] ?? $child->f['syntax'] ?? 'Code');
            $items[] = [$label, $child];
        }
        if ($items === []) {
            return null;
        }
        $group = $call->nextId('code-group');
        $controls = '';
        $panels = '';
        foreach ($items as $index => [$label, $block]) {
            $id = $group . '-tab-' . ($index + 1);
            $escaped = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $controls .= '<input type="radio" name="' . $group . '" id="' . $id . '" class="code-group-radio"'
                . ($index === 0 ? ' checked' : '') . ">\n"
                . '<label for="' . $id . '" class="code-group-label">' . $escaped . "</label>\n";
            $panels .= '<div class="code-group-panel" role="group" aria-label="' . $escaped . '">'
                . $call->blocksHtml([$block]) . "</div>\n";
        }

        return "<div class=\"code-group\">\n" . $controls . $panels . '</div>';
    }

    private function listTable(CarveHookCall $call): ?string
    {
        $list = $call->node->children[0] ?? null;
        if (!$list instanceof Node || $list->type !== 'list' || count($call->node->children ?? []) !== 1) {
            return null;
        }
        $rows = [];
        foreach ($list->children ?? [] as $item) {
            $cells = $item->children[0] ?? null;
            if ($cells === null || $cells->type !== 'list' || count($item->children ?? []) !== 1) {
                return null;
            }
            $rows[] = $cells->children ?? [];
        }
        $headerRows = max(0, (int)($call->attribute('header-rows') ?? 0));
        $html = '<table>';
        foreach ($rows as $index => $cells) {
            if ($index === 0 && $headerRows > 0) {
                $html .= '<thead>';
            }
            if ($index === $headerRows) {
                $html .= '<tbody>';
            }
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $tag = $index < $headerRows ? 'th scope="col"' : 'td';
                $html .= '<' . $tag . '>' . $this->cellHtml($call, $cell) . '</' . explode(' ', $tag)[0] . '>';
            }
            $html .= '</tr>';
            if ($index === $headerRows - 1) {
                $html .= '</thead>';
            }
        }
        if (count($rows) > $headerRows) {
            $html .= '</tbody>';
        }

        return $html . '</table>';
    }

    private function cellHtml(CarveHookCall $call, Node $cell): string
    {
        $children = $cell->children ?? [];
        if (count($children) === 1 && $children[0]->type === 'paragraph') {
            return $call->inlinesHtml($children[0]->children ?? []);
        }

        return $call->blocksHtml($children);
    }
}
