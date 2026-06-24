# shopware-carve

Render [Carve](https://github.com/markup-carve/carve) markup to safe, semantic HTML in Shopware 6.
One source - eight surfaces: Twig filters, CMS elements, product/category fields, admin live
preview, transactional mail, inline product references, and a CLI renderer.

**Safe by default.** Raw HTML passthrough is off. `javascript:`, `data:`, `vbscript:`, `file:` URL
schemes are neutralized. `on*`, `srcdoc`, and `formaction` attributes are stripped. These protections
are always-on baselines independent of any plugin setting. No separate sanitizer needed. The `|carve`
filter is `is_safe => html` because carve-php's URL/attribute hardening is unconditional.

- Composer: `markup-carve/shopware-carve`
- License: MIT
- Shopware: 6.6 and 6.7
- PHP: ^8.2
- Namespace: `Carve\Shopware\`

> **Pre-1.0 caveat.** Both `carve-php` and `carve-js` are design-exploration libraries. Syntax and
> output format can still change before 1.0. Pin versions explicitly and review the carve-php
> changelog before upgrades.

---

## Enabled extensions

The following carve-php extensions are registered unconditionally on every HTML converter (both
`CarveRenderer` and `CarveContextRenderer`). They are pure-PHP and require no extra JavaScript.

| Extension | What it does |
|---|---|
| `AdmonitionExtension` | Converts `::: note`, `::: tip`, `::: warning`, `::: danger`, `::: info`, `::: success` divs to `<div class="admonition {type}">` with a `<p class="admonition-title">` header and an appropriate ARIA role. |
| `DetailsExtension` | Converts `::: details "Title"` to a native `<details><summary>Title</summary>...</details>` disclosure widget. |
| `ListTableExtension` | Converts `::: list-table` blocks (nested lists) to real `<table>` markup with `<thead>`/`<tbody>`/`<th>`/`<td>` and rowspan/colspan support. |
| `InlineFootnotesExtension` | Allows inline footnote syntax `[content]{.fn}` to generate numbered footnote references and an end-of-document footnotes section, sharing the numbering sequence with regular footnotes. |
| `AutolinkExtension` | Detects bare `https://`, `http://`, and `mailto:` URLs in text and turns them into clickable `<a>` links. |
| `ExternalLinksExtension` | Adds `rel="nofollow noopener"` and `target="_blank"` to all external HTTP/HTTPS links, including those produced by `AutolinkExtension`. |
| `TableOfContentsExtension` | Collects headings and makes a `<ul class="toc">` available via `getTocHtml()` (or auto-inserts at `position: 'top'`/`'bottom'` when configured). Not auto-inserted by default - use `position` option or call `getTocHtml()` manually. |

The following extension categories are **not** enabled because they require client-side JavaScript
or additional dependencies, and degrade gracefully without them:

- **Tabs** (`TabsExtension`) - requires carve-js tab component
- **Code groups** (`CodeGroupExtension`) - requires carve-js
- **Mermaid / chart** - requires Mermaid.js / Chart.js loaded on the storefront
- **Spoiler** (`SpoilerExtension`) - requires carve-js reveal component

Smart quotes (`SmartQuotesExtension`) remain config-driven and are added only when
`ShopwareCarve.config.smartQuotes` is enabled in the plugin settings.

---

## Surfaces

### 1 - Twig filters `|carve`, `|carve_text`, `|carve_md`

**Benefit:** Render Carve to safe HTML, plain text, or Markdown from any theme template. The
universal primitive everything else builds on - safe output with no bolt-on sanitizer; plain and
Markdown variants enable channel reuse (mail, export).

```twig
{# HTML output (safe, is_safe => html) #}
{{ product.translated.description | carve }}

{# Plain text (e.g. for meta descriptions) #}
{{ product.translated.description | carve_text }}

{# Markdown (e.g. for export) #}
{{ product.translated.description | carve_md }}
```

For content that contains `:product[SKU]` inline references, use `|carve_ctx(context)` to pass the
sales channel context so product links resolve correctly (see Surface 7).

---

### 2 - Carve CMS element (shopping experiences)

**Benefit:** Drag and drop a safe rich-text block into any CMS page or product layout via the admin.
Non-developers author headings, bold text, tables, and admonitions with zero XSS surface and no
code execution.

Add the element type `carve` from the element panel in the Shopping Experiences editor. The element
renders its `content` field through `CarveRenderer::toHtml()` server-side. The admin config panel
shows a live preview (Surface 5).

---

### 3 - Product custom field `carve_body`

**Benefit:** Structured, diffable, translator-friendly product copy rendered under the product
description on the storefront. Source is plain text (versionable in git); identical in admin
preview and storefront.

After running migrations (see Install), a `carve_body` text area appears on the product detail
admin page. The plugin's storefront override renders it below the core description:

```twig
{# storefront/page/product-detail/description.html.twig - rendered automatically #}
{{ product.customFields.carve_body | carve }}
```

---

### 4 - Category custom field `carve_body`

**Benefit:** Safe rich text for category landing copy - same safety and determinism as product
fields but on category pages.

The plugin adds a `carve_body` field to category entities (via migration) and renders it in the
category CMS listing template automatically.

---

### 5 - Admin live preview (carve-js)

**Benefit:** While typing in the CMS element or custom fields, the preview updates instantly and is
byte-identical to the storefront output. WYSIWYG confidence via PHP/JS parity with no API roundtrip.

The admin CMS element config component (`sw-cms-el-config-carve`) imports `carveToHtml` from
`@markup-carve/carve` and calls it on every `input` event. The shared cross-implementation test
corpus guarantees that carve-js and carve-php produce the same bytes for the same source.

---

### 6 - Transactional mail rendering

**Benefit:** One Carve source feeds both the HTML part and the plain-text part of a multipart mail.
Safe interpolation of user/order data into mail bodies.

See [`docs/mail.md`](docs/mail.md) for the full setup. Short example:

```twig
{# HTML part of a mail template #}
{% set body %}
## Order {{ order.orderNumber }}

Dear **{{ order.orderCustomer.firstName }}**,

your order is on its way.
{% endset %}
{{ body | carve }}

{# Plain-text part of the same mail template #}
{{ body | carve_text }}
```

---

### 7 - Commerce inline type `:product[SKU]`

**Benefit:** Authors embed a live product reference (link with name and price) inline in any Carve
content, resolved against the current sales channel at render time. Markdown has no safe,
first-class way to embed live commerce entities in authored copy.

Use the `|carve_ctx(context)` filter (available in storefront templates as `context`) rather than
the plain `|carve` filter when the content may contain product references:

```twig
{{ product.customFields.carve_body | carve_ctx(context) }}
```

Unknown or out-of-stock SKUs degrade gracefully to inert text - no exceptions thrown.

---

### 8 - Multi-target CLI `carve:render`

**Benefit:** Render a `.crv` file or piped source to HTML, Markdown, plain text, or ANSI from the
console. Write once, show anywhere: storefront, email, terminal, and export from a single source.

```bash
# Render to HTML
bin/console carve:render path/to/content.crv --html

# Render to plain text
bin/console carve:render path/to/content.crv --plain

# Render to Markdown
bin/console carve:render path/to/content.crv --md

# Render with ANSI color (terminal output)
# (--term, not --ansi: the latter is reserved by Symfony's console to force color globally)
bin/console carve:render path/to/content.crv --term
```

---

## Install

### Prerequisites

shopware-carve depends on two libraries that are not yet published to Packagist or npm. Install
them from local clones until they are released.

#### PHP dependency: carve-php

Clone carve-php alongside your Shopware project root (adjust the path to suit your layout):

```bash
git clone https://github.com/markup-carve/carve-php ../carve-php
```

Add a `path` repository to your project's `composer.json` so Composer resolves it locally:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../carve-php",
            "options": { "symlink": false }
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then require the plugin:

```bash
composer require markup-carve/shopware-carve
```

Verify the library loaded correctly:

```php
<?php
var_dump(class_exists('Carve\\CarveConverter')); // bool(true)
```

Once carve-php is published to Packagist you can remove the `path` repository and run
`composer update` normally.

#### JS dependency: carve-js (admin live preview only)

Clone carve-js somewhere on your machine:

```bash
git clone https://github.com/markup-carve/carve-js /path/to/carve-js
```

Install it into the plugin's admin directory as a local file dependency:

```bash
cd custom/plugins/ShopwareCarve/src/Resources/app/administration
npm install /path/to/carve-js
```

This writes a `file:` reference into the plugin's `package.json`. Once `@markup-carve/carve` is
published to npm you can replace the local install with `npm install @markup-carve/carve`.

---

### Plugin installation

```bash
# Activate the plugin
bin/console plugin:install --activate ShopwareCarve

# Run migrations (adds carve_body to products and categories)
bin/console database:migrate --all ShopwareCarve

# Build the admin (required for CMS element and live preview)
bin/console bundle:dump
bin/build-administration.sh
bin/console assets:install

# Compile storefront theme (picks up carve-content styles)
bin/console theme:compile

# Clear cache
bin/console cache:clear
```

---

## Configuration

Access the plugin settings via Admin - Extensions - My extensions - Carve - Configure.

| Key | Default | Description |
|-----|---------|-------------|
| `ShopwareCarve.config.allowRawHtml` | `false` | Allow raw HTML passthrough (see Security note below). |
| `ShopwareCarve.config.livePreview` | `true` | Show instant preview while editing a Carve CMS element in the admin. |
| `ShopwareCarve.config.smartQuotes` | `false` | Smart quotes (typographic): Converts straight quotes to locale-correct typographic quotes. |
| `ShopwareCarve.config.smartQuotesLocale` | `en` | Smart-quote language: Sets the locale for typographic quotes. Only applies when `smartQuotes` is enabled. Supported locales: en, de, de-CH, fr, es, it, pt, nl, pl, ru, uk, cs, hu, sv, da, fi, nb, nn, ja, zh. |

### allowRawHtml

Controls whether authored raw HTML (fenced ` ```=html ` blocks and inline `` `...`{=html} `` spans)
is passed through to the output or escaped. Default: `false` (raw HTML is escaped).

Note that the following protections are **always on** regardless of this setting - they are a
baseline provided by carve-php and are not governed by `allowRawHtml`:

- `javascript:`, `data:`, `vbscript:`, and `file:` URL schemes are neutralized.
- `on*` event attributes, `srcdoc`, and `formaction` are stripped.

**Enable `allowRawHtml` only if every content author is fully trusted.** Enabling it while the
`|carve` filter is registered as `is_safe => html` creates a stored XSS vector - any author can
inject arbitrary HTML (including `<script>` tags) into the storefront.

### livePreview

When `true` (the default), the CMS element config panel renders an instant storefront-identical
preview powered by carve-js. Set to `false` to disable the preview (e.g. for performance or
when carve-js is not installed).

### smartQuotes

When `true`, carve-php's smart-quotes extension is applied to HTML output, converting straight
ASCII quotes (`"..."` and `'...'`) to locale-correct typographic equivalents. Only affects HTML
output (`|carve`, `|carve_ctx`) - plain-text and Markdown targets are not affected.

Default: `false`.

### smartQuotesLocale

Sets the locale used to choose typographic quote characters. Only takes effect when `smartQuotes`
is `true`.

Default: `en` (English curly quotes: `"..."` / `'...'`).

Supported locales:

| Locale | Description |
|--------|-------------|
| `en` | English |
| `de` | German (de) |
| `de-CH` | German (Switzerland) |
| `fr` | French |
| `es` | Spanish |
| `it` | Italian |
| `pt` | Portuguese |
| `nl` | Dutch |
| `pl` | Polish |
| `ru` | Russian |
| `uk` | Ukrainian |
| `cs` | Czech |
| `hu` | Hungarian |
| `sv` | Swedish |
| `da` | Danish |
| `fi` | Finnish |
| `nb` | Norwegian Bokmal |
| `nn` | Norwegian Nynorsk |
| `ja` | Japanese |
| `zh` | Chinese |

Note: future versions may auto-derive the locale from the Shopware sales channel language.

---

## Security note

The `|carve` and `|carve_ctx` filters are marked `is_safe => html` - meaning Twig will not
double-escape their output. This is safe because carve-php provides always-on hardening that
cannot be disabled by any plugin setting:

- `javascript:`, `data:`, `vbscript:`, and `file:` URL schemes are neutralized.
- `on*` event attributes, `srcdoc`, and `formaction` are stripped.
- Parse depth and input size are bounded against DoS.

The only thing the `ShopwareCarve.config.allowRawHtml` setting controls is whether authored raw
HTML (fenced ` ```=html ` blocks and inline `` `...`{=html} `` spans) is passed through or
escaped. By default (`allowRawHtml = false`) raw HTML is escaped and cannot reach the output.

**Enable `allowRawHtml` only if all content authors are fully trusted.** Enabling it while
`is_safe => html` is in the Twig filter registration creates a stored XSS vector - authors can
inject arbitrary HTML including `<script>` tags into the storefront.

---

## Shopware version support

| Shopware | Supported |
|---|---|
| 6.6.x | Yes |
| 6.7.x | Yes |
| < 6.6 | No |

---

## License

MIT - see [LICENSE](LICENSE).
