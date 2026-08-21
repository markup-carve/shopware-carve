# Next version
- Pin the admin live preview's engine into the plugin ZIP, so a build resolves the stated `@markup-carve/carve` version instead of whatever the registry serves.

# 0.1.2
- Require carve-php 0.1.5, which probes every candidate in a list-valued URL attribute instead of trusting the value's leading scheme. Upgrade if you render untrusted Carve or import untrusted HTML.
- Add configurable `:name:` symbol shortcodes with trusted raw-HTML replacement values.
- A list-table header cell now renders as `<th scope="col">` rather than a bare `<th>`. Theme overrides or tests matching on the bare tag need updating.

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
