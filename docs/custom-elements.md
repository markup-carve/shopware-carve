# Custom Carve elements for Shopware

The plugin ships prices, stock labels, product cards and grids, specs, datasheets,
media, snippets, and internal links. Use the [built-in elements](authoring.md)
first. Add a render hook when a shop needs a different element or behavior.

## Render hooks

Carve parses `:name[content]` as an `InlineExtension`. A `::: name` container is
a `Div` with that class. Hooks replace the HTML for these nodes; they do not
need a custom parser. Unknown elements keep their generic rendered output.

Badges are built in: `:badge[Sale|danger]` renders a styled label. For another
inline element, register a render hook and validate each CSS variant against an
allowlist. This example adds a `notice` element:

```php
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Node\Inline\InlineExtension;

function registerNotice(CarveConverter $converter): void
{
    $converter->on('render.inline_extension', static function (RenderEvent $event): void {
        $node = $event->getNode();
        if (!$node instanceof InlineExtension || $node->getExtensionType() !== 'notice') {
            return;
        }
        $variant = $node->getAttribute('variant') ?? 'info';
        if (!in_array($variant, ['info', 'success', 'warning', 'danger'], true)) {
            $variant = 'info';
        }
        $event->setHtml('<span class="shop-notice shop-notice--' . $variant . '">'
            . $event->getChildrenHtml() . '</span>');
    });
}
```

Authors can then write `:notice[Limited stock]{variant="danger"}`. Add the notice
classes to the theme's stylesheet.

For a block, listen on `render.div` and check `Div::hasClass()`. Block attributes
precede the opener:

```carve
{sku="SKU"}
::: specs
:::
```

## Integration points

When extending this plugin's implementation:

- Register context-free hooks in `CarveConverterFactory::create()`. Keep custom
  hooks out of the UGC path unless they have been reviewed for that use.
- Put commerce lookups in a service with `register($converter, $context)` and,
  when batching is needed, `prepare($document, $context)`. Register it alongside
  `CarveCommerce` and `CarveResources` in `CarveContextRenderer::render()`.
- Call preparation after includes have expanded so referenced SKUs in shared
  files join the same batch. Wire service dependencies in `services.yaml`.
- Keep hooks scoped to one sales-channel context. Avoid closures that retain a
  customer's context in a shared converter cache.

Use the sales-channel product repository, availability filters, SEO URL handler,
currency formatter, and cache tags for data-bound elements. The existing
`CarveCommerce` implementation demonstrates these together. A raw product
repository lookup does not apply the channel's catalog and pricing rules.

HTML built by a hook must escape dynamic text and attributes. Validate URL
schemes and CSS class choices separately; HTML escaping does not make an unsafe
URL safe. Raw hook output does not pass through the authored-markup sanitizer.

## Possible extensions

Further shop-specific elements could include ratings, variant choices, galleries,
translation-backed price notes, coupon controls, or consent-aware video embeds.
Use existing core templates or native storefront components where they already
handle cart state, customer permissions, or consent.

Test hooks with a real converter and representative channel contexts. Cover
missing entities, escaping, visibility, language, cache invalidation, and included
source. Browser-test interactive output in the target storefront.
