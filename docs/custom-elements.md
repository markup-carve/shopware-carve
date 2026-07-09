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

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Renderer\HtmlRenderer;

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

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Inline\InlineExtension;
use MarkupCarve\Carve\Renderer\HtmlRenderer;

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

## Idea catalog for Shopware devs and apps

Not built yet - candidates that fit the "short token, server-resolved, no client
JS" shape. Each is a render hook plus (for data-bound ones) a Shopware service.
A live roadmap with checkboxes is tracked in the repo issues. ⭐ marks the
highest value-per-effort.

> Pick the right Carve form (verified against carve-php):
> - **Inline** `:name[arg]` - the bracket is REQUIRED, even `:name[]` for a
>   no-argument element; hyphens in the name are fine. A bare `:name` (no bracket)
>   does not parse. A trailing `(url)` is NOT captured, so "button" is a link with
>   a class (`[Label](url){.button}`) or a block, not `:button[..](url)`.
> - **Block** `::: name` + body (optional `"title"` and `{attrs}`; NO `[...]`).
> - **Fence** ` ```name ` + body - what FencedRender presets (mermaid, chart,
>   barcode, csv) claim. This is why `barcode-ean13` has no colon.

### Live product and catalog data (inline, server-resolved, sales-channel aware)
- ⭐ `:product[SKU]` / card variant `:product[SKU]{.card}` - link or full card. *Why:* cross-sells in editorial copy stay live and unbroken instead of rotting as hardcoded text.
- ⭐ `:price[SKU]` - current formatted price. *Why:* kills stale/wrong hardcoded prices; correct per currency, customer group, tax state.
- ⭐ `:stock[SKU]` - availability badge. *Why:* urgency ("only 3 left") and honesty (sold out) at the point of mention, auto-updated.
- `:was-now[SKU]` / `:discount[SKU]` - strike-through list price + percent off. *Why:* campaign copy shows the real reference price (DE strike-price rules).
- `::: product-grid {skus="A,B,C"}` - hand-picked product set (block; SKUs in body or attr). *Why:* curate an exact set inside a content page without the CMS listing element's filter constraints.
- `:rating[SKU]`, `:variants[SKU]`, `:category[handle]`, `:manufacturer[name]` - social proof, option chips, stable internal links that survive SEO-URL changes.

### Conversion / CTA / layout (mostly pure markup)
- ⭐ CTA button: a link with a class `[Label](url){.button}`, or a block `::: cta`. *Why:* authors add real CTAs from plain text, theme-styled, no HTML/CSS, no XSS. (Note: `:button[Label](url)` is invalid - the URL is dropped.)
- ⭐ `:badge[Sale|danger]` - label chip. *Why:* New/Sale/B2B emphasis without inline styles; one place to restyle.
- `::: columns` / `::: card` - layout primitives. *Why:* multi-column/card content in fields that are not full CMS pages.
- `::: accordion` / `::: faq` - native `<details>` collapsibles. *Why:* FAQ/spec sections, SEO-friendly, no JS.
- `:coupon[CODE]` - copyable code chip. *Why:* one-click copy, fewer typos, more redemptions.
- `:add-to-cart[SKU]` - inline buy button. *Why:* turn editorial copy into a buy surface without a CMS block.
- `:countdown[2026-07-01]` - sale timer (tiny JS for the live tick). *Why:* urgency.

### Legal / compliance (DE/EU, config-resolved)
- ⭐ `:vat-note[]` / `:price-note[]` - "incl. VAT, plus shipping" from config (empty bracket - no argument). *Why:* the legally required price suffix, one source of truth, no per-page mistakes.
- ⭐ `:legal[withdrawal]` (or `tos` / `privacy` / `imprint`) - link to the configured legal page. *Why:* mandatory links that are never broken, centrally managed.
- `:shipping-from[50]` - free-shipping threshold in shop currency. *Why:* config-driven, consistent.

### Spec / technical / B2B
- ⭐ ` ```csv ` fence -> real `<table>`. *Why:* spreadsheet-pasted load/size/compatibility tables with block content per cell - the thing pipe-tables cannot do.
- `::: specs {sku="ABC"}` - auto spec sheet from product properties (block + attr). *Why:* zero-maintenance table generated from PIM data.
- `:datasheet[SKU]` - link to the product's datasheet media. *Why:* one-token B2B doc access.
- `:unit[6 mm]` / `:dimension[1x2]` - consistent technical notation.

### Trust / social proof / media
- `::: trust-badges`, `::: usp` (blocks), `:reviews-summary[SKU]` (inline) - payment/shipping icons, benefit lists, aggregate rating. *Why:* themed, consistent conversion elements.
- `:product-image[SKU]` (inline) / `::: gallery` (block) - live image / media-library gallery. *Why:* always the current asset.
- `:video[youtube:ID]` - GDPR-safe click-to-load embed. *Why:* no auto third-party load, consent-friendly by construction.

### Developer / app utility (the platform angle)
- ⭐ `:snippet[key]` - render a translation snippet inline. *Why:* i18n inside authored content - one source, all languages via the snippet system; big for multilingual shops and app-shipped copy.
- ⭐ `::: app-block {name="..."}` - extension point for apps/plugins to register their own server-resolved widgets via the carve-php extension API. *Why:* an app ships its own markup vocabulary usable in any Carve field - a platform play, not just static content.
- `:route[frontend.detail.page]` / `:seo-url[handle]` - generate a storefront URL. *Why:* stable links that respect SEO URLs + sales-channel domain, not hardcoded paths.
- `:asset[path]` - theme/bundle asset URL. *Why:* reference plugin/app assets without deploy-fragile paths.
- `:config[core.shopName]` - output a whitelisted SystemConfig value. *Why:* DRY shop metadata in content. Security: strict allowlist only, never arbitrary keys.
- `:icon[truck]` - theme icon set. *Why:* consistent iconography, no inline SVG.

### Generic-but-commerce-flavored (low/no JS)
- `:color[#ff8800]` + `::: palette`, `:rating-stars[4.5]`, `:spark[1,4,2,8]` (build-time SVG), ` ```qr ` fence (or inline `:qr[url]`), ` ```barcode-ean13 ` fence (digits in the body), `:progress[70]`.

### Two cross-cutting reasons this matters for devs/apps
- **One safe authoring vocabulary across every surface** - the same elements work in product/category fields, CMS, reviews, mail and PDFs, so an app defines a widget once and it is usable everywhere a Carve field exists.
- **Security stays free** - everything emits through the same hardened pipeline (escaped values, scheme/attribute denylist), so an app author cannot introduce XSS via a custom widget as long as dynamic values are escaped (see the examples above).

Build order that maximizes value per effort: `:price` + `:stock`, then
`:vat-note[]` / `:legal`, then `:badge` + the `.button` link, then `:snippet`,
then `:product[SKU]{.card}`, then the ` ```csv ` fence.
