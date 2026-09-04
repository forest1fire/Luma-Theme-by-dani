=== Luma Core ===
Contributors: codewithdani
Tags: woocommerce, sales, cart, search, wishlist, compare, conversion
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.30.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Luma's custom WooCommerce sales engine. It is designed to keep the storefront fast and commercially useful without stacking multiple overlapping add-on plugins.

== Included ==
* AJAX cart drawer and cart count updates, including real shipping-progress messaging, cart-aware cross-sell recommendations and a session-based Save for later list. Cart recommendation count, copy and visibility are merchant-configurable in Luma Control Center. Signed-in customers can also keep saved cart items synchronized to their account; guest saved items remain session-only.
* Quick view, simple-product quick add and Buy Now checkout action.
* Predictive product search in the Luma theme search panel.
* Wishlist and recently viewed collections with account sync for logged-in customers, plus a real side-by-side compare table with native prices, ratings, availability and attributes.
* Smart “You may also like” recommendations on product pages, an order-received follow-up based on the actual order's purchased products and native cross-sells, plus a real-product reorder section in account order details.
* Curated complete-the-look bundle with editable SKU selection and add-all action.
* Native shop toolbar with category, price and sort filters, a mobile filter drawer, applied-filter chips, clear controls and keyboard-friendly live updates.
* Countdown timer, coupon booster, trust bar, real stock signals and free-delivery progress meter.
* Welcome-offer popup with timed and desktop exit-intent trigger, one-display-per-session behavior, coupon copy and lead capture.
* Luma leads, back-in-stock waitlist and cart-recovery foundation stored in WordPress admin.
* Checkout order bump that the customer explicitly opts into; no add-on is added silently.
* Optional campaign attribution cookie and non-personal dataLayer funnel events, including page view, view item, add-to-cart intent, checkout, promotion, bundle and purchase events.
* Standalone Luma admin menu with a Dashboard entry outside the WooCommerce menu, real sales/revenue/refund/payment-method/product-trend reporting, cost-aware product-profit estimates and links into native order/payment screens; selectable Core modules fall back to native WooCommerce behavior where possible.
* Optional real unit-cost fields on products and variations for product-profit analysis; unknown costs are never guessed. Settings can also hold merchant-entered payment percentage/fixed fees, fulfillment cost and operating overhead per order to show a clearly labelled operating-profit estimate.
* Product variation option buttons, size-guide modal and order tracking.
* Optional WhatsApp button.
* Demo installer creates editable sample products, a working LUMA10 coupon and bundle defaults. Existing stock state is preserved where applicable; Luma does not invent scarcity. Countdown output appears only after a real future end date is configured.

== Shortcodes ==
[luma_sale_bar]
[luma_coupon]
[luma_shipping_meter]
[luma_countdown]
[luma_trust_bar]
[luma_size_guide]
[luma_recently_viewed]
[luma_recommendations]
[luma_wishlist]
[luma_compare]
[luma_saved_items]
[luma_bundle]
[luma_order_bump]

== Setup ==
1. Activate WooCommerce first.
2. Activate Luma Core.
3. Open the standalone Luma menu in the WordPress admin sidebar, then choose Dashboard.
4. Open Luma > Dashboard for store performance, then Luma > Settings to configure threshold, coupon, countdown, welcome offer, bundle SKUs, checkout bump and cart recommendations. Use the Core modules checkboxes to disable any Luma layer you do not need; the post-purchase layer can also be disabled.
5. Configure WooCommerce products, cross-sells, stock, policies and real unit costs before launch. Product cost can be entered in the product or variation pricing panel for the Luma product-profit estimate; gateway fees, tax, shipping overhead and unknown costs are excluded. The cart drawer lets guests save a cart line for later in the current session; signed-in customers have the same real product and variation selections synchronized to their account. Saved items can be moved back to the bag only when the real product remains public, purchasable and in stock. The [luma_saved_items] shortcode can be placed in an Elementor or WordPress page. The post-purchase recommendation section appears on an authorized order-received page and uses only that order's real line items, cross-sells and product categories.
6. Add the shortcodes to Elementor Shortcode widgets where needed. The Luma Commerce Block widget also exposes the reusable blocks.
7. Enable Campaign measurement only after reviewing the store's privacy notice. Luma then asks each visitor for consent before storing UTM source, medium, campaign and content or emitting non-personal dataLayer events; no email or customer profile is sent to analytics.
8. If Cart recovery is enabled by customer consent at checkout, use Luma > Cart recovery entries as a foundation for a merchant-approved email service or manual follow-up. Luma Core does not send automated recovery emails by itself.

