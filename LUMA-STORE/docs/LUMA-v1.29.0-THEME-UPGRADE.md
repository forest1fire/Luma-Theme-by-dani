# Luma Theme v1.29.0 upgrade

## What was completed

- Added a keyboard-accessible Skip to content link.
- Added visible `:focus-visible` states for keyboard navigation.
- Improved mobile menu behavior with outside-click close, Escape close, resize close and body scroll lock.
- Improved search panel behavior with outside-click close and focus return on Escape.
- Added safer cart-count handling when WooCommerce has not initialized a cart object, including builder/preview contexts.
- Added responsive WooCommerce styling for cart, checkout, account navigation, notices, forms and tables.
- Added editor-content overflow protection for long text and embedded media.
- Preserved reduced-motion behavior.
- Preserved RTL, Elementor, WooCommerce and normal Customizer compatibility.

## Intentional limits

- No fake sales, stock scarcity, activity or urgency was added.
- No external font, tracking script or third-party dependency was introduced.
- No customer data or consent behavior was weakened.

## Validation

- PHP lint passed for Core, Theme and included PHP files.
- JavaScript syntax passed.
- CSS syntax passed.
- JSON validation passed.
- Theme version is `1.29.0` for cache busting.
