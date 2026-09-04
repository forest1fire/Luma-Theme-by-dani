# Luma v1.33.0 / Luma Core v1.30.0 release report

Theme **1.33.0** · Luma Core **1.30.0**. A correctness, internationalization and
verification release: the defects found during a full audit were fixed, both
packages were made genuinely translation-ready, and the changes are now backed by
repeatable quality gates rather than manual inspection.

## Bugs fixed — theme

- `searchform.php` emitted the same `id` twice when a page rendered two search
  forms; each field now uses `wp_unique_id()`.
- The footer newsletter form posted to a non-existent handler and printed
  hardcoded English. It now targets an AJAX action, is guarded by a WooCommerce
  check, and every string is translatable.
- Header action links carried no accessible names and the bag/wish-list counts
  were invisible to assistive technology because `aria-label` hid the badge text.
  The labels are now localized and kept in step with the badge by an observer.
- `page.php` skipped the standard content wrapper, `front-page.php` referenced
  undefined variables, and `template-parts/content.php` had no fallback markup.
- Duplicate animation rules existed in both `theme.css` and `seo-motion.css`.
- The mobile mega menu did not open, and focus was not managed on open or close.
- WooCommerce printed its own `<div id="primary"><main>` inside the theme's
  wrapper, producing two `#primary` elements and a `<main>` nested in a `<main>`.
  `inc/woocommerce.php` now removes both wrapper actions.

## Bugs fixed — Luma Core

- UTM capture sanitized before decoding and accepted any parameter name; it now
  decodes first and validates against a whitelist.
- Offer and size-guide popups called WooCommerce functions without checking the
  plugin was active — a fatal error on sites without it.
- Elementor widgets registered eagerly, breaking on sites without Elementor.
- AJAX filter requests ignored `paged`, so filtering always returned page one.
  Requests now send `paged` and a validated `base_url`, and the response's server
  rendered pagination replaces the stale markup.
- Order-bump availability was not checked before use.
- Review and cart counts used a singular string for plural quantities; `_n()` now.
- `product_tag` filters were dropped from the tax query, and `on_sale` did nothing.
- Privacy data was retained with no exporter, eraser or policy text. Both are now
  registered, waitlist and lead records are hard-deleted on request, and the
  privacy policy content is contributed to WordPress.
- Health-check output was echoed without escaping.
- Demo content wrote literal `\n` instead of newlines.

## Bugs fixed — front-end JavaScript

- `core.js` had no load guard, so a second inclusion redeclared everything.
- Filter empty and error states injected server text with `.html()`, an XSS sink;
  they now build text nodes only.
- Offer countdowns kept their interval running forever after expiry. A second
  leak sat behind it: `initCountdowns()` re-runs on every WooCommerce fragment
  refresh and armed a fresh interval each time, overwriting the stored id so the
  previous timer could never be cleared. It now clears any existing timer first,
  and arms none at all for an offer that has already ended.
- Quick-add, coupon and share buttons changed their label and never restored it,
  leaving the control stuck on "Adding…".
- Clipboard calls had no rejection handler and failed silently on Firefox and
  Safari, where `navigator.clipboard` can be absent.
- Several ternaries were dead code, and every remaining aria-label was hardcoded
  English. Two user-visible strings survived the first internationalization pass
  (the coupon confirmation and an order-bump failure message) and are now routed
  through the localized bundle.
- Variation swatches were unusable by keyboard. They are now a named
  `role="group"` with `aria-pressed` state, the hidden select is removed from the
  accessibility tree, and swatches re-sync on WooCommerce variation events.
- The injected `.luma-variation-swatches` markup had no stylesheet at all. The
  missing rules are in `core.css` and use theme tokens with light-mode fallbacks.
- Three more injected elements had markup but no rule, so they fell back to
  browser defaults: `.luma-collection-loading` is a `<span>` inside a four-column
  grid and rendered squeezed into one quarter-width cell instead of spanning it;
  `.luma-cart-drawer__saved` had none of the separation its recommendations
  sibling has; `.luma-cart-notices` had no spacing once populated. All three are
  now styled, and the two drawer regions take no space while empty.

## Upgrades added

- **Dark colour scheme**, end to end: a merchant default, a visitor toggle,
  `localStorage` persistence, an inline no-flash bootstrap, and a
  `html[data-luma-theme="dark"]` token block that overrides Customizer output.
- **Breadcrumbs** on every template, with `BreadcrumbList` microdata.
- **Related posts**, **reading progress**, **back-to-top**, and **sticky-header
  auto-hide**, each individually switchable in the Customizer.
- **Full internationalization** of both packages. 481 unique strings are wrapped
  and ship with generated `.pot` templates; front-end scripts read their copy from
  `wp_localize_script` instead of embedding English.
- **Privacy policy content** contributed by both packages.

## Packaging metadata

Found while rebuilding the installable archives — WordPress and WooCommerce read
compatibility data from file headers, not from readme prose:

- `style.css` declared no `Tested up to`, so the theme details screen showed
  nothing even though `readme.txt` claimed 6.6. Added, plus `License URI`.
- The plugin header and its `readme.txt` declared no `Tested up to` at all.
  Added to both, plus `License URI`.
