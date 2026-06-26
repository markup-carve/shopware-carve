# Security: Carve vs Markdown in Shopware

This plugin renders authored content (product/category copy, CMS, mail, and
product reviews) to HTML. This page explains why that is safe by default, how it
compares to a typical Markdown setup, and where the boundaries are.

All carve-php outputs shown below were produced with the library this plugin
ships; the "safe" and "raw" columns are the two modes (`allowRawHtml` off/on).

## Threat model

The dangerous classes for shop content are: script execution (`<script>`,
`<img onerror>`), dangerous URL schemes (`javascript:`, `data:`), event-handler
and injection-sink attributes (`on*`, `srcdoc`, `formaction`), and CPU DoS from
pathological input. UGC (reviews) adds: untrusted authors at scale.

## Three always-on guarantees + one admin gate

carve-php enforces these on **every** render, independent of any plugin setting
(they are part of the Carve specification, not a configurable add-on):

1. **No raw-HTML execution.** A typed `<script>`/`<img>` is escaped to text.
   Verbatim HTML is only possible through an explicit `` ```=html `` fence, and
   only when an admin enables `allowRawHtml`.
2. **URL-scheme denylist.** `javascript:`, `vbscript:`, `data:`, `file:` href/src
   values are blanked.
3. **Attribute hardening.** `on*`, `srcdoc`, `formaction` and script-bearing CSS
   are stripped - even when written via Carve's own `{...}` attribute syntax.

The single gate is **`allowRawHtml`** (default off): it only affects the
explicit `` ```=html `` passthrough fence, on admin-authored content. It does not
weaken guarantees 2 and 3, and it never applies to reviews (see below).

Markdown has none of guarantees 1-3 by default. Most Markdown renderers pass raw
HTML through, and a popular PHP library (Parsedown) ships with raw HTML and
`javascript:` links enabled by default. To get parity you must configure the
renderer and add a separate sanitizer (HTMLPurifier server-side / DOMPurify
client-side).

## Attack vectors, side by side

Author input on a product description or review field. "Markdown" = a typical
CommonMark/Parsedown setup without a bolted-on sanitizer. "Carve" = this plugin
(both modes are identical for all of these - only the explicit `=html` fence
differs).

| Author input | Markdown (no sanitizer) | Carve (this plugin) |
|---|---|---|
| `<script>alert(document.cookie)</script>` | emitted verbatim, **runs - session/cookie theft** | `&lt;script&gt;...` escaped, inert |
| `<img src=x onerror=alert(1)>` | verbatim, **onerror fires** | escaped, inert |
| `[click](javascript:alert(1))` | often kept (Parsedown does), **runs on click** | `<a href="">click</a>` - scheme blanked |
| `![x](data:text/html,<script>...>)` | data: image, **XSS vector** | `<img src="">` - scheme blanked |
| `[hover]{onmouseover="alert(1)"}(/p)` | (attribute extensions may keep `onmouseover`) | `<span>hover</span>` - `on*` stripped |
| `[x](vbscript:msgbox(1))` | kept | `href=""` blanked |

A naive injected `<script>` can never execute in Carve regardless of
`allowRawHtml`, because bare HTML is treated as text - only the explicit
`` ```=html `` fence is a passthrough, and only with the gate on.

## Per Shopware surface

- **Product / category / CMS / mail (admin-authored).** The `|carve` /
  `|carve_ctx` filters are registered `is_safe => html`. This is sound because of
  guarantees 1-3: staff cannot introduce XSS unless an admin has explicitly
  enabled `allowRawHtml` (a trusted-author choice on admin-authored content).
- **Product reviews (UGC), `|carve_ugc` / `renderReviews`.** Forces safe mode
  **and** the `comment` profile **regardless of global settings**. So even with
  `allowRawHtml` on shop-wide, review text stays escaped, headings/tables/images/
  divs are stripped, nesting is capped, and links get `rel="nofollow ugc"`.
  Markdown on a review field is stored XSS the first time someone posts a script.

## Profiles as a node-type allowlist

For untrusted or semi-trusted content, the `profile` setting (or the forced
`comment` profile on reviews) restricts which Carve node types may appear, as an
AST transform before rendering:

- `comment` - basic inline + paragraphs/lists/quotes/code only; no headings,
  images, tables, footnotes, raw HTML, divs. Links `nofollow ugc`. Max nesting 4.
- `minimal` - inline + paragraphs/lists only; no links/images. Max nesting 2.

This shrinks the attack surface beyond the always-on hardening and is the
recommended setting for any user-authored field.

## Denial of service

- **Carve:** linear-time parsing with parse-depth caps (spec-mandated), so
  deeply nested or pathological input is bounded.
- **Markdown:** behavior varies by library; several are super-linear on
  pathological input and have no depth cap.

## Determinism

Carve is deterministic and identical across implementations (php / js / rust,
shared corpus). The admin carve-js live preview therefore shows exactly what the
storefront will emit, and the security boundary is snapshot-testable. Markdown
output varies by flavor, library, and version.

## Residual risk and honest limits

- **`allowRawHtml` on.** Re-accepts raw-HTML risk for the explicit `=html` fence,
  on admin-authored content only. Deliberate, gated, trusted-author. UGC paths
  ignore it. Leave it off unless every content author is fully trusted.
- **Pre-1.0.** carve-php is corpus-pinned but pre-1.0; output/syntax can still
  move. Pin versions and review its changelog before upgrading.
- **Not a full DOM sanitizer.** Carve hardens schemes, event handlers and script
  CSS, but does not allowlist which attributes may appear on which tag. For
  **fully untrusted input where arbitrary attributes are allowed**, use a
  restrictive `profile` (the review surface already forces `comment`) and, for
  defense in depth, run the output through DOMPurify. For the default surfaces
  (admin-authored content, and reviews under the comment profile) the built-in
  guarantees are sufficient.

## Bottom line

Carve is safe-by-default where Markdown is unsafe-by-default. Script execution,
`javascript:`/`data:` URLs, and event-handler attributes are neutralized on every
surface with zero configuration; the only way to opt back into raw HTML is an
explicit admin toggle that never touches user-generated content.
