# Authoring and commerce elements

The CMS editor and the plugin's product, category, and manufacturer fields use
one Carve editor. Run plugin migrations and rebuild the administration after
upgrading to install the field component. Existing translations and inherited
values remain Shopware fields; the migration changes their editing component.
Custom field components installed by another plugin are left unchanged.
Deactivation and uninstall restore the standard textarea for fields using the
Carve editor; reactivation restores the Carve editor. Content stays stored.

The editor provides syntax help, product and media selection, conversion from
HTML or Markdown, and a server preview. Conversion returns diagnostics and a
candidate source. **Replace with converted source** applies it to the current
form; save the Shopware entity to persist it.

Preview requests are debounced and stale responses are ignored. Failures appear
with a retry action. HTML appears in a sandboxed iframe, so enabling raw HTML for
trusted storefront authors does not execute that HTML in the administration.
The iframe uses basic preview styles. It does not run storefront scripts or
match every theme's CSS.

Choose a preview sales channel to resolve commerce elements and guest prices.
Preview selection requires sales-channel and product read permissions. The active content
language is passed to the channel context. Customer-specific pricing should be
checked in the storefront with that customer's session.

## CMS field mapping

A Carve CMS element can use static source or a mapped string field, such as
`product.translated.customFields.carve_body`. The resolver renders the mapped
value rather than treating its property path as source. Mapped content keeps
file includes literal. Use a static slot for [shared file content](includes.md).

## Commerce elements

Use `carve_ctx(context)` in custom Twig templates. Product and category overrides
and CMS elements already use this path. Manufacturer fields remain available
through theme integration.

| Source | Output |
|---|---|
| `:product[SKU]` | Translated product name linked through Shopware's SEO URL handler. |
| `:price[SKU]` | Formatted unit price for the first advanced-price tier, or the calculated base price. |
| `:stock[SKU]` | Translated availability label. |
| `:datasheet[SKU]` | First public PDF attached to the product. |
| `:category[UUID]` | Active page category within the channel's navigation, footer, or service tree. |
| `:manufacturer[UUID]` | Translated brand name linked to storefront search. Core has no manufacturer detail route. |
| `:legal[privacy]` | Link to the layout configured as the channel's privacy page under basic information, opened in a modal like core's own legal links. Also accepts `tos`, `imprint`, and `withdrawal`. |
| `:snippet[key]` | Escaped translation snippet text. |
| `:media[UUID]{alt="Description"}` | Public image with thumbnails in `srcset`, lazy loading, and alternative text. |

Product lookup uses the sales-channel repository and link visibility. Inactive,
unassigned, and missing products stay as literal SKU text. Unavailable products
also keep product links, prices, and cards inert; their stock label can still
report unavailability. Repeated SKUs, including misses, are memoized in the
request's channel context. One document batches up to 100 new SKUs.

Price output follows the active currency and tax state. The inline price targets
the first advanced tier; cart quantities and variant selection remain Shopware
cart behavior. Use the core product card when those controls are needed.

Cards, curated grids, and specification lists are block elements:

```carve
{sku="SKU"}
::: product-card
:::

::: product-grid
SKU-A, SKU-B, SKU-C
:::

{sku="SKU"}
::: specs
:::
```

Cards and grids reuse the core Storefront product-card template, including its
product image, pricing, and purchase controls. A grid accepts at most 24 SKUs and
omits unavailable products. Core-only installations and previews without the
Storefront runtime fall back to product links. Specification lists use translated product property
groups and values. Keep SKUs stable when shared documents reference them.

CMS fragments namespace IDs by slot to keep repeated tabs and anchors separate.
Twig filters preserve existing heading IDs by default. Pass an optional namespace
to `carve` or `carve_ctx` when rendering repeated fragments in a custom template.

## Placement and links

`productPlacement` accepts `append` (default), `before`, or `replace`. Replacement
happens only when Carve copy exists. `categoryPlacement` accepts `before`
(default) or `after` the CMS layout. Map a field into a Carve CMS element when
placement needs to follow the page layout instead.

The ordinary and context-aware renderers share symbols, content profiles,
external-link settings, typography, and diagram options. These settings respect
the current sales channel. Set `smartQuotesLocale` to `auto` to derive quote
characters from its content language. Reviews keep their forced comment profile
and exclude configured raw-HTML symbol values.

`externalLinksNofollow` and `externalLinksNewTab` default to `true` for compatibility.
Disable either per channel when editorial links should behave differently.

## Headless storefronts

The resolved CMS slot returns `data.html`, `data.carveSource`, and include
dependency metadata. Render the server's HTML; passing the source through a
second browser parser would omit includes and context-dependent elements.

Copy [CmsElementCarve.vue](../examples/shopware-frontends/CmsElementCarve.vue) and
[CmsBlockCarve.vue](../examples/shopware-frontends/CmsBlockCarve.vue) into your
Shopware Frontends application's globally registered CMS component directory.
Include the plugin's content styles in your theme. The component follows a
replaced `content` prop through a computed value, including language changes.

The examples display HTML. Core Storefront cards contain Storefront purchase
controls and require its runtime. Headless applications should replace those
card templates or use their native cart components. Diagram hydration is also
an application responsibility. Keep raw HTML off unless authors are trusted.
See [Shopware's CMS component resolution](https://developer.shopware.com/frontends/frontends-recipes/cms/rendering.html).

## Diagrams and accessibility

Mermaid and Chart.js URLs use exact versions. PlantUML uses `krokiUrl`, which can
point to a self-hosted service; allow its origin in the storefront CSP. Diagram
source is sent to that endpoint. Charts provide a collapsible data representation as
a text alternative. Reinitialization avoids duplicate chart canvases.
Images scale within content containers and tables scroll horizontally on narrow
screens. Review containers accept paragraphs and lists without nesting them
inside a paragraph.
