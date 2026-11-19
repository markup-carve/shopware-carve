# Custom Carve elements for Shopware

The plugin ships prices, stock labels, product cards and grids, specs, datasheets,
media, snippets, and internal links. Use the [built-in elements](authoring.md)
first. Add a render hook when a shop needs a different element or behavior.

## Render hooks

The plugin renders through `CarveSetup`, which holds the carve-php 0.2 options and the
plugin's render hooks. A hook claims `:name[content]` (form `inline`), a `::: name`
container (form `div`) or a fence language (form `fence`) and returns its HTML. Hooks
do not need a custom parser: each one becomes a carve-php 0.2 extension matcher with a
renderer. Hooks on the same name run in registration order and the first non-null HTML
wins; when every hook returns `null`, the element keeps its core rendering
(`<span class="ext-name">` for an unclaimed inline element).

Badges are built in: `:badge[Sale|danger]` renders a styled label. For another
inline element, register a hook and validate each CSS variant against an
allowlist. This example adds a `notice` element:

```php
use MarkupCarve\Shopware\Service\CarveHookCall;
use MarkupCarve\Shopware\Service\CarveSetup;

function registerNotice(CarveSetup $setup): void
{
    $setup->on('inline', 'notice', static function (CarveHookCall $call): string {
        $variant = $call->attribute('variant') ?? 'info';
        if (!in_array($variant, ['info', 'success', 'warning', 'danger'], true)) {
            $variant = 'info';
        }

        return '<span class="shop-notice shop-notice--' . $variant . '">' . $call->childrenHtml() . '</span>';
    });
}
```

Authors can then write `:notice[Limited stock]{variant=danger}`. Add the notice
classes to the theme's stylesheet.

`CarveHookCall` gives a hook the claimed element: `argument()` (its plain text, or
the fence body), `childrenHtml()`, `title()` and `titleHtml()` for a quoted div
title, `attribute()`, `hasClass()` and `fallbackHtml()`. For a block, register a
`div` hook. Block attributes precede the opener:

```carve
{sku=SKU}
::: specs

:::
```

A change that needs the whole document, such as replacing a paragraph, is a carve-php
document transform: `CarveSetup::addTransform()` takes a
`MarkupCarve\Carve\Extension\DocumentTransform` that runs on a copy of the resolved
tree before HTML rendering. `CarveCommerce` uses one to turn a paragraph that holds
only `:product[SKU]{.card}` into the storefront card. A transform that adds an
extension node must register it with `CarveSetup::addExtension()` and declare it with
`CarveTree::declare()`.

## Integration points

When extending this plugin's implementation:

- Register context-free hooks in `CarveConverterFactory::create()`. Keep custom
  hooks out of the UGC path unless they have been reviewed for that use.
- Put commerce lookups in a service with `register($setup, $context)` and,
  when batching is needed, `prepare($document, $context)`. Register it alongside
  `CarveCommerce` and `CarveResources` in `CarveContextRenderer::render()`.
- Call preparation after includes have expanded so referenced SKUs in shared
  files join the same batch. `prepare()` receives the carve-php 0.2 tree; use
  `CarveTree::walk()` and `CarveTree::core()` to read the elements a hook claimed.
  Wire service dependencies in `services.yaml`.
- Keep hooks scoped to one sales-channel context. Avoid closures that retain a
  customer's context in a shared setup cache.

Use the sales-channel product repository, availability filters, SEO URL handler,
currency formatter, and cache tags for data-bound elements. The existing
`CarveCommerce` implementation demonstrates these together. A raw product
repository lookup does not apply the channel's catalog and pricing rules.

HTML built by a hook must escape dynamic text and attributes. Validate URL
schemes and CSS class choices separately; HTML escaping does not make an unsafe
URL safe. Raw hook output does not pass through the authored-markup sanitizer.

The carve-php 0.2 extension host is provisional and may change in a 0.2.x
release; `CarveSetup` keeps that change out of the hooks.

## Possible extensions

Further shop-specific elements could include ratings, variant choices, galleries,
translation-backed price notes, coupon controls, or consent-aware video embeds.
Use existing core templates or native storefront components where they already
handle cart state, customer permissions, or consent.

Test hooks with a real `CarveSetup` and representative channel contexts. Cover
missing entities, escaping, visibility, language, cache invalidation, and included
source. Browser-test interactive output in the target storefront.
