# Luma Demo Kit

This kit is for a fresh WordPress + WooCommerce install.

## Fastest method
1. Install and activate `luma-commerce-theme.zip`.
2. Install WooCommerce, Elementor and Elementor Pro.
3. Install and activate `luma-commerce-core.zip`.
4. Open the standalone **Luma** menu in the WordPress admin sidebar, then choose **Dashboard**. The dashboard shows real WooCommerce revenue, order count, units sold, refunds, payment-method totals, recent sales trend, top products, recent order links, selected-period CSV export, low-stock alerts and coupon usage. It shows product-profit estimates only when real unit costs have been entered. Product cost fields are available in the WooCommerce product and variation pricing panels. Optional payment percentage/fixed fees, fulfillment cost and operating overhead can be entered in Luma > Settings for the operating-profit estimate.
5. Click **Install / update Luma demo store**.
6. Open Pages and edit any dummy page with Elementor.

The importer creates an editable store with original Luma campaign imagery, product-specific visuals, sample products, categories, pages, menus, a working LUMA10 coupon and a curated bundle. Demo products start in stock and remain editable; set a real product to out of stock if you want to test the waitlist flow. It does not fabricate views, sales, stock or countdowns. Campaign measurement is deliberately off until the merchant reviews privacy and consent requirements. Run the importer again to update the Luma demo SKUs instead of making duplicates.

## Sales-engine demonstrations
* Enable the welcome offer popup to test timed conversion, desktop exit intent, one-display-per-session behavior and lead capture.
* Submit the footer or popup email form, then review **Luma → Luma leads**.
* Add a product to the bag to test the real shipping meter and cart-aware cross-sell recommendations. Recommendation visibility, copy and card limit can be adjusted in Luma Control Center. Use **Save for later** on a cart line to move it into the current session list, remove it, or move it back only while the real product remains available. Signed-in customers keep those real saved product and variation selections synchronized to their account; guests remain session-only. The `[luma_saved_items]` shortcode can be placed on an Elementor page to show the saved list. WordPress privacy export and erasure tools cover the signed-in saved list. Then open a product page to test the editable complete-the-look bundle and its **Add all to bag** action.
* Complete a staging order, then view the authorized order-received page to test the post-purchase follow-up. It uses real purchased products and configured WooCommerce cross-sells/categories; it does not invent customer activity. The same order's account detail view also exposes real reorder links for available items.
* Visit checkout to test the customer-selected order bump.
* Enable Campaign measurement on staging to inspect non-personal `dataLayer` events and UTM order attribution. The storefront consent notice must be accepted before campaign data or events are recorded.
* Tick the checkout cart-reminder consent box, return to the checkout flow without ordering, then review **Luma → Luma cart recovery**. This is a foundation for a merchant-approved email provider; it does not send automated emails on its own.

## Pages created
* Home
* About Luma
* Shop
* Sale
* Wishlist
* Compare
* Recently Viewed
* Size Guide
* Contact
* Shipping & Returns
* FAQs
* Track Order
* Privacy Policy
* Terms & Conditions

## Elementor starter templates
The `assets/images/` folder contains the original Luma campaign and product visuals. The `elementor/` folder contains small starter templates. Import them from Elementor → Tools → Import/Export, or use the default theme homepage first and rebuild each section in Elementor. Luma Core shortcodes include `[luma_bundle]` and `[luma_order_bump]`, and the Elementor widget exposes both blocks.

## Before launch
Replace all demo product names, prices, stock, images, copy, policies, payment gateways and courier information. The fallback homepage uses lightweight editorial CSS decoration and real-product purchase cues, clear View Piece, safe Quick Add, shoppable recommendation and Complete the Look styling, respects reduced-motion preferences and preloads only its real hero image; review page titles, descriptions, social URLs and structured-data output alongside your chosen SEO plugin. Demo images are original campaign placeholders and are not product photography. Review Campaign measurement, email lead handling, recovery consent language and your privacy policy before selling live.
