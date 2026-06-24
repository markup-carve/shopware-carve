# Carve demo shop (Shopware 6.7, DDEV) — reproduction guide

This guide reproduces a Shopware 6.7 demo shop with `shopware-carve` (plus `carve-php` and
`carve-js`) installed and every surface seeded and browser-verified.

## 1. Scaffold the DDEV project

```bash
mkdir -p /path/to/carve-demo && cd /path/to/carve-demo
ddev config --project-type=shopware6 --project-name=carve-demo --docroot=public
ddev start
ddev composer create -n "shopware/production:^6.7"
ddev exec bin/console system:install --basic-setup --create-database
```

Admin user from `--basic-setup`: `admin` / `shopware`.

## 2. Wire carve-php + the plugin via path repositories

DDEV containers do not see host paths outside the project, so the path repos must point at copies
**inside** the project. This demo used the **copy** approach (not symlink, not host-path repos):

```bash
# copy the three pre-1.0 clones into the project
cp -r /path/to/carve-php       _pkgs/carve-php
cp -r /path/to/shopware-carve  _pkgs/shopware-carve
cp -r /path/to/carve-js        _pkgs/carve-js
```

Add to the demo root `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "_pkgs/carve-php",      "options": { "symlink": false } },
    { "type": "path", "url": "_pkgs/shopware-carve", "options": { "symlink": false } }
],
"minimum-stability": "dev",
"prefer-stable": true
```

> `minimum-stability: dev` is required so Composer accepts the untagged `dev-main` carve-php.

Then:

```bash
ddev composer require markup-carve/carve-php:* markup-carve/shopware-carve:*
ddev exec bin/console plugin:refresh
ddev exec bin/console plugin:install --activate ShopwareCarve
ddev exec bin/console database:migrate --all ShopwareCarve

# carve-php must resolve -> bool(true)
ddev exec php -r "require 'vendor/autoload.php'; var_dump(class_exists('Carve\\CarveConverter'));"
```

## 3. Admin (carve-js live preview) + storefront build

`@markup-carve/carve` is not on npm; install it from the local clone into the plugin's admin module
first (the build picks it up from `vendor/markup-carve/shopware-carve/...administration/node_modules`):

```bash
ddev exec bin/console bundle:dump
ddev exec bash -c 'cd custom/plugins/ShopwareCarve/src/Resources/app/administration 2>/dev/null \
  || cd vendor/markup-carve/shopware-carve/src/Resources/app/administration; \
  npm install /var/www/html/_pkgs/carve-js'
ddev exec bin/build-administration.sh
ddev exec bin/build-storefront.sh
ddev exec bin/console assets:install
ddev exec bin/console theme:compile
ddev exec bin/console cache:clear      # MUST succeed -> proves the DI container compiles
```

Storefront build notes (encountered on 6.7.11):

- `bin/build-storefront.sh` runs `npm ci --prefer-offline`; if the npm cache lacks `vite@8.x`, run a
  one-time `npm install` (without `--prefer-offline`) for the storefront, then re-run the build with
  `SHOPWARE_SKIP_BUNDLE_DUMP=1`.
- The plugin storefront entry must NOT `import './scss/base.scss'` from `app/storefront/src/main.js`
  (webpack has no SCSS loader for plugin JS entries on the storefront build). The SCSS is picked up
  automatically by the Shopware theme build via its conventional path
  `app/storefront/src/scss/base.scss`, so the import is unnecessary.

## 4. Seed one example per surface

Seed via the Admin API (OAuth password grant, client `administration`). All entities are assigned to
the Storefront sales channel and made visible. SKUs used in this demo:

| SKU         | Purpose                                                        |
|-------------|---------------------------------------------------------------|
| `CARVE-A`   | Product A — rich `carve_body` (heading, bold, italic, table, `::: warning`, `:product[CARVE-B]`) |
| `CARVE-B`   | Product B — referenced target of `:product[CARVE-B]`          |
| `CARVE-XSS` | XSS product — `carve_body = [x](javascript:alert(1)) <script>alert(2)</script>` |

Also seeded:

- **Category** "Carve Landing Category" with `carve_category_body` landing copy (heading, `::: note`).
- **CMS page** "Carve Demo CMS Page" with one `carve` block/element, exposed via a landing page at
  `/carve-cms-demo`.

Product A `carve_body` sample:

```text
# Wire Rope Sling Guide

This is a *premium* steel wire rope with /excellent/ flexibility.

## Load Specifications

| Diameter | Min Break Load | Working Limit |
|----------|----------------|---------------|
| 6 mm     | 22 kN          | 4.4 kN        |
| 8 mm     | 39 kN          | 7.8 kN        |
| 10 mm    | 61 kN          | 12.2 kN       |

::: warning
Never exceed the working load limit. Inspect the sling before every use.
:::

See also our compatible shackle: :product[CARVE-B]
```

After seeding, regenerate indexes/SEO URLs and clear caches:

```bash
ddev exec bin/console dal:refresh:index
ddev exec bin/console cache:clear
```

## 5. URLs

From `ddev describe`:

- Storefront: `https://carve-demo.ddev.site:33001`
- Admin: `https://carve-demo.ddev.site:33001/admin` (`admin` / `shopware`)
- Product A: `/Carve-Demo-Wire-Rope-Product-A/CARVE-A`
- Product B: `/Carve-Demo-Shackle-Product-B/CARVE-B`
- XSS product: `/Carve-XSS-Test-Product/CARVE-XSS`
- Category: `/Carve-Landing-Category/`
- CMS landing: `/carve-cms-demo`
- CLI: `ddev exec bin/console carve:render /var/www/html/demo.crv --term`

