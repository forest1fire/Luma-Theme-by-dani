=== Luma ===
Contributors: codewithdani
Tags: woocommerce, elementor, fashion, ecommerce, accessibility-ready
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.33.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, original fashion storefront theme for the Luma brand, built by CodeWithDani for WordPress, WooCommerce and Elementor Pro.

== What is included ==
* Elementor Theme Builder locations for header, footer, single, archive and 404 templates.
* WooCommerce compatibility with responsive product grids and gallery support.
* No bundled fonts, external assets, page-builder lock-in or copied brand assets.
* Lightweight CSS-only decorative motion with reduced-motion support, editorial section decoration, purchase-friendly product cues based on real catalog data, homepage View Piece and safe Quick Add actions, shoppable recommendations, Complete the Look styling, stronger cart recommendation styling, hero preloading, below-fold rendering hints, SEO metadata fallback and configurable real social links without placeholder URLs.
* Mobile-first header, search panel, navigation and accessibility basics.
* Designed to work with the optional Luma Core companion plugin.
* Includes a ready homepage fallback, bold streetwear campaign assets and a responsive mega-menu fallback.
* Luma Core includes a one-click demo installer with editable pages, menus, categories, products and Media Library campaign images.
* The companion Demo Kit includes Elementor starter templates and an importable WooCommerce product CSV.

== Commercial build ==
Luma Core adds a complete sales layer rather than decorative-only features: first-visit welcome offer and email lead capture, product discovery, complete-the-look bundles, a checkout order bump, real stock urgency, waitlist, consent-gated campaign attribution, non-personal dataLayer events, order-aware post-purchase recommendations, account reorder links, a session-based and account-synchronized cart Save for later flow, a privacy-exportable saved-items list, a standalone Luma admin Dashboard with WooCommerce sales, payment, CSV export and cost-aware profit reporting, merchant-entered operating-cost estimates, and a consent-led cart-recovery foundation. Every customer-facing block remains editable through normal WooCommerce data, Luma Dashboard/Settings, WordPress or Elementor shortcodes.

== Installation ==
1. Upload and activate the theme from Appearance > Themes.
2. Install and activate WooCommerce.
3. Install Elementor and Elementor Pro.
4. Upload and activate the Luma Core companion plugin.
5. Create a static homepage and edit it in Elementor.
6. In Elementor > Theme Builder, create Header, Footer, Product Archive, Single Product and 404 templates, then assign display conditions.
7. Open the standalone Luma > Dashboard menu to set the offer, bundle, order bump, cart recommendation and campaign-measurement preferences.

== Important ==
This is an original design system inspired by modern fashion commerce patterns. Do not upload another brand's logo, photos, copy or proprietary code without permission. The default brand label is Luma. Replace it, logo and menu URLs with your final brand assets from the WordPress Customizer or Elementor.

== Plugin policy ==
Elementor Pro and payment-gateway plugins require their own valid licenses/accounts. This package does not bundle paid software.

* Works with the v1.27 safe demo installer SKU handling.
* v1.28.1 adds the Luma Control Center quick-action grid and real store-readiness checks.
* v1.28.2 adds a responsive overflow guard and min-width-safe grids to prevent horizontal page scrolling across desktop, mobile and Core storefront modules.
* v1.29.0 adds a proper skip link, focus-visible states, safer mobile navigation behavior, responsive WooCommerce cart/checkout/account styling and preview-safe cart handling.
* v1.30.0 adds complete WordPress editorial support: comments, author/date post headers, tags, post navigation, page links, search pagination and responsive article typography.
* v1.31.0 adds native Utility Menu and Footer Widgets extension points, fallback social metadata, product-review styling and print-friendly order/content surfaces.
* v1.32.0 adds a remembered grid/list product view switcher and a more useful 404 recovery path with shop and search actions.
* v1.33.0 fixes the duplicate WooCommerce content wrapper (two elements shared id="primary" and nested <main> landmarks), renders a heading on static pages, restores the post loop when the front page is set to latest posts, makes repeated search forms emit unique ids, corrects the luma-commerce text domain, announces bag and wish-list counts to assistive technology, and adds a light/dark color scheme, breadcrumbs with BreadcrumbList structured data, related posts, a reading progress bar and a back-to-top control.

== Changelog ==

= 1.33.0 =
* Fixed duplicate element ids in searchform.php by generating a unique id per render.
* Fixed the footer newsletter form, which posted to a missing handler and printed untranslated text.
* Added accessible names to header actions and kept bag and wish-list labels in step with their counts.
* Fixed the missing content wrapper in page.php, undefined variables in front-page.php and absent fallback markup in template-parts/content.php.
* Removed duplicate animation rules shared between theme.css and seo-motion.css.
* Fixed the mobile mega menu, which did not open, and added focus management on open and close.
* Stopped WooCommerce printing a second #primary and a nested main element inside the theme wrapper.
* Added a dark colour scheme with a merchant default, a visitor toggle, local persistence and an inline no-flash bootstrap.
* Added breadcrumbs with BreadcrumbList microdata to every template.
* Added related posts, reading progress, back-to-top and sticky-header auto-hide, each switchable in the Customizer.
* Made every theme string translatable and shipped a generated .pot template in languages/.
* Contributed privacy policy content.
* Declared Tested up to, Requires PHP and License URI in the theme header so the compatibility data WordPress reads is complete.

= 1.32.0 =
* Added an optional grid/list product view switcher to the native WooCommerce archive, with grid as the default.
* List view uses real WooCommerce product cards and persists only as a device-local preference.
* Product filters and Core AJAX refreshes keep working because the view class stays on the product list.
* Improved the 404 page with home, shop and search recovery actions.

= 1.31.0 =
* Added a WordPress Utility Menu location in the top utility bar, with the original Track order and Help links as a fallback.
* Added a Footer Widgets sidebar so standard widgets can extend the footer without editing theme files.
* Added fallback Open Graph and Twitter metadata when no SEO plugin is active; existing SEO plugins remain the source of truth.
* Added WooCommerce product review and review-form styling.
* Added print-friendly styles for order and content surfaces.

= 1.30.0 =
* Added a complete comments.php template with accessible author, date, moderation and reply output.
* Added comment form support while preserving WordPress consent fields.
* Added post author and date header, featured image, tags and post navigation.
* Added wp_link_pages() support for paginated posts and pages.
* Added search-result and index pagination.
* Added responsive editorial typography for blockquote, table, media and wide alignments.

= 1.29.0 =
* Added a keyboard-accessible Skip to content link and visible :focus-visible states.
* Improved mobile menu behaviour with outside-click close, Escape close, resize close and body scroll lock.
* Improved search panel behaviour with outside-click close and focus return on Escape.
* Added safer cart-count handling when WooCommerce has not initialised a cart object, including builder and preview contexts.
* Added responsive WooCommerce styling for cart, checkout, account navigation, notices, forms and tables.
* Added editor-content overflow protection for long text and embedded media.
