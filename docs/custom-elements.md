# Custom Carve elements for Shopware

Carve lets you invent your own inline and block elements and resolve them at
render time against live Shopware data - prices, stock, product cards, legal
snippets - while the author only types a short token. This is the same mechanism
the plugin already ships for `:product[SKU]`.

This guide explains the pattern, then shows two complete, copy-pasteable
examples: a pure-markup `:badge[...]` and a data-bound `:price[SKU]`. It ends
with a catalog of commerce-specific element ideas.

> All output you emit must be escaped. The plugin's safety baselines (URL-scheme
> denylist, attribute hardening) protect *authored* markup; HTML you build in a
> render hook is your responsibility - always run dynamic values through
> `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

## How it works

carve-php parses `:name[content]` (inline) and `::: name` (block) into a node
during parsing - it does **not** call custom inline matchers for this syntax.
You customize the *rendering* of that node via a render-event hook, exactly like
the bundled `AdmonitionExtension` (`render.div`) and this plugin's
`ProductInlineMatcher` (`render.inline_extension`):

``` php
$converter->on('render.inline_extension', function (\Carve\Event\RenderEvent $event): void {
    $node = $event->getNode();
    if (!$node instanceof \Carve\Node\Inline\InlineExtension) {
        return;
    }
    if ($node->getExtensionType() !== 'badge') {  // the word after the colon
        return;
    }
    // $event->getChildrenHtml() is the rendered inner content ("Sale")
    $event->setHtml('<span class="...">...</span>');  // setHtml stops default rendering
});
```

- Inline `:name[...]` -> listen on `render.inline_extension`, check
  `getExtensionType()`.
- Block `::: name` -> listen on `render.div` and check the node's class list
  (see `AdmonitionExtension` for the pattern).
- `setHtml()` replaces the default output (`<span class="ext-name">`). If you
  return without calling it, the default generic markup is used - so unknown
  elements always degrade readably.

You register your hook on the converter the plugin builds. Two integration
points exist in this plugin:

- **Context-free** elements (no Shopware lookup): register in
  `CarveRenderer` where the HTML converter is built.
- **Data-bound** elements (need a product, price, sales channel): register in
  `CarveContextRenderer`, which already has the `SalesChannelContext` and is
  reached from templates via the `|carve_ctx(context)` filter.

---

## Example 1 - `:badge[Sale]` (pure markup, no data)

A self-contained inline badge. Lives next to `ProductInlineMatcher`.

`src/Inline/BadgeInlineMatcher.php`:

``` php
<?php declare(strict_types=1);

namespace Carve\Shopware\Inline;

use Carve\CarveConverter;
use Carve\Event\RenderEvent;
use Carve\Node\Inline\InlineExtension;
use Carve\Renderer\HtmlRenderer;

/**
 * `:badge[text]` -> <span class="carve-badge">text</span>.
 * An optional first word picks a variant: `:badge[Sale|danger]`.
 */
class BadgeInlineMatcher
{
    private const VARIANTS = ['default', 'info', 'success', 'warning', 'danger'];

