=== Luma Core ===
Contributors: codewithdani
Tags: woocommerce, sales, cart, search, wishlist, compare, conversion
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 1.30.0
License: GPLv2 or later

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
