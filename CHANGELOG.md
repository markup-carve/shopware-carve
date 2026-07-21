# Changelog

All notable changes to `markup-carve/shopware-carve` are documented here.
This project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Opt-in `enablePlantuml` setting: render ` ```plantuml ` / ` ```puml ` fenced blocks as diagrams.
  carve-php emits `<pre class="plantuml">`; since PlantUML has no in-browser renderer, the
  storefront JS POSTs each block to the external Kroki service (`https://kroki.io/plantuml/svg`)
  and inlines the returned SVG as an `<img>` data URI - only when a diagram is present. Off by
  default. Requires `https://kroki.io` in the CSP `connect-src` and `data:` in `img-src`.

## [0.1.0] - 2026-07-14

### Added
- Initial public release: render the Carve markup language to safe HTML in Shopware 6.6 and 6.7.
- Eight surfaces: `|carve` / `|carve_text` / `|carve_md` Twig filters; Carve CMS element and block;
  product custom field `carve_body`; category custom field `carve_category_body`; admin live preview
  (carve-js, byte-identical to storefront); transactional mail (`|carve` + `|carve_text`); inline
  `:product[SKU]` references via `|carve_ctx(context)`; and the `carve:render` CLI (HTML / Markdown /
  plain / ANSI).
- Always-on extensions: admonitions, details, list tables, footnotes, autolink, external links
  (`rel="nofollow noopener"` + `target="_blank"`), table of contents, and spoilers (native
  `<details>` block + CSS-blur inline, no JavaScript).
- Plugin settings (Admin -> Extensions -> Carve -> Configure): `allowRawHtml` (default off),
  `livePreview`, `smartQuotes` + `smartQuotesLocale` (20 locales), and opt-in `enableMermaid` /
  `enableCharts` (lazy-load Mermaid.js / Chart.js from a CDN only when a diagram is on the page).
- Storefront `.carve-content` stylesheet covering all rendered constructs.

### Security
- URL-scheme denylist (`javascript:`, `vbscript:`, `data:`, `file:`) and attribute hardening
  (`on*`, `srcdoc`, `formaction`, script-bearing CSS) are always-on baselines from carve-php,
  independent of any setting. `allowRawHtml` governs only raw HTML passthrough and is off by default.
