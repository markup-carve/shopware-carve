# 0.1.9
- New console commands: carve:lint checks the Carve in products, categories, manufacturers and CMS elements, carve:fmt formats Carve files and can align tables, and carve:convert migrates existing HTML descriptions into Carve fields (dry run by default).
- New setting "Reject saves with Carve lint findings", off by default.
- The editor preview reports every Carve lint rule, including list, continuation and table problems.
- Includes can select a named region or insert a file as a code block.
- Requires carve-php 0.1.15.

- Carve content can show native badges, calls to action, FAQ and accordion sections, coupons, countdowns, product sale and image views.
- Storefront layouts include trust badges, gallery rows, sparklines, CSV tables, contextual prices, shipping notes, selected options, review ratings and standalone product cards.
- YouTube videos load after visitor action. Named app blocks, public configuration, bundled assets and theme icons are available in Carve content.

# 0.1.8
- Requires carve-php 0.1.12, with updated table spans, footnotes and task-list markup.
- Markdown abbreviation definitions and bare HTML line-block containers import differently. Review the editor's import preview before saving.
- To keep literal backslashes in image alt text or quoted include paths, double them in the source.

# 0.1.7
- The editor's preview now flags structural problems and broken references while you write, next to the Markdown-habit warnings it already showed: an undefined reference, or a link pointing at a fragment that does not exist on the rendered page.
- Requires carve-php 0.1.11.

# 0.1.6
- Installing or updating the plugin no longer aborts while reading its configuration, and the service container compiles on a stock shop.
- The editor's preview, import and shared-file endpoints are now registered, so the administration preview renders. It also works with debug mode on.
- `:legal[privacy]`, `tos`, `imprint` and `withdrawal` link the layout configured under basic information. They previously stayed plain text.
- A single product card keeps a card-sized width.
- Category copy lines up with the rest of the page instead of running edge to edge, and cards and grids keep space before the next block.
- The editor's image picker shows file names. It previously listed blank rows.
- Specification lists render as a two-column list with row separators.

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
