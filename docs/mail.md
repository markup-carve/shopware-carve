# Transactional Mail Rendering

The `carve` and `carve_text` filters are registered as global Twig extensions in Shopware. This means they are available in all Twig templates, including mail templates.

## Single Source, Multiple Parts

Mail templates typically consist of both HTML and plain-text parts. With Carve, you can author the content once and render it to both formats.

In the Shopware Admin (Settings > Email templates), create two separate fields:

**HTML part:**
```twig
{% set body = order.customFields.carve_body ?? '' %}
{{ body|carve }}
```

**Plain-text part (separate template field):**
```twig
{% set body = order.customFields.carve_body ?? '' %}
{{ body|carve_text }}
```

The Carve source is stored in a single custom field (e.g., `order.customFields.carve_body`), and both `carve` (HTML output) and `carve_text` (plain-text output) filters consume the same source.

## Safe Mode and User Data

Any user-supplied data interpolated into the Carve source is hardened by Carve's safe mode. This prevents malicious or accidental markup breakage while preserving the intended formatting directives.

For a complete reference template snippet, see `src/Resources/views/email/example-carve.html.twig`.
