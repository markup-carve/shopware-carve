# shopware-carve

[![Packagist Version](https://img.shields.io/packagist/v/markup-carve/shopware-carve.svg)](https://packagist.org/packages/markup-carve/shopware-carve)
[![CI](https://github.com/markup-carve/shopware-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/shopware-carve/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/markup-carve/shopware-carve.svg)](https://packagist.org/packages/markup-carve/shopware-carve)
[![License](https://img.shields.io/packagist/l/markup-carve/shopware-carve.svg)](LICENSE)

Render [Carve](https://github.com/markup-carve/carve) markup in Shopware 6.
The plugin supports Twig filters, CMS elements, product and category fields,
admin preview, transactional mail, product references, reviews, and CLI
conversion.

Install `markup-carve/shopware-carve` for Shopware 6.6 or 6.7 on PHP 8.2 or
newer. The package uses the `MarkupCarve\Shopware\` namespace and the MIT
license.

> This package is pre-1.0. Syntax and output may change before 1.0. Pin
> versions and review the carve-php changelog before upgrading.

## Install

```bash
composer require markup-carve/shopware-carve
bin/console plugin:refresh
bin/console plugin:install --activate ShopwareCarve
bin/console database:migrate --all ShopwareCarve
bin/console bundle:dump
bin/build-administration.sh
bin/console assets:install
bin/console theme:compile
bin/console cache:clear
```

See the [installation reference](docs/reference.md#install) for prerequisites,
development installs, and asset-package details.

## Twig filters

```twig
{{ product.translated.description | carve }}
{{ product.translated.description | carve_text }}
{{ product.translated.description | carve_md }}
```

Use `carve` for HTML, `carve_text` for plain text, and `carve_md` for Markdown.
Content containing `:product[SKU]` references needs the sales-channel-aware
filter:

```twig
{{ product.translated.description | carve_ctx(context) }}
```

## Shopware surfaces

The plugin supplies:

- a Carve CMS element with live administration preview;
- `carve_body`, `carve_category_body`, and `carve_manufacturer_body` fields;
- HTML and plain-text transactional mail rendering;
- `:product[SKU]` inline references resolved in the sales-channel context;
- a restricted comment profile for product reviews;
- `carve:render` for HTML, text, Markdown, and terminal output.

The [surface reference](docs/reference.md#surfaces) documents each integration
point and includes template examples. The [gallery](GALLERY.md) shows the CMS,
storefront, mail, review, and CLI output with its source.

## Configuration

Configure the plugin under **Extensions > My extensions > Carve for Shopware**.
The main settings control raw HTML, live preview, symbol shortcodes, smart
quotes, Mermaid, Chart.js, PlantUML, content profiles, and contained file
includes.

Raw HTML is disabled by default. Diagram options may load code or images from
configured external services, so their CSP and trust requirements differ from
the core renderer.

The [configuration reference](docs/reference.md#configuration) lists every
setting, default, CSP requirement, and custom-profile extension point.

## Security

The renderer always neutralizes dangerous URL schemes and strips event-handler,
`srcdoc`, and `formaction` attributes. Raw HTML remains off unless an
administrator enables it. Review content uses the stricter `comment` profile.

See [Security](docs/security.md) for the threat model, examples, and the limits
of each content profile.

## Enabled Carve extensions

Admonitions, details, list tables, inline footnotes, autolinks, external-link
hardening, tables of contents, spoilers, code groups, and tabs are registered
without extra JavaScript. Smart quotes and diagram renderers are enabled by
configuration.

The [extension table](docs/reference.md#enabled-extensions) lists their exact
syntax and output behavior.

## Development

The [complete reference](docs/reference.md) retains the API, configuration,
surface, version-support, and maintenance details. Run `composer check` plus
the checks in the [storefront JavaScript workflow](.github/workflows/storefront-js.yml)
before submitting changes.