- Neither `readme.txt` had a `== Changelog ==` section, so the distributed ZIPs
  shipped no version history. Both now carry entries for every documented
  release, reconstructed from the reports in this folder.

One gap is deliberately **not** filled: Luma Core declares no `WC tested up to`
or `WC requires at least`. WooCommerce uses those to list an extension as
untested and to warn before updating. Inventing a number would be a false
compatibility claim, so the audit reports it as a standing warning instead —
set both after testing against a live store.

Note that the declared WordPress compatibility is **6.6**, which is stale:
WordPress 7.1 shipped on 19 August 2026. Bumping it requires testing on a real
install, which no static check can substitute for.

## Quality gates

Added under `LUMA-STORE/tools/`, all wired into `.github/workflows/luma-checks.yml`:

| Command | What it proves |
| --- | --- |
| `node tools/audit.js` | Escaping, duplicate ids, brace balance, version drift, header metadata, changelog presence, i18n key coverage, WooCommerce guard reachability, JS-created class coverage, dead code, template hierarchy |
| `node tools/make-pot.js --check` | `.pot` templates match the strings actually in source |
| `node tools/smoke-test.js` | `theme.js` executes against real markup and behaves correctly |
| `node tools/core-smoke-test.js` | `core.js` executes on a jQuery double with AJAX intercepted |
| `node tools/build-packages.js --check` | Shipped ZIPs match the source tree they were built from |

`npm run verify` in `LUMA-STORE/tools/` runs all four.

### Verified state

- Static audit: **0 errors** across 20 PHP, 7 CSS, 2 JS and 5 JSON files, with one
  standing warning for the undeclared WooCommerce compatibility headers.
- Behaviour tests: **53 theme checks** (scheme toggle, view switcher, count labels,
  mobile menu, search panel, scroll chrome, reading progress) and **101 Core
  checks** (variation swatches, countdowns, coupon and filter AJAX, injection
  safety, collections, quick add, order bumps, bundles, share, predictive search).
- Translation templates: 181 theme and 300 plugin entries, plural forms intact.
- Package parity: theme, plugin and demo-kit digests match source.

## What executing the code found

Static analysis had already reported zero errors on these files. Running them
found defects it could not see:

1. `initCountdowns()` stacked an interval on every fragment refresh and lost the
   id needed to clear it.
2. Two strings were still hardcoded English after the internationalization pass.
3. `filterEmptyState()` depends on the two-argument jQuery element factory, a
   code path no static check exercised.
4. A missing i18n key fails silently — the helper returns its English fallback
   and nothing looks wrong — so an audit rule now requires every key a script
   looks up to exist in the PHP bundle.

## WooCommerce guard reachability

The popup fatal was one instance of a general risk: Luma Core calls the
WooCommerce API in over sixty places, and any of them reached while WooCommerce
is inactive is a fatal error. Grepping cannot answer this, because a guard only
counts if it sits between the entry point and the call.

The audit now walks the PHP AST and treats a function as safe when an enclosing
`if` or ternary tests for WooCommerce, when its own body does (the usual
`if ( ! class_exists( 'WooCommerce' ) ) return;`), when it is only reached from
WooCommerce's own hooks, or when every call site is itself safe. That last rule
propagates, so helpers such as `luma_core_cart_payload()` are correctly judged
safe: they hold no guard of their own, but each AJAX handler that calls them
checks `luma_core_cart_available()` first. WooCommerce's own template overrides
are skipped, since WordPress only loads them through WooCommerce.

Result: **no unguarded WooCommerce call site remains** in either package. The
rule was verified by negative test — removing the ternary guard in
`front-page.php` and removing one caller's availability check both produce
warnings that name the function and its callers.

## Unstyled injected markup

The swatch stylesheet was found by hand. To stop the same class of bug recurring,
the audit now requires every `luma-` class that JavaScript creates in an element
factory to exist in a shipped stylesheet. Runtime-created nodes are the risky
case precisely because no template preview ever shows them.

The rule is deliberately narrow. A broad "every class used anywhere must be
defined" check was tried and rejected: it produced 32 findings, and every one
examined was legitimate — classes styled through a parent flex or grid rule
(`.luma-coupon-code` inside the flex `.luma-apply-coupon`), through a descendant
selector (`.luma-mini-cart` under `.luma-cart-drawer__body ul`), through a second
class on the same element (`.luma-recommendation__quick-add` also carries
`.luma-quick-add`), or semantic and JS hooks that are not meant to be styled.
A check that cries wolf gets ignored, so this one covers only the case that
actually broke.

Both forms of jQuery element creation are detected, including the two-argument
`$('<li>', { class: '…' })` factory. Verified by negative test: renaming the
swatch class in the script alone now fails the audit.

## House rules preserved

- No fake sales, stock or urgency signals were introduced.
- No tracking, no external fonts, no new runtime dependencies.
- Privacy behaviour was strengthened, never weakened.
- RTL, reduced-motion, Elementor and WooCommerce compatibility are intact.
- Versions were bumped in every header, constant and readme for cache busting.

## Installable packages

`LUMA-STORE/packages/` holds `luma-commerce-theme.zip`, `luma-commerce-core.zip`,
`luma-demo-kit.zip` and `SHA256SUMS.txt`.
