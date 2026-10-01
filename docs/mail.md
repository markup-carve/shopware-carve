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

Safe mode restricts HTML output. It does not stop interpolated values from
introducing Carve formatting or links. Escape literal data before composing the
source:

```twig
{% set name = order.orderCustomer.firstName|carve_escape %}
{% set body = 'Dear ' ~ name ~ ', your order is on its way.' %}
{{ body|carve }}
```

`carve_escape` escapes punctuation and folds line breaks to spaces for an inline
value. Use it for names and other literal data, not for an authored document.
The filter is not marked HTML-safe; render the assembled source through `carve`.

For email-client compatibility, use ordinary paragraphs, lists, and tables.
Storefront CSS and diagram scripts do not travel with a mail body. Apply inline
styles in the mail wrapper, for example `<div style="font-family:Arial,sans-serif;
line-height:1.5">{{ body|carve }}</div>`. Use `carve_text` for the separate text
part. Commerce references require a sales-channel context and are intended for
storefront content.

For a complete reference template snippet, see `src/Resources/views/email/example-carve.html.twig`.