    public function register(CarveConverter $converter): void
    {
        $renderer = $converter->getRenderer();
        if (!$renderer instanceof HtmlRenderer) {
            return;
        }

        $converter->on('render.inline_extension', function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'badge') {
                return;
            }

            $text = trim(html_entity_decode(strip_tags((string) $event->getChildrenHtml())));
            if ($text === '') {
                return;
            }

            $variant = 'default';
            if (str_contains($text, '|')) {
                [$label, $maybeVariant] = array_map('trim', explode('|', $text, 2));
                if (in_array($maybeVariant, self::VARIANTS, true)) {
                    $text = $label;
                    $variant = $maybeVariant;
                }
            }

            $event->setHtml(sprintf(
                '<span class="carve-badge carve-badge--%s">%s</span>',
                $this->escape($variant),
                $this->escape($text)
            ));
        });
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
```

Enable it - in `CarveRenderer`, after building the HTML converter, register it
alongside the other extensions:

``` php
(new \Carve\Shopware\Inline\BadgeInlineMatcher())->register($converter);
```

Add CSS in `src/Resources/app/storefront/src/scss/base.scss`:

``` scss
.carve-badge {
    display: inline-block;
    padding: .15em .5em;
    border-radius: .25rem;
    font-size: .8em;
    font-weight: 600;
    background: #e2e8f0;
    color: #1a202c;

    &--success { background: #c6f6d5; color: #22543d; }
    &--warning { background: #feebc8; color: #7b341e; }
    &--danger  { background: #fed7d7; color: #742a2a; }
    &--info    { background: #bee3f8; color: #2a4365; }
}
```

Author it: `Now :badge[Sale|danger] on selected ropes.` -> a red "Sale" chip.

---

## Example 2 - `:price[SKU]` (live Shopware data)

Renders the current sales-channel price for a product number, so prices in copy
never go stale. Because it needs the `SalesChannelContext`, it is registered in
`CarveContextRenderer` and used through `|carve_ctx(context)`.

`src/Inline/PriceInlineMatcher.php`:

``` php
<?php declare(strict_types=1);

namespace Carve\Shopware\Inline;

use Carve\CarveConverter;
use Carve\Event\RenderEvent;
use Carve\Node\Inline\InlineExtension;
use Carve\Renderer\HtmlRenderer;

/**
 * `:price[SKU]` -> the formatted current price for a product number, or inert
 * text if the SKU is unknown. The actual price/format lookup is injected so the
 * matcher stays free of Shopware coupling and is unit-testable.
 */
class PriceInlineMatcher
{
    /** @var \Closure(string): (string|null) */
    private \Closure $lookup;

    /** @param callable(string): (string|null) $lookup returns a formatted price or null */
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
            if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'price') {
                return;
            }

            $sku = trim(html_entity_decode(strip_tags((string) $event->getChildrenHtml())));
            if ($sku === '') {
                return;
            }

            $formatted = ($this->lookup)($sku);
            $text = $formatted ?? $sku;            // unknown SKU -> inert text
            $cssClass = $formatted !== null ? 'carve-price' : 'carve-price carve-price--unknown';

            $event->setHtml(sprintf(
                '<span class="%s">%s</span>',
                $cssClass,
                htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            ));
        });
    }
}
```

Wire it into `CarveContextRenderer::toHtml()` next to the product matcher,
supplying a lookup that resolves the price for the active sales channel. Sketch:

``` php
// inside CarveContextRenderer::toHtml(), after building $converter:
(new \Carve\Shopware\Inline\PriceInlineMatcher(function (string $sku) use ($context): ?string {
    $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
    $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('productNumber', $sku));
    $criteria->setLimit(1);

    /** @var \Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity|null $product */
    $product = $this->salesChannelProductRepository->search($criteria, $context)->first();
    $price = $product?->getCalculatedPrice();
    if ($price === null) {
        return null;
    }

    // Format with the sales-channel currency. Inject your own formatter for full
    // locale control; this is the minimal shape.
    return number_format($price->getUnitPrice(), 2) . ' ' . $context->getCurrency()->getIsoCode();
}))->register($converter);
```

This needs the `sales_channel.product.repository` (or `product.repository`)
service injected into `CarveContextRenderer` via `services.xml`. Use
`SalesChannelContext` (not the plain `Context`) so the price reflects the
customer group, currency and tax state.

Author it (in a field rendered with `|carve_ctx(context)`):
`The CARVE-A starts at :price[CARVE-A].`

### Making it opt-in

Gate any new element behind a config flag, like `enableMermaid`: add a
`<input-field type="bool">` to `config.xml`, read it in the renderer, and only
`register()` the matcher when it is on. That keeps the default surface small and
lets merchants turn elements on per shop.

### Testing

Test the matcher in isolation - build a real `CarveConverter`, register the
matcher with a fake lookup, convert a snippet, and assert the HTML. See
`tests/Inline/ProductInlineMatcherTest.php` for the exact shape.

---

## Idea catalog (commerce-specific)

Not built yet - candidates that fit the "short token, server-resolved, no client
JS" shape. Each is a render hook plus (for data-bound ones) a Shopware service.

### Live commerce data (sales-channel aware)
- `:price[SKU]` - current formatted price (example above).
- `:stock[SKU]` - availability badge (In stock / X left / Out).
- `:::product-card[SKU]` / `:::product-grid[SKU,...]` - card(s) with image, price, add-to-cart.
- `:was-now[SKU]` / `:discount[SKU]` - list price struck through + new price + percent off.
- `:delivery-time[SKU]`, `:rating[SKU]`, `:product-image[SKU]`, `:variants[SKU]`.

### Conversion / trust (mostly pure markup)
- `:button[Label](url)` / `::: cta` - themed call-to-action button.
- `:badge[Sale|danger]` - label chip (example above).
- `:coupon[CODE]` - copyable voucher chip.
- `::: usp`, `::: trust-badges` - feature/payment icon rows.
- `:countdown[ISO-DATE]` - sale countdown (live tick needs a little JS).

### Legal / compliance (DE/EU, config-resolved)
- `:vat-note` / `:price-note` - the required "incl. VAT, plus shipping" suffix from the shop config.
- `:legal[withdrawal|tos|privacy|imprint]` - link to the configured legal page.
- `:shipping-from[50]` - "free shipping from EUR 50" in the shop currency.

### Spec / technical (B2B)
- `::: csv` -> real `<table>` from spreadsheet-pasted rows (load/size tables).
- `::: specs[SKU]` - auto spec sheet from product properties.
- `:unit[6 mm]`, `:sku[SKU]`, `::: size-chart`.

### Generic-but-useful (low/no JS)
- `:icon[truck]` (theme icon set), `:color[#ff8800]` + `::: palette`,
  `:rating-stars[4.5]`, `:spark[1,4,2,8]`, `::: qr[url]`, `barcode-ean13`.

Build order that maximizes value per effort: `:price` + `:stock`, then
`:vat-note` / `:legal`, then `:button` / `:badge`, then `:::product-card`, then
`::: csv`.
