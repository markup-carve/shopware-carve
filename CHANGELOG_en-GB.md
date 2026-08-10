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
