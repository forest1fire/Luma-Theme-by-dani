# Luma Core 1.29.0 — Filter UX Upgrade

## What changed

Luma Core's native WooCommerce shop filters now use a mobile-first drawer while retaining the persistent desktop toolbar.

- Added a full-height mobile filter drawer with a visible close control.
- Added a dimmed, keyboard-safe backdrop that closes the drawer without navigating away.
- Added focus placement on the drawer close control when the drawer opens.
- Added Escape-to-close behavior and `aria-expanded` / `aria-hidden` state updates.
- Prevented page scroll while the mobile drawer is open.
- Added applied-filter chips for category, price bounds, stock, sale and attribute selections.
- Added individual chip removal and a Clear all action; clearing immediately refreshes the AJAX result set.
- Added a polite live region to the result count and applied-filter summary so refreshed results are announced.
- Preserved AJAX filtering, pagination query synchronization, desktop visibility, RTL drawer placement and native WooCommerce markup compatibility.
- Added translated strings for the new summary labels and actions.

## Validation

- PHP syntax validation passed for `luma-commerce-core.php`.
- JavaScript syntax validation passed for `assets/js/core.js`.
- Core source text/encoding validation passed.
- Core package/source parity passed after rebuilding the installable ZIP. SHA-256: `af0ad5defed580523c41f18f3e9cd17248fa0653242cd7f57c1c775c238a421d`.

## Merchant and privacy notes

This release changes only filter presentation and state handling. It adds no tracking, fabricated stock or sales claims, consent changes, customer profiling or external network dependency.
