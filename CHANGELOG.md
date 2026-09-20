# Changelog

All notable changes to `markup-carve/shopware-carve` are documented here.
This project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.4] - 2026-09-20

### Added

- Gated file includes. An administrator sets an absolute `includeRoot`, empty by
  default, which keeps every include directive literal everywhere. With a root
  configured, `carve:render` and the Carve CMS element expand includes; on the
  CMS surface the editor needs the `carve.include_expand` privilege on top of
  CMS editing rights. Product, category and manufacturer fields stay literal
  whoever wrote them. The administration preview now renders through
  `/api/_action/carve/preview` under the editor's own privileges. README, "File
  includes", and `docs/security.md` carry the trust boundary (#35).

### Changed

- Require carve-php `^0.1.9`, the first tag carrying the include pass (#35).
- Lock the admin live preview's engine to carve-js 0.1.7, the newest release the
  declared `^0.1.5` range admits.

### Fixed

- The German plugin label reads `Carve für Shopware` again rather than an ASCII
  transliteration, and `CHANGELOG_de-DE.md` carries real umlauts throughout (#36).

## [0.1.3] - 2026-08-27

### Changed

- Pin the admin live preview's engine into the plugin ZIP:
  `src/Resources/app/administration/package-lock.json` now travels with the
  extension, so the `npm install` behind `shopware-cli extension zip` builds the
  preview against the stated `markup-carve/carve` npm version rather than
  whatever the registry resolves at build time (#21).
- Lock the release artifact to carve-php 0.1.6 and carve-js 0.1.5, the latest
  published engines allowed by the declared ranges at preparation time. The
  administration package now declares `^0.1.5` instead of the historical
  `^0.1.0` floor, so a lockfile refresh cannot silently fall back to an engine
  predating the security release.
- Mermaid, Chart and PlantUML hydration placeholders now carry `role="img"`
  and an accessible name from the current engines; the plugin's integration
  tests assert those attributes rather than the older inaccessible markup.

## [0.1.2] - 2026-08-18

**Superseded - never shipped.** The 0.1.2 release was published without an
installable ZIP and nothing reached the Shopware Community Store, so no merchant
received any of the below. It is left published as the record of what happened;
everything in this section ships in the next release instead. See the 0.1.2
release note and RELEASING.md, "The asset audit".

### Security

- Require carve-php `^0.1.5`, which probes **every** candidate in a list-valued
  URL attribute instead of trusting the value's leading scheme.
  `srcset="safe.png 1x, javascript:alert(1) 2x"` passed the probe on its second
  entry. Upgrade if you render untrusted Carve or import untrusted HTML.

### Changed

- Storefront markup changes with carve-php 0.1.5: a list-table header cell now
  renders as `<th scope="col">` rather than a bare `<th>`, which is what a screen
  reader needs to associate the column. Theme overrides or tests matching on the
  bare tag need updating.

### Added

- Add configurable `:name:` symbol shortcodes with trusted raw-HTML replacement values.

## [0.1.1] - 2026-08-10

### Added
- Opt-in `enablePlantuml` setting: render ` ```plantuml ` / ` ```puml ` fenced blocks as diagrams.
  carve-php emits `<pre class="plantuml">`; since PlantUML has no in-browser renderer, the
  storefront JS POSTs each block to the external Kroki service (`https://kroki.io/plantuml/svg`)
  and inlines the returned SVG as an `<img>` data URI - only when a diagram is present. Off by
  default. Requires `https://kroki.io` in the CSP `connect-src` and `data:` in `img-src`.

### Changed

- Require carve-php `^0.1.4`, including the current security hardening and
  parser/writer convergence fixes.
- Replace the floating code-sniffer dependency with the tagged `^0.6.0` line
  and use stable Composer resolution.
- Replace the deprecated Symfony `services.xml` with `services.yaml`.
- Move CI, drift, and release workflows to the current checkout action runtime.

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
