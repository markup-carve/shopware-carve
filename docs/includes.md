# Shared content with includes

Use transclusion to maintain a passage once and insert it into several Carve CMS
slots. Suitable content includes product-family care guides, installation
instructions, approved delivery copy, and brand introductions. An include merges
Carve source before rendering, so its headings, references, and commerce elements
use the page's renderer and sales-channel context.

## Configure the content library

1. Create a directory containing publication-ready `.crv` files. Keep credentials,
   private notes, and uploads outside it.
2. Set `ShopwareCarve.config.includeRoot` to its absolute path. This setting is
   global because it grants filesystem access; it does not follow the chosen
   preview sales channel.
3. Give authorized CMS editors the `carve.include_expand` additional permission.
4. Deploy the files, then run `bin/console carve:includes:invalidate`.

The root remains empty by default. The plugin never fetches remote include URLs.
The engine rejects traversal and symlink escapes and limits recursion, expanded
bytes, resolver calls, and diagnostics. See [Security](security.md).

Privileged editors can select shared files under **Formatting help and insert
tools** in the Carve CMS editor. The catalog lists at most 500 `.crv` paths with
letters, digits, underscores, dots, slashes, and hyphens. Files with other names
can still be referenced through the engine's quoted-path syntax. The catalog
returns relative names without revealing the server's absolute root.

## Select a passage

```carve
{{ shared/care.crv }}

{{ shared/guide.crv #installation }}

{{ shared/guide.crv #installation @shift:1 }}
```

A section selection includes its heading and contents up to the next heading at
the same or a higher level. Use explicit heading IDs for passages other documents
reference. `@shift:1` turns an included level-one heading into a level-two heading.
Nested paths resolve relative to the file that contains them.

The engine also supports physical line selection:

```carve
{{ shared/guide.crv @lines:3-8 }}
```

Use either a section ID or a line range in one directive. Section IDs survive
edits elsewhere in the file better than line numbers. The editor accepts a
section ID and heading shift; line ranges remain available in source.

Included headings and footnotes participate in the parent document's numbering
and collision handling. After rendering, the plugin scopes fragment IDs and
radio groups to the CMS slot, keeping separate blocks independent on one page.

## Translations and channels

Keep translated source under explicit locale directories, for example
`en-GB/shared.crv` and `de-DE/shared.crv`. In each translated CMS slot, select its
locale's file:

```carve
{{ en-GB/shared.crv #care @shift:1 }}
```

The German slot uses `de-DE/shared.crv` with the same section ID. This makes the
translation choice visible in source. There is no implicit file-language
fallback. Shopware's CMS translation fallback still applies to the slot itself.
For channel-specific copy, use explicit paths such as `retail/en-GB/delivery.crv`
and `trade/en-GB/delivery.crv` in the assigned layouts.

The [example library](../examples/includes) includes two translated documents
and two parent pages. Its placeholder paragraphs must be replaced with approved
shop copy before publication. Commerce references inside a selected passage
resolve using the containing page's channel, currency, and language.

## Publishing permissions

Saving a static Carve CMS source or a product, category, landing-page, or homepage
slot override containing an include requires `carve.include_expand`, even while
the root is unset. Code examples inside verbatim spans and code fences stay
literal. Changing an existing slot from another type to Carve also requires the
privilege, because saved translations or overrides can become active that way.

Product, category, and manufacturer text fields do not expand file includes.
Mapped CMS content also keeps them literal. Use a static CMS slot for publication
content that reads files. Untrusted review content never expands includes.

The permission check applies to new writes. Review existing CMS sources before
enabling a root on an upgraded shop: older stored directives have no record of
who approved them. Every file under the root must be suitable for publication.

## Preview and deployment

The editor renders through the PHP service and displays include warnings,
relative dependency paths, unresolved targets, and source diagnostics. Select a
sales channel to resolve product references and preview guest prices. Previewing
includes checks the acting editor's include privilege; selecting a
channel does not grant filesystem access.

Pages receive cache tags for included paths, including missing targets. After
changing, removing, or adding files, invalidate their dependants:

```bash
bin/console carve:includes:invalidate en-GB/shared.crv de-DE/shared.crv
```

Paths are relative to `includeRoot`. Omitting paths invalidates every page that
ran the include pass. Run that form when changing the root or replacing the
whole library. The command forces invalidation rather than waiting for delayed
cache processing. File changes outside Shopware do not produce database events;
add this command to the deployment job. HTTP-cache lifetime is not a file watcher.