== Notes ==
Wishlist, compare and recently viewed collections are device-local by default, so no customer account data is stored. Save for later is session-only for guests and stores only product IDs, variation selections and quantities in user meta for signed-in customers. WordPress privacy export and erasure tools include the signed-in saved list. Dashboard reports use a short cache and are invalidated when orders, refunds or products change; recent order links, selected-period CSV export, real low-stock alerts and coupon-usage reporting are available from Luma Dashboard. AJAX search and filters fail gracefully instead of leaving a stuck loading state. Leads, waitlist entries and recovery entries are stored only for the forms or explicit checkboxes that create them. Luma never fabricates activity, stock, sales or countdown claims. Online payments still require a verified gateway plugin and merchant account.

* Demo installer reuses matching demo products, avoids duplicate demo media and safely resolves duplicate product SKUs without taking down wp-admin.
* v1.30.0 fixes campaign attribution that never saved (the UTM cookie was sanitized before decoding, which stripped its percent-encoding), a fatal error on the offer popup when WooCommerce was deactivated, a TypeError when the collection sync endpoint received an array, ignored product_tag filters, stale pagination after an AJAX shop refresh, and literal "\n" text on the demo Sale page. It also registers privacy exporters and erasers for restock requests and newsletter sign-ups, styles the variation swatches it injects, clears expired countdown timers, and localizes every customer-facing string the JavaScript writes.

== Changelog ==

= 1.30.0 =
* Fixed UTM capture, which sanitized before decoding and accepted arbitrary parameter names.
* Guarded the offer and size-guide popups so they no longer fatal when WooCommerce is inactive.
* Registered Elementor widgets lazily so sites without Elementor are unaffected.
* Fixed AJAX filtering, which ignored the requested page; requests now carry paged and a validated base URL and swap in server-rendered pagination.
* Added a cart-availability check before reading order bumps.
* Used correct plural forms for review and cart counts.
* Restored product_tag filtering and made the on_sale filter work.
* Registered privacy exporters and erasers for waitlist and lead records and contributed privacy policy content.
* Escaped health-check output.
* Fixed demo content writing a literal backslash-n instead of a newline.
* Made core.js safe to include twice, removed .html() injection of server text, cleared expired countdown intervals and restored button labels after async actions.
* Added clipboard failure handling for browsers without navigator.clipboard.
* Made variation swatches keyboard accessible and added the stylesheet they were missing entirely.
* Made every plugin string translatable and shipped a generated .pot template in languages/.
* Declared Tested up to, Requires PHP and License URI in the plugin header.

= 1.29.0 =
* Added a full-height mobile filter drawer with a visible close control and a dimmed, keyboard-safe backdrop.
* Added focus placement on the drawer close control when it opens, Escape-to-close, and aria-expanded / aria-hidden state updates.
* Prevented page scroll while the mobile drawer is open.
* Added applied-filter chips for category, price bounds, stock, sale and attribute selections.
* Added individual chip removal and a Clear all action that immediately refreshes the AJAX result set.
* Added a polite live region to the result count and applied-filter summary so refreshed results are announced.

= 1.28.2 =
* Restored the missing PHP boundary before the footer function closing brace.
* Added a safe cart-availability check before reading the cart for the footer subtotal, preventing warnings in previews where the cart object is not initialised.
* Clipped page-level horizontal overflow without disabling inner comparison-table scrolling.
* Set min-width: 0 on grid children so long product names and controls cannot force the viewport wider.