## 6. Browser verification

Screenshots saved under `carve-demo/_screenshots/`:

| File                                   | Surface                                                   |
|----------------------------------------|-----------------------------------------------------------|
| `01-product-a-detail.png`              | Product A — heading/bold/italic/table/`warning`; `:product[CARVE-B]` → working link to Product B |
| `02-category-page.png`                 | Category landing copy (`note` admonition)                 |
| `03a-cms-storefront.png`               | CMS Carve element on the storefront                       |
| `03d-cms-live-preview-before.png`      | Admin CMS element config — live preview pane              |
| `03f-cms-live-preview-typing.png`      | Admin CMS live preview updating as you type               |
| `04-xss-product-inert.png`             | XSS product — `javascript:` link inert, `<script>` escaped, no alert |
| `cli-ansi-output.txt`                  | `carve:render --term` colored ANSI output                 |

## 7. Configuration (plugin settings)

The plugin exposes four settings at Admin > Extensions > My Extensions > Configure (or
`#/sw/extension/config/ShopwareCarve`):

| Setting | Key | Default | Notes |
|---|---|---|---|
| Safe mode (hardened HTML) | `ShopwareCarve.config.safeMode` | off | Strips raw HTML in Carve source |
| Admin live preview | `ShopwareCarve.config.livePreview` | on | Shows byte-identical preview pane in the CMS element config sidebar |
| Smart quotes (typographic) | `ShopwareCarve.config.smartQuotes` | off | Replaces straight ASCII `"..."` with locale-specific typographic quotes |
| Smart-quote language | `ShopwareCarve.config.smartQuotesLocale` | (none) | Locale code: `en`, `de`, `de-ch`, `fr`, `es`, etc. |

### Verified behaviors

**Icon:** the plugin tile in Extensions > My Extensions shows the Carve icon (green leaf/C on dark
background), not a blank placeholder.

**Configure screen:** all four fields render under the "Carve rendering" card with help-icon tooltips.
The smart-quote language dropdown lists English, German (de), German (Switzerland), French, Spanish,
and more.

**Smart quotes (German):** with Smart quotes ON and language set to `de`, straight double-quotes
`"hello"` in a product's `carve_body` render as German low-9/high-6 quotes `„hello"` on the
storefront. Verified on CARVE-A (cache cleared after save).

**Live preview toggle:** setting Admin live preview OFF saves `{"_value":false}` to
`system_config`; the CMS element config JS component reads this on `created()` via
`systemConfigApiService.getValues('ShopwareCarve.config')` and gates the preview pane behind
`v-if="livePreviewEnabled"`. Setting it back ON re-enables the pane. Toggling was verified via the
Configure screen and confirmed in the DB.

Screenshots saved under `carve-demo/_screenshots/config/`:

| File | Surface |
|------|---------|
| `01-extensions-icon.png` | My Extensions - Carve tile with icon |
| `02-configure-screen.png` | Configure screen - all four settings visible |
| `03-smart-quotes-de.png` | Storefront CARVE-A - German typographic quotes |
| `05-live-preview-off.png` | Configure - Admin live preview toggled OFF |
| `06-live-preview-on.png` | Configure - Admin live preview ON (final state) |
| `07-regression-carve-a.png` | Storefront CARVE-A - renders normally after config changes |
| `08-regression-carve-xss.png` | Storefront CARVE-XSS - XSS remains inert |

## 8. 6.7 compatibility fixes applied to the plugin during this demo

These were genuine 6.7 defects found via browser verification and fixed in the plugin source:

- **CLI:** the render command's ANSI option was `--ansi`, which collides with Symfony's reserved
  global `--ansi`; renamed to `--term`.
- **Product field:** the storefront override targeted the 6.6 path
  `page/product-detail/description.html.twig` (block `page_product_detail_description`); 6.7 moved it
  to `component/product/description.html.twig` (block `component_product_description_content_text`).
- **Category field:** override used the non-existent block `base_content_inner` and accessor
  `page.header.navigation.active`; fixed to block `page_content_blocks` and accessor `page.category`
  (`NavigationPage::getCategory`).
- **CMS element:** `CmsSlotEntity::setData()` requires a `Struct` on 6.7 (an array threw a 500);
  wrapped the data in `ArrayStruct`. Also added the missing storefront block template
  `block/cms-block-carve.html.twig` (without it the slot's element never rendered).
- **CMS live preview:** the config used `<sw-textarea-field v-model:value>`; 6.7 maps this onto
  Meteor's `mt-textarea`, whose v-model prop is `modelValue`. Switched to
  `<mt-textarea :model-value @update:model-value>` so the live preview recomputes on every keystroke.

## 9. Troubleshooting: composer path repo does not re-mirror

With `{ "type": "path", "options": { "symlink": false } }` and a frozen package version,
`ddev composer update markup-carve/shopware-carve` may NOT re-copy edited source into
`vendor/`. Since `theme:compile` and the storefront/admin builds read from `vendor/`, a stale
copy silently ships old SCSS/JS. Force a clean re-mirror after editing the plugin source:

``` bash
rm -rf vendor/markup-carve/shopware-carve && ddev composer install --no-interaction
```

Then rebuild (`bin/build-administration.sh`, `bin/build-storefront.sh`, `theme:compile`,
`cache:clear`). The same applies to the local `markup-carve/carve-php` clone.
