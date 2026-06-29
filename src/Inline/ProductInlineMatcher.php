<?php declare(strict_types=1);

namespace MarkupCarve\Shopware\Inline;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Renderer\HtmlRenderer;

/**
 * Resolves the Carve inline extension `:product[SKU]` to a storefront product link.
 *
 * carve-php parses `:type[content]` into an InlineExtension node during parsing
 * (before any custom inline matcher runs), so resolution happens at RENDER time
 * via the `render.inline_extension` event - the same hook pattern the bundled
 * AdmonitionExtension uses for `render.div`. A known SKU becomes an <a> to the
 * product page; an unknown SKU degrades to its inert text (no broken link).
 *
 * The SKU lookup is injected as a closure so this class stays free of Shopware
 * coupling and is unit-testable in isolation.
 */
class ProductInlineMatcher
{
    /** @var \Closure(string): (array{name: string, url: string}|null) */
    private \Closure $lookup;

    /** @param callable(string): (array{name: string, url: string}|null) $lookup */
    public function __construct(callable $lookup)
    {
        $this->lookup = \Closure::fromCallable($lookup);
    }

    public function register(CarveConverter $converter): void
    {
        $renderer = $converter->getRenderer();
        if (!$renderer instanceof HtmlRenderer) {
            return;
        }

        $converter->on('render.inline_extension', function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'product') {
                return;
            }

            $sku = trim(html_entity_decode(strip_tags((string) $event->getChildrenHtml())));
            if ($sku === '') {
                return;
            }

            $product = ($this->lookup)($sku);
            if ($product === null) {
                $event->setHtml($this->escape($sku));

                return;
            }

            $event->setHtml(
                '<a href="' . $this->escape($product['url']) . '">' . $this->escape($product['name']) . '</a>'
            );
        });
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
