# 0.1.6
- Installing or updating the plugin no longer aborts while reading its configuration, and the service container compiles on a stock shop.
- The editor's preview, import and shared-file endpoints are now registered, so the administration preview renders. It also works with debug mode on.
- `:legal[privacy]`, `tos`, `imprint` and `withdrawal` link the layout configured under basic information. They previously stayed plain text.
- A single product card keeps a card-sized width.

# 0.1.5
- One shared Carve editor across the CMS element and the product, category and manufacturer fields, with server-side preview, diagnostics, product and media selection, and reviewed HTML and Markdown import.
- New commerce elements for authoring: sales-channel prices, stock labels, cards, grids, specification lists, datasheets, media images, snippets and internal links.
- Shared content includes: pick from an include library, write checks on publication, dependency cache tags, and the `carve:includes:invalidate` command for deployment jobs.
- Configurable CMS field mapping, copy placement and external-link behavior, automatic typography locale, and literal-data escaping for mail.
- Product references now respect sales-channel visibility and use SEO URLs. Lookups are batched, so a page with many references issues far fewer queries.
- The administration preview rejects stale responses and shows failures in a sandboxed frame instead of keeping the previous render.
- Fragment ids and tab radio groups stay independent when several Carve blocks appear on one page.

# 0.1.4
- Add gated file includes. An administrator sets an absolute include containment root, empty by default, which keeps every include directive literal everywhere.
- With a root configured, the `carve:render` command and the Carve CMS element expand includes; a CMS editor additionally needs the "Expand Carve file includes" privilege. Product, category and manufacturer fields keep directives literal whoever wrote them.
- The administration live preview now renders through a server route under the editor's own privileges, so it shows what the storefront produces.
- Require carve-php 0.1.9, and lock the admin preview engine to carve-js 0.1.7.
- The German plugin label reads "Carve für Shopware" again rather than an ASCII transliteration.

# 0.1.3
- Require carve-php 0.1.5, which probes every candidate in a list-valued URL attribute instead of trusting the value's leading scheme. Upgrade if you render untrusted Carve or import untrusted HTML.
- Add configurable `:name:` symbol shortcodes with trusted raw-HTML replacement values.
- A list-table header cell now renders as `<th scope="col">` rather than a bare `<th>`. Theme overrides or tests matching on the bare tag need updating.
- Pin the admin live preview's engine into the plugin ZIP, so a build resolves the stated `@markup-carve/carve` version instead of whatever the registry serves.
- Lock the release ZIP to carve-php 0.1.6 and carve-js 0.1.5, and require the security-fixed `^0.1.5` JavaScript line.
- Give Mermaid, Chart and PlantUML hydration placeholders an image role and accessible name.

# 0.1.2
- Never released. This version was never published to the store, so no shop received it; everything it contained is listed under the version above.

# 0.1.1
- Add opt-in PlantUML and Puml fence rendering through Kroki, disabled by default.
- Update the Carve engine to 0.1.4 for current security hardening and rendering fixes.
- Update plugin service configuration and pin development tooling to tagged stable releases.

# 0.1.0
- Initial release: render the Carve markup language to safe HTML across Shopware 6.6 and 6.7.
- Twig filters (carve, carve_text, carve_md), a Carve CMS element and block, product and category custom fields, admin live preview, transactional mail rendering, and inline product references.
- Always-on extensions: admonitions, details, list tables, footnotes, autolink, external-link hardening, table of contents, and spoilers.
- Configurable raw-HTML passthrough (off by default), smart quotes with 20 locales, and opt-in Mermaid and Chart.js rendering.
- Always-on security baseline: URL-scheme denylist and attribute hardening, independent of any setting.
