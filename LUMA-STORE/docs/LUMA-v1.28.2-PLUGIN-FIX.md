# Luma Core v1.28.2 plugin fix

## User-reported issue

After activating Luma Core, the storefront preview appeared broken and the footer showed PHP source/error text.

## Root cause

`luma_core_footer_features()` switched from PHP into inline HTML at the start of its output, but the function closing brace and the `add_action()` call were not switched back into PHP. As a result, the plugin printed this PHP source into the public footer:

- the function closing brace
- the `add_action()` registration
- the beginning of `luma_core_offer_popup()`

This corrupted the footer output and could make the storefront appear malformed.

## Fixes

- Restored the missing `<?php` boundary before the footer function closing brace.
- Added a safe cart-availability check before reading `WC()->cart` for the footer subtotal, preventing warnings in previews where the cart object is not initialized.
- Bumped Luma Core to `1.28.2` so the corrected plugin can be identified and its assets are cache-busted.

## Horizontal scrollbar fix

The storefront also received a responsive safety pass:

- Page-level horizontal overflow is clipped without disabling inner comparison-table scrolling.
- Grid children use `min-width: 0` so long product names and controls cannot force the viewport wider.
- Homepage, footer, shop, recommendation, bundle and trust grids use safe `minmax(0, 1fr)` tracks.
- Core bundle and trust modules collapse safely on narrow screens.
- Theme version was bumped to `1.28.2` so the new CSS is cache-busted.

## Validation

- PHP lint passed for Core, Theme and included PHP files.
- No plugin PHP source is present in inline frontend HTML tokens.
- Core JavaScript syntax passed.
- Core CSS syntax passed.
- JSON validation passed.
- Core source/archive byte parity passed after rebuilding the package.
- Core ZIP SHA-256: `040c2e53d11027a9267ebbc9fa164956e763d44b95fc7f47bdd642d5cab30987`.
