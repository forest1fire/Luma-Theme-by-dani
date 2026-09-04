<?php
/**
 * Plugin Name: Luma Core
 * Plugin URI: https://example.com/luma-core
 * Description: Luma's custom WooCommerce sales engine: discovery, bundles, order bumps, order-aware merchandising, attribution, cart recovery foundation and conversion tools.
 * Version: 1.30.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: CodeWithDani
 * License: GPL-2.0-or-later
 * Text Domain: luma-commerce-core
 */

defined( 'ABSPATH' ) || exit;

define( 'LUMA_CORE_VERSION', '1.30.0' );
define( 'LUMA_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'LUMA_CORE_DIR', plugin_dir_path( __FILE__ ) );

function luma_core_option( $key, $fallback = '' ) {
    $options = get_option( 'luma_core_settings', array() );
    return isset( $options[ $key ] ) ? $options[ $key ] : $fallback;
}

function luma_core_cart_available() {
    return function_exists( 'WC' ) && WC() && isset( WC()->cart ) && is_object( WC()->cart );
}

function luma_core_session_available() {
    return function_exists( 'WC' ) && WC() && isset( WC()->session ) && is_object( WC()->session );
}

function luma_core_admin_notice() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-warning"><p><strong>Luma Core:</strong> WooCommerce is required for the shop, cart and conversion features.</p></div>';
    }
}
add_action( 'admin_notices', 'luma_core_admin_notice' );

function luma_core_analytics_consent_state() {
    if ( ! isset( $_COOKIE['luma_analytics_consent'] ) || ! is_scalar( $_COOKIE['luma_analytics_consent'] ) ) return '';
    $state = sanitize_key( wp_unslash( $_COOKIE['luma_analytics_consent'] ) );
    return in_array( $state, array( '1', '0' ), true ) ? $state : '';
}

function luma_core_has_analytics_consent() {
    return '1' === luma_core_analytics_consent_state();
}

function luma_core_public_product( $product_id ) {
    if ( ! function_exists( 'wc_get_product' ) ) return false;
    $product = wc_get_product( absint( $product_id ) );
    return ( $product && 'publish' === get_post_status( $product->get_id() ) ) ? $product : false;
}

function luma_core_coupon_available( $code ) {
    if ( '' === trim( (string) $code ) || ! function_exists( 'wc_get_coupon_id_by_code' ) ) return false;
    $coupon_id = wc_get_coupon_id_by_code( sanitize_text_field( $code ) );
    if ( ! $coupon_id || 'publish' !== get_post_status( $coupon_id ) ) return false;
    $coupon = function_exists( 'wc_get_coupon' ) ? wc_get_coupon( $coupon_id ) : new WC_Coupon( $coupon_id );
    if ( ! $coupon ) return false;
    $expires = $coupon->get_date_expires();
    if ( $expires && $expires->getTimestamp() <= current_time( 'timestamp' ) ) return false;
    $usage_limit = (int) $coupon->get_usage_limit();
    return ! $usage_limit || (int) $coupon->get_usage_count() < $usage_limit;
}

function luma_core_assets() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    wp_enqueue_style( 'luma-core', LUMA_CORE_URL . 'assets/css/core.css', array(), LUMA_CORE_VERSION );
    if ( is_rtl() ) wp_enqueue_style( 'luma-core-rtl', LUMA_CORE_URL . 'assets/css/rtl.css', array( 'luma-core' ), LUMA_CORE_VERSION );
    wp_enqueue_script( 'luma-core', LUMA_CORE_URL . 'assets/js/core.js', array( 'jquery' ), LUMA_CORE_VERSION, true );
    wp_localize_script( 'luma-core', 'lumaCore', array(
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'luma_core_nonce' ),
        'cartUrl'      => wc_get_cart_url(),
        'checkoutUrl'  => wc_get_checkout_url(),
        'shopUrl'      => wc_get_page_permalink( 'shop' ),
        'analyticsEnabled' => (bool) luma_core_option( 'analytics_enabled', false ),
        'analyticsConsent' => luma_core_has_analytics_consent(),
        'searchEnabled' => (bool) luma_core_option( 'module_search', true ),
        'account'      => array(
            'loggedIn' => is_user_logged_in(),
            'wishlist' => is_user_logged_in() ? array_map( 'strval', (array) get_user_meta( get_current_user_id(), 'luma_wishlist', true ) ) : array(),
            'compare'  => is_user_logged_in() ? array_map( 'strval', (array) get_user_meta( get_current_user_id(), 'luma_compare', true ) ) : array(),
        ),
        'i18n'         => array(
            'added'       => __( 'Added to bag', 'luma-commerce-core' ),
            'error'       => __( 'Please choose an option or try again.', 'luma-commerce-core' ),
            'searching'   => __( 'Searching…', 'luma-commerce-core' ),
            'noResults'   => __( 'No products found.', 'luma-commerce-core' ),
            'pieces'      => __( 'pieces', 'luma-commerce-core' ),
            'active'      => __( 'Active:', 'luma-commerce-core' ),
            'min'         => __( 'Min', 'luma-commerce-core' ),
            'max'         => __( 'Max', 'luma-commerce-core' ),
            'inStock'     => __( 'In stock', 'luma-commerce-core' ),
            'onSale'      => __( 'On sale', 'luma-commerce-core' ),
            'clearAll'    => __( 'Clear all', 'luma-commerce-core' ),
            'addToWishlist'    => __( 'Add to wish list', 'luma-commerce-core' ),
            'removeFromWishlist' => __( 'Remove from wish list', 'luma-commerce-core' ),
            'addToCompare'     => __( 'Add to compare', 'luma-commerce-core' ),
            'removeFromCompare' => __( 'Remove from compare', 'luma-commerce-core' ),
            'addedToBag'   => __( 'Added', 'luma-commerce-core' ),
            'applied'      => __( 'Applied', 'luma-commerce-core' ),
            'copied'       => __( 'Copied', 'luma-commerce-core' ),
            'checking'     => __( 'Checking…', 'luma-commerce-core' ),
            'saving'       => __( 'Saving…', 'luma-commerce-core' ),
            'adding'       => __( 'Adding…', 'luma-commerce-core' ),
            'removing'     => __( 'Removing…', 'luma-commerce-core' ),
            'addingBundle' => __( 'Adding the edit…', 'luma-commerce-core' ),
            'tryAgain'     => __( 'Please try again.', 'luma-commerce-core' ),
            'compareLimit' => __( 'Compare up to four pieces at a time.', 'luma-commerce-core' ),
            'choosePiece'  => __( 'Select at least one piece.', 'luma-commerce-core' ),
            'nothingSaved' => __( 'Nothing saved here yet.', 'luma-commerce-core' ),
            'saveFailed'   => __( 'This item could not be saved.', 'luma-commerce-core' ),
            'savedFailed'  => __( 'This saved item could not be updated.', 'luma-commerce-core' ),
            'orderNotFound' => __( 'We could not find that order.', 'luma-commerce-core' ),
            'shareText'    => __( 'Take a look at this piece.', 'luma-commerce-core' ),
            'close'        => __( 'Close', 'luma-commerce-core' ),
            'resultsCount' => __( '%d pieces', 'luma-commerce-core' ),
            'page'         => __( 'Page %d', 'luma-commerce-core' ),
            'chooseOption' => __( 'Choose an option', 'luma-commerce-core' ),
        ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'luma_core_assets' );

function luma_core_capture_campaign() {
    if ( is_admin() || ! luma_core_option( 'analytics_enabled', false ) || ! luma_core_has_analytics_consent() || headers_sent() ) return;
    $keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content' );
    $campaign = array();
    foreach ( $keys as $key ) if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) $campaign[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
    if ( ! $campaign ) return;
    setcookie( 'luma_utm', rawurlencode( wp_json_encode( $campaign ) ), time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
}
add_action( 'init', 'luma_core_capture_campaign', 1 );

function luma_core_order_attribution( $order ) {
    if ( ! luma_core_option( 'analytics_enabled', false ) || ! luma_core_has_analytics_consent() || empty( $_COOKIE['luma_utm'] ) ) return;
    /*
     * Decode first, sanitize second. sanitize_text_field() strips octets such
     * as %7B and %22, so running it over the still-encoded cookie destroyed
     * the JSON and no campaign was ever attached to an order.
     */
    $raw = is_scalar( $_COOKIE['luma_utm'] ) ? rawurldecode( wp_unslash( $_COOKIE['luma_utm'] ) ) : '';
    $campaign = $raw ? json_decode( $raw, true ) : null;
    if ( ! is_array( $campaign ) ) return;
    $allowed = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content' );
    $clean = array();
    foreach ( $allowed as $key ) {
        if ( isset( $campaign[ $key ] ) && is_scalar( $campaign[ $key ] ) ) {
            $value = sanitize_text_field( (string) $campaign[ $key ] );
            if ( '' !== $value ) $clean[ $key ] = substr( $value, 0, 120 );
        }
    }
    if ( $clean ) $order->update_meta_data( '_luma_campaign', $clean );
}
add_action( 'woocommerce_checkout_create_order', 'luma_core_order_attribution' );

function luma_core_purchase_event( $order_id ) {
    if ( ! luma_core_option( 'analytics_enabled', false ) || ! luma_core_has_analytics_consent() ) return;
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $items = array();
    foreach ( $order->get_items() as $item ) $items[] = array( 'item_id' => (string) ( $item->get_product_id() ? $item->get_product_id() : $item->get_variation_id() ), 'item_name' => $item->get_name(), 'quantity' => (int) $item->get_quantity(), 'price' => (float) $order->get_item_total( $item, false, false ) );
    $event = array( 'event' => 'purchase', 'transaction_id' => (string) $order->get_id(), 'value' => (float) $order->get_total(), 'currency' => $order->get_currency(), 'items' => $items );
    $event_key = 'lumaPurchase_' . (int) $order->get_id();
    echo '<script>(function(){var k=' . wp_json_encode( $event_key ) . ';try{if(sessionStorage.getItem(k))return;sessionStorage.setItem(k,"1");}catch(e){}window.dataLayer=window.dataLayer||[];window.dataLayer.push(' . wp_json_encode( $event ) . ');}());</script>';
}
add_action( 'woocommerce_thankyou', 'luma_core_purchase_event', 20 );

function luma_core_admin_menu() {
    add_menu_page( 'Luma Dashboard', 'Luma', 'manage_woocommerce', 'luma-control-center', 'luma_core_dashboard_page', 'dashicons-store', 56 );
    add_submenu_page( 'luma-control-center', 'Luma Dashboard', 'Dashboard', 'manage_woocommerce', 'luma-control-center', 'luma_core_dashboard_page' );
    add_submenu_page( 'luma-control-center', 'Luma Control Center Settings', 'Settings', 'manage_woocommerce', 'luma-control-settings', 'luma_core_settings_page' );
}
add_action( 'admin_menu', 'luma_core_admin_menu' );

function luma_core_admin_assets( $hook ) {
    if ( false === strpos( $hook, 'luma-control' ) ) return;
    wp_enqueue_style( 'luma-core-admin', LUMA_CORE_URL . 'assets/css/admin.css', array(), LUMA_CORE_VERSION );
}
add_action( 'admin_enqueue_scripts', 'luma_core_admin_assets' );

function luma_core_clear_dashboard_cache() {
    foreach ( array( 7, 30, 90, 365 ) as $days ) delete_transient( 'luma_dashboard_v3_' . $days );
}
add_action( 'woocommerce_new_order', 'luma_core_clear_dashboard_cache' );
add_action( 'woocommerce_order_status_changed', 'luma_core_clear_dashboard_cache' );
add_action( 'woocommerce_order_refunded', 'luma_core_clear_dashboard_cache' );
add_action( 'woocommerce_update_product', 'luma_core_clear_dashboard_cache' );
add_action( 'woocommerce_delete_product', 'luma_core_clear_dashboard_cache' );
add_action( 'update_option_luma_core_settings', 'luma_core_clear_dashboard_cache' );

function luma_core_register_settings() {
    register_setting( 'luma_core_settings_group', 'luma_core_settings', array( 'sanitize_callback' => 'luma_core_sanitize_settings', 'auth_callback' => function() { return current_user_can( 'manage_woocommerce' ); } ) );
}
add_action( 'admin_init', 'luma_core_register_settings' );

function luma_core_settings_capability() {
    return 'manage_woocommerce';
}
add_filter( 'option_page_capability_luma_core_settings_group', 'luma_core_settings_capability' );

function luma_core_sanitize_settings( $settings ) {
    $settings = is_array( $settings ) ? $settings : array();
    $module_keys = array( 'module_wishlist', 'module_compare', 'module_quick_view', 'module_cart_drawer', 'module_search', 'module_filters', 'module_sticky_atc', 'module_post_purchase' );
    $has_module_settings = ! empty( $settings['module_settings_present'] ) || (bool) array_intersect( $module_keys, array_keys( $settings ) );
    return array(
        'threshold'   => isset( $settings['threshold'] ) ? max( 0, (float) $settings['threshold'] ) : 4999,
        'sale_text'   => isset( $settings['sale_text'] ) ? sanitize_text_field( $settings['sale_text'] ) : 'Explore the current Luma edit',
        'meter_text'  => isset( $settings['meter_text'] ) ? sanitize_text_field( $settings['meter_text'] ) : 'You are {remaining} away from free delivery',
        'coupon_code' => isset( $settings['coupon_code'] ) ? sanitize_text_field( $settings['coupon_code'] ) : '',
        'coupon_text' => isset( $settings['coupon_text'] ) ? sanitize_text_field( $settings['coupon_text'] ) : 'Use code for an extra saving',
        'countdown'   => isset( $settings['countdown'] ) ? sanitize_text_field( $settings['countdown'] ) : '',
        'whatsapp'    => isset( $settings['whatsapp'] ) ? preg_replace( '/[^0-9+]/', '', $settings['whatsapp'] ) : '',
        'popup_enabled' => ! empty( $settings['popup_enabled'] ),
        'popup_title' => isset( $settings['popup_title'] ) ? sanitize_text_field( $settings['popup_title'] ) : 'Your first Luma move',
        'popup_text' => isset( $settings['popup_text'] ) ? sanitize_text_field( $settings['popup_text'] ) : 'Join the edit and unlock your first-order offer.',
        'popup_delay' => isset( $settings['popup_delay'] ) ? max( 0, min( 60, absint( $settings['popup_delay'] ) ) ) : 8,
        'module_wishlist' => ! $has_module_settings || ! empty( $settings['module_wishlist'] ),
        'module_compare' => ! $has_module_settings || ! empty( $settings['module_compare'] ),
        'module_quick_view' => ! $has_module_settings || ! empty( $settings['module_quick_view'] ),
        'module_cart_drawer' => ! $has_module_settings || ! empty( $settings['module_cart_drawer'] ),
        'module_search' => ! $has_module_settings || ! empty( $settings['module_search'] ),
        'module_filters' => ! $has_module_settings || ! empty( $settings['module_filters'] ),
        'module_sticky_atc' => ! $has_module_settings || ! empty( $settings['module_sticky_atc'] ),
        'module_post_purchase' => ! $has_module_settings || ! empty( $settings['module_post_purchase'] ),
        'analytics_enabled' => ! empty( $settings['analytics_enabled'] ),
        'bundle_enabled' => ! empty( $settings['bundle_enabled'] ),
        'bundle_skus' => isset( $settings['bundle_skus'] ) ? sanitize_text_field( $settings['bundle_skus'] ) : 'LUMA-DEMO-002, LUMA-DEMO-003, LUMA-DEMO-009',
        'bundle_title' => isset( $settings['bundle_title'] ) ? sanitize_text_field( $settings['bundle_title'] ) : 'Complete the look',
        'bundle_copy' => isset( $settings['bundle_copy'] ) ? sanitize_text_field( $settings['bundle_copy'] ) : 'The finishing pieces, selected for this edit.',
        'order_bump_enabled' => ! empty( $settings['order_bump_enabled'] ),
        'order_bump_sku' => isset( $settings['order_bump_sku'] ) ? sanitize_text_field( $settings['order_bump_sku'] ) : 'LUMA-DEMO-009',
        'order_bump_title' => isset( $settings['order_bump_title'] ) ? sanitize_text_field( $settings['order_bump_title'] ) : 'Add a finishing detail',
        'order_bump_copy' => isset( $settings['order_bump_copy'] ) ? sanitize_text_field( $settings['order_bump_copy'] ) : 'Complete your rotation with a small extra.',
        'cart_recommendations_enabled' => ! array_key_exists( 'cart_recommendations_enabled', $settings ) || ! empty( $settings['cart_recommendations_enabled'] ),
        'cart_recommendation_limit' => isset( $settings['cart_recommendation_limit'] ) ? max( 1, min( 4, absint( $settings['cart_recommendation_limit'] ) ) ) : 2,
        'cart_recommendation_kicker' => isset( $settings['cart_recommendation_kicker'] ) ? sanitize_text_field( $settings['cart_recommendation_kicker'] ) : 'The finishing pieces',
        'cart_recommendation_title' => isset( $settings['cart_recommendation_title'] ) ? sanitize_text_field( $settings['cart_recommendation_title'] ) : 'Complete your bag',
        'payment_fee_percent' => isset( $settings['payment_fee_percent'] ) ? max( 0, min( 100, (float) $settings['payment_fee_percent'] ) ) : 0,
        'payment_fee_fixed' => isset( $settings['payment_fee_fixed'] ) ? max( 0, (float) $settings['payment_fee_fixed'] ) : 0,
        'fulfillment_cost' => isset( $settings['fulfillment_cost'] ) ? max( 0, (float) $settings['fulfillment_cost'] ) : 0,
        'operating_overhead' => isset( $settings['operating_overhead'] ) ? max( 0, (float) $settings['operating_overhead'] ) : 0,
        'low_stock_threshold' => isset( $settings['low_stock_threshold'] ) ? max( 0, min( 100, absint( $settings['low_stock_threshold'] ) ) ) : 5,
    );
}

function luma_core_product_cost_field() {
    global $product_object;
    $value = $product_object ? $product_object->get_meta( '_luma_unit_cost', true ) : '';
    woocommerce_wp_text_input( array( 'id' => '_luma_unit_cost', 'label' => __( 'Luma unit cost', 'luma-commerce-core' ), 'value' => $value, 'type' => 'number', 'custom_attributes' => array( 'min' => '0', 'step' => '0.01' ), 'desc_tip' => true, 'description' => __( 'Optional cost used for the Luma product-profit estimate. Use your real unit cost; leave blank when it is unknown.', 'luma-commerce-core' ) ) );
}
add_action( 'woocommerce_product_options_pricing', 'luma_core_product_cost_field' );

function luma_core_save_product_cost( $product ) {
    if ( ! isset( $_POST['_luma_unit_cost'] ) || ! is_scalar( $_POST['_luma_unit_cost'] ) ) return;
    $raw = trim( (string) wp_unslash( $_POST['_luma_unit_cost'] ) );
    if ( '' === $raw ) $product->delete_meta_data( '_luma_unit_cost' ); else $product->update_meta_data( '_luma_unit_cost', wc_format_decimal( $raw ) );
}
add_action( 'woocommerce_admin_process_product_object', 'luma_core_save_product_cost' );

function luma_core_variation_cost_field( $loop, $variation_data, $variation ) {
    woocommerce_wp_text_input( array( 'id' => '_luma_unit_cost_' . $loop, 'name' => 'luma_unit_cost[' . $loop . ']', 'label' => __( 'Luma unit cost', 'luma-commerce-core' ), 'value' => $variation->get_meta( '_luma_unit_cost', true ), 'type' => 'number', 'custom_attributes' => array( 'min' => '0', 'step' => '0.01' ), 'wrapper_class' => 'form-row form-row-full', 'desc_tip' => true, 'description' => __( 'Optional real unit cost for profit reporting. Blank variations use the parent product cost.', 'luma-commerce-core' ) ) );
}
add_action( 'woocommerce_variation_options_pricing', 'luma_core_variation_cost_field', 10, 3 );

function luma_core_save_variation_cost( $variation_id, $index ) {
    if ( ! isset( $_POST['luma_unit_cost'][ $index ] ) || ! is_scalar( $_POST['luma_unit_cost'][ $index ] ) ) return;
    $raw = trim( (string) wp_unslash( $_POST['luma_unit_cost'][ $index ] ) );
    if ( '' === $raw ) delete_post_meta( $variation_id, '_luma_unit_cost' ); else update_post_meta( $variation_id, '_luma_unit_cost', wc_format_decimal( $raw ) );
}
add_action( 'woocommerce_save_product_variation', 'luma_core_save_variation_cost', 10, 2 );

function luma_core_product_unit_cost( $product ) {
    if ( ! $product ) return null;
    if ( method_exists( $product, 'get_cogs_value' ) ) {
        $cogs = $product->get_cogs_value();
        if ( is_numeric( $cogs ) && '' !== (string) $cogs ) return max( 0, (float) $cogs );
    }
    $value = $product->get_meta( '_luma_unit_cost', true );
    if ( is_numeric( $value ) && '' !== (string) $value ) return max( 0, (float) $value );
    if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
        $parent = wc_get_product( $product->get_parent_id() );
        if ( $parent ) {
            $value = $parent->get_meta( '_luma_unit_cost', true );
            if ( is_numeric( $value ) && '' !== (string) $value ) return max( 0, (float) $value );
        }
    }
    return null;
}

function luma_core_dashboard_low_stock() {
    if ( ! function_exists( 'wc_get_products' ) ) return array();
    $threshold = max( 0, min( 100, absint( luma_core_option( 'low_stock_threshold', 5 ) ) ) );
    $products = wc_get_products( array( 'status' => 'publish', 'limit' => 100, 'stock_status' => 'instock', 'meta_key' => '_stock', 'orderby' => 'meta_value_num', 'order' => 'ASC' ) );
    $items = array();
    foreach ( (array) $products as $product ) {
        if ( ! $product || ! $product->managing_stock() || null === $product->get_stock_quantity() ) continue;
        $quantity = (int) $product->get_stock_quantity();
        if ( $quantity < 0 || $quantity > $threshold || $product->backorders_allowed() ) continue;
        $items[] = array( 'name' => $product->get_name(), 'quantity' => $quantity, 'url' => get_edit_post_link( $product->get_id() ) ?: admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ) );
    }
    usort( $items, function( $a, $b ) { return $a['quantity'] <=> $b['quantity']; } );
    return array_slice( $items, 0, 8 );
}

function luma_core_dashboard_days() {
    $value = isset( $_GET['luma_range'] ) && is_scalar( $_GET['luma_range'] ) ? absint( $_GET['luma_range'] ) : 30;
    return in_array( $value, array( 7, 30, 90, 365 ), true ) ? $value : 30;
}

function luma_core_dashboard_orders( $days ) {
    if ( ! function_exists( 'wc_get_orders' ) ) return array();
    $args = array( 'limit' => 200, 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ), 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' );
    $timezone = wp_timezone();
    $after = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-' . absint( $days ) . ' days' )->format( 'Y-m-d H:i:s' );
    $args['date_created'] = '>=' . $after; $orders = array(); $page = 1;
    do {
        $args['paged'] = $page++; $batch = wc_get_orders( $args );
        if ( ! is_array( $batch ) || ! $batch ) break;
        $orders = array_merge( $orders, array_filter( $batch, 'is_object' ) );
    } while ( count( $batch ) === 200 );
    return $orders;
}

function luma_core_dashboard_metrics( $orders ) {
    $metrics = array( 'revenue' => 0, 'refunds' => 0, 'orders' => 0, 'items' => 0, 'tracked_items' => 0, 'missing_items' => 0, 'cost' => 0, 'profit' => 0, 'payments' => array(), 'products' => array(), 'daily' => array(), 'recent_orders' => array(), 'coupons' => array(), 'low_stock' => array(), 'estimated_expenses' => 0, 'operating_profit' => 0, 'expense_inputs' => array() );
    foreach ( $orders as $order ) {
        $total = (float) $order->get_total(); $refunded = (float) $order->get_total_refunded(); $net = max( 0, $total - $refunded );
        if ( count( $metrics['recent_orders'] ) < 8 ) $metrics['recent_orders'][] = array( 'number' => $order->get_order_number(), 'date' => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '', 'status' => wc_get_order_status_name( $order->get_status() ), 'customer' => method_exists( $order, 'get_formatted_billing_full_name' ) && $order->get_formatted_billing_full_name() ? $order->get_formatted_billing_full_name() : __( 'Guest customer', 'luma-commerce-core' ), 'payment' => $order->get_payment_method_title() ?: __( 'Unspecified', 'luma-commerce-core' ), 'total' => $net, 'url' => luma_core_dashboard_order_url( $order ) ); $metrics['orders']++; $metrics['revenue'] += $net; $metrics['refunds'] += max( 0, $refunded );
        $payment = $order->get_payment_method_title() ?: __( 'Unspecified', 'luma-commerce-core' );
        if ( ! isset( $metrics['payments'][ $payment ] ) ) $metrics['payments'][ $payment ] = array( 'orders' => 0, 'total' => 0 );
        $metrics['payments'][ $payment ]['orders']++; $metrics['payments'][ $payment ]['total'] += $net;
        foreach ( (array) $order->get_coupon_codes() as $coupon_code ) { $coupon_code = sanitize_text_field( $coupon_code ); if ( '' === $coupon_code ) continue; if ( ! isset( $metrics['coupons'][ $coupon_code ] ) ) $metrics['coupons'][ $coupon_code ] = 0; $metrics['coupons'][ $coupon_code]++; }
        $date = $order->get_date_created();
        if ( $date ) { $day = $date->setTimezone( wp_timezone() )->format( 'Y-m-d' ); if ( ! isset( $metrics['daily'][ $day ] ) ) $metrics['daily'][ $day ] = 0; $metrics['daily'][ $day ] += $net; }
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $quantity = max( 0, (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item( $item->get_id() ) );
            $line_revenue = max( 0, (float) $item->get_total() + (float) $order->get_total_refunded_for_item( $item->get_id() ) );
            $metrics['items'] += $quantity;
            $product = $item->get_product(); $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
            if ( ! isset( $metrics['products'][ $product_id ] ) ) $metrics['products'][ $product_id ] = array( 'name' => $item->get_name(), 'quantity' => 0, 'revenue' => 0 );
            $metrics['products'][ $product_id ]['quantity'] += $quantity; $metrics['products'][ $product_id ]['revenue'] += $line_revenue;
            $unit_cost = luma_core_product_unit_cost( $product );
            if ( null === $unit_cost ) $metrics['missing_items'] += $quantity; else { $metrics['tracked_items'] += $quantity; $metrics['cost'] += $unit_cost * $quantity; $metrics['profit'] += $line_revenue - ( $unit_cost * $quantity ); }
        }
    }
    $expense_inputs = array( 'payment_fee_percent' => max( 0, min( 100, (float) luma_core_option( 'payment_fee_percent', 0 ) ) ), 'payment_fee_fixed' => max( 0, (float) luma_core_option( 'payment_fee_fixed', 0 ) ), 'fulfillment_cost' => max( 0, (float) luma_core_option( 'fulfillment_cost', 0 ) ), 'operating_overhead' => max( 0, (float) luma_core_option( 'operating_overhead', 0 ) ) );
    $metrics['expense_inputs'] = $expense_inputs;
    $metrics['estimated_expenses'] = ( $metrics['revenue'] * $expense_inputs['payment_fee_percent'] / 100 ) + ( $metrics['orders'] * ( $expense_inputs['payment_fee_fixed'] + $expense_inputs['fulfillment_cost'] + $expense_inputs['operating_overhead'] ) );
    $metrics['operating_profit'] = $metrics['profit'] - $metrics['estimated_expenses'];
    $metrics['low_stock'] = luma_core_dashboard_low_stock();
    arsort( $metrics['coupons'] );
    uasort( $metrics['products'], function( $a, $b ) { return $b['revenue'] <=> $a['revenue']; } );
    uasort( $metrics['payments'], function( $a, $b ) { return $b['total'] <=> $a['total']; } );
    ksort( $metrics['daily'] );
    return $metrics;
}

function luma_core_dashboard_report( $days ) {
    $key = 'luma_dashboard_v3_' . absint( $days ); $metrics = get_transient( $key );
    if ( false === $metrics ) { $metrics = luma_core_dashboard_metrics( luma_core_dashboard_orders( $days ) ); set_transient( $key, $metrics, 5 * MINUTE_IN_SECONDS ); }
    return is_array( $metrics ) ? $metrics : luma_core_dashboard_metrics( array() );
}

function luma_core_dashboard_money( $amount ) {
    return function_exists( 'wc_price' ) ? wc_price( $amount ) : esc_html( number_format_i18n( (float) $amount, 2 ) );
}

function luma_core_dashboard_orders_url() {
    $order_util = 'Automattic\\WooCommerce\\Utilities\\OrderUtil';
    if ( class_exists( $order_util ) && method_exists( $order_util, 'custom_orders_table_usage_is_enabled' ) && call_user_func( array( $order_util, 'custom_orders_table_usage_is_enabled' ) ) ) return admin_url( 'admin.php?page=wc-orders' );
    return admin_url( 'edit.php?post_type=shop_order' );
}

function luma_core_dashboard_health() {
    $shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
    $published = wp_count_posts( 'product' );
    $published_count = $published && isset( $published->publish ) ? (int) $published->publish : 0;
    $gateways = array();
    if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'payment_gateways' ) ) {
        $payment_gateways = WC()->payment_gateways();
        if ( $payment_gateways && method_exists( $payment_gateways, 'get_available_payment_gateways' ) ) $gateways = (array) $payment_gateways->get_available_payment_gateways();
    }
    $zones = class_exists( 'WC_Shipping_Zones' ) ? (array) WC_Shipping_Zones::get_zones() : array();
    return array(
        array( 'label' => __( 'Shop page', 'luma-commerce-core' ), 'detail' => $shop_id && get_permalink( $shop_id ) ? __( 'Configured', 'luma-commerce-core' ) : __( 'Choose a shop page', 'luma-commerce-core' ), 'ready' => (bool) ( $shop_id && get_permalink( $shop_id ) ), 'url' => admin_url( 'admin.php?page=wc-settings&tab=products' ) ),
        array( 'label' => __( 'Payments', 'luma-commerce-core' ), 'detail' => $gateways ? sprintf( _n( '%d gateway available', '%d gateways available', count( $gateways ), 'luma-commerce-core' ), count( $gateways ) ) : __( 'Review payment setup', 'luma-commerce-core' ), 'ready' => (bool) $gateways, 'url' => admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ),
        array( 'label' => __( 'Shipping zones', 'luma-commerce-core' ), 'detail' => $zones ? sprintf( _n( '%d zone configured', '%d zones configured', count( $zones ), 'luma-commerce-core' ), count( $zones ) ) : __( 'Review shipping setup', 'luma-commerce-core' ), 'ready' => (bool) $zones, 'url' => admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ),
        array( 'label' => __( 'Published catalog', 'luma-commerce-core' ), 'detail' => sprintf( _n( '%d product published', '%d products published', $published_count, 'luma-commerce-core' ), $published_count ), 'ready' => $published_count > 0, 'url' => admin_url( 'edit.php?post_type=product' ) ),
        array( 'label' => __( 'Store connection', 'luma-commerce-core' ), 'detail' => is_ssl() ? __( 'HTTPS enabled', 'luma-commerce-core' ) : __( 'Use HTTPS before launch', 'luma-commerce-core' ), 'ready' => is_ssl(), 'url' => admin_url( 'options-general.php' ) ),
    );
}

function luma_core_dashboard_quick_links() {
    return array(
        array( 'label' => __( 'New product', 'luma-commerce-core' ), 'detail' => __( 'Add a catalog piece', 'luma-commerce-core' ), 'url' => admin_url( 'post-new.php?post_type=product' ) ),
        array( 'label' => __( 'Orders', 'luma-commerce-core' ), 'detail' => __( 'Fulfill and refund', 'luma-commerce-core' ), 'url' => luma_core_dashboard_orders_url() ),
        array( 'label' => __( 'Inventory', 'luma-commerce-core' ), 'detail' => __( 'Stock and variations', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=wc-admin&path=%2Fanalytics%2Fstock' ) ),
        array( 'label' => __( 'Coupons', 'luma-commerce-core' ), 'detail' => __( 'Offers and discount codes', 'luma-commerce-core' ), 'url' => admin_url( 'edit.php?post_type=shop_coupon' ) ),
        array( 'label' => __( 'Customers', 'luma-commerce-core' ), 'detail' => __( 'Orders and customer history', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=wc-admin&path=%2Fcustomers' ) ),
        array( 'label' => __( 'Analytics', 'luma-commerce-core' ), 'detail' => __( 'Sales and store reports', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=wc-admin&path=%2Fanalytics%2Foverview' ) ),
        array( 'label' => __( 'Payments', 'luma-commerce-core' ), 'detail' => __( 'Gateway configuration', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ),
        array( 'label' => __( 'Shipping', 'luma-commerce-core' ), 'detail' => __( 'Zones and delivery rates', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ),
        array( 'label' => __( 'Storefront', 'luma-commerce-core' ), 'detail' => __( 'Menus and pages', 'luma-commerce-core' ), 'url' => admin_url( 'nav-menus.php' ) ),
        array( 'label' => __( 'Design', 'luma-commerce-core' ), 'detail' => __( 'Customizer and Elementor', 'luma-commerce-core' ), 'url' => admin_url( 'customize.php' ) ),
        array( 'label' => __( 'Luma settings', 'luma-commerce-core' ), 'detail' => __( 'Features and merchandising', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=luma-control-settings' ) ),
        array( 'label' => __( 'Demo store', 'luma-commerce-core' ), 'detail' => __( 'Install or update demo', 'luma-commerce-core' ), 'url' => admin_url( 'admin.php?page=luma-control-settings#luma-demo-installer' ) ),
    );
}

function luma_core_export_dashboard_csv() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( esc_html__( 'You do not have permission to export this report.', 'luma-commerce-core' ) );
    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_orders' ) ) wp_die( esc_html__( 'WooCommerce is required to export this report.', 'luma-commerce-core' ) );
    check_admin_referer( 'luma_export_dashboard' );
    $days = luma_core_dashboard_days(); $orders = luma_core_dashboard_orders( $days );
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=luma-sales-' . absint( $days ) . '-days-' . gmdate( 'Y-m-d' ) . '.csv' );
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, array( 'Order', 'Date', 'Status', 'Payment method', 'Gross total', 'Refunded', 'Net revenue', 'Items' ) );
    foreach ( $orders as $order ) fputcsv( $output, array( $order->get_order_number(), $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '', wc_get_order_status_name( $order->get_status() ), $order->get_payment_method_title(), number_format( (float) $order->get_total(), 2, '.', '' ), number_format( (float) $order->get_total_refunded(), 2, '.', '' ), number_format( max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() ), 2, '.', '' ), $order->get_item_count() ) );
    fclose( $output );
    exit;
}
add_action( 'admin_post_luma_export_dashboard', 'luma_core_export_dashboard_csv' );

function luma_core_dashboard_order_url( $order ) {
    return $order && method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : luma_core_dashboard_orders_url();
}

function luma_core_dashboard_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( esc_html__( 'You do not have permission to view this page.', 'luma-commerce-core' ) );
    if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="wrap"><div class="notice notice-warning"><p>' . esc_html__( 'WooCommerce is required before Luma can calculate store analytics.', 'luma-commerce-core' ) . '</p></div></div>'; return; }
    $days = luma_core_dashboard_days(); $metrics = luma_core_dashboard_report( $days );
    $range_labels = array( 7 => __( 'Last 7 days', 'luma-commerce-core' ), 30 => __( 'Last 30 days', 'luma-commerce-core' ), 90 => __( 'Last 90 days', 'luma-commerce-core' ), 365 => __( 'Last 365 days', 'luma-commerce-core' ) );
    $aov = $metrics['orders'] ? $metrics['revenue'] / $metrics['orders'] : 0; $tracked_total = $metrics['tracked_items'] + $metrics['missing_items']; $coverage = $tracked_total ? round( ( $metrics['tracked_items'] / $tracked_total ) * 100 ) : 0;
    $chart_days = array(); $chart_span = min( 14, $days ); $today = new DateTimeImmutable( 'now', wp_timezone() );
    for ( $i = $chart_span - 1; $i >= 0; $i-- ) { $key = $today->modify( '-' . $i . ' days' )->format( 'Y-m-d' ); $chart_days[ $key ] = isset( $metrics['daily'][ $key ] ) ? $metrics['daily'][ $key ] : 0; }
    $chart_max = $chart_days ? max( $chart_days ) : 0;
    $quick_links = luma_core_dashboard_quick_links();
    $health_checks = luma_core_dashboard_health();
    ?>
    <div class="wrap luma-admin-page luma-dashboard"><div class="luma-dashboard__header"><div><p class="luma-admin-kicker">Luma Commerce</p><h1><?php esc_html_e( 'Dashboard', 'luma-commerce-core' ); ?></h1><p><?php esc_html_e( 'A clear view of your real WooCommerce store performance.', 'luma-commerce-core' ); ?></p></div><form method="get" class="luma-dashboard__range"><input type="hidden" name="page" value="luma-control-center"><label for="luma-range"><?php esc_html_e( 'Period', 'luma-commerce-core' ); ?></label><select id="luma-range" name="luma_range" onchange="this.form.submit()"><?php foreach ( $range_labels as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $days, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><noscript><button class="button" type="submit"><?php esc_html_e( 'Apply', 'luma-commerce-core' ); ?></button></noscript></form><div class="luma-dashboard__actions"><a class="button" href="<?php echo esc_url( luma_core_dashboard_orders_url() ); ?>"><?php esc_html_e( 'View orders', 'luma-commerce-core' ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'Payment settings', 'luma-commerce-core' ); ?></a><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'luma_export_dashboard', 'luma_range' => $days ), admin_url( 'admin-post.php' ) ), 'luma_export_dashboard' ) ); ?>"><?php esc_html_e( 'Export CSV', 'luma-commerce-core' ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=luma-control-settings' ) ); ?>"><?php esc_html_e( 'Luma settings', 'luma-commerce-core' ); ?></a></div></div>
    <section class="luma-dashboard-panel luma-dashboard-panel--wide luma-dashboard-quick-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Control center', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Essential controls', 'luma-commerce-core' ); ?></h2></div><small><?php esc_html_e( 'The daily store actions, in one place.', 'luma-commerce-core' ); ?></small></div><div class="luma-dashboard-quick-links"><?php foreach ( $quick_links as $quick_link ) : ?><a class="luma-dashboard-quick-link" href="<?php echo esc_url( $quick_link['url'] ); ?>"><strong><?php echo esc_html( $quick_link['label'] ); ?></strong><span><?php echo esc_html( $quick_link['detail'] ); ?></span><b aria-hidden="true">↗</b></a><?php endforeach; ?></div></section>
    <section class="luma-dashboard-panel luma-dashboard-panel--wide luma-dashboard-health-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Launch check', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Store readiness', 'luma-commerce-core' ); ?></h2></div><small><?php esc_html_e( 'Real configuration checks, not assumptions.', 'luma-commerce-core' ); ?></small></div><div class="luma-dashboard-health-grid"><?php foreach ( $health_checks as $health_check ) : ?><a class="luma-dashboard-health-item" href="<?php echo esc_url( $health_check['url'] ); ?>"><span class="luma-dashboard-health-icon <?php echo esc_attr( $health_check['ready'] ? 'is-ready' : 'needs-review' ); ?>" aria-hidden="true"><?php echo esc_html( $health_check['ready'] ? '✓' : '!' ); ?></span><span><strong><?php echo esc_html( $health_check['label'] ); ?></strong><small><?php echo esc_html( $health_check['detail'] ); ?></small></span><b aria-hidden="true">↗</b></a><?php endforeach; ?></div></section>
    <div class="luma-dashboard__cards"><article><span><?php esc_html_e( 'Net revenue', 'luma-commerce-core' ); ?></span><strong><?php echo wp_kses_post( luma_core_dashboard_money( $metrics['revenue'] ) ); ?></strong><small><?php echo esc_html( $range_labels[ $days ] ); ?></small></article><article><span><?php esc_html_e( 'Orders', 'luma-commerce-core' ); ?></span><strong><?php echo esc_html( number_format_i18n( $metrics['orders'] ) ); ?></strong><small><?php esc_html_e( 'Processing, completed and on-hold', 'luma-commerce-core' ); ?></small></article><article><span><?php esc_html_e( 'Units sold', 'luma-commerce-core' ); ?></span><strong><?php echo esc_html( number_format_i18n( $metrics['items'] ) ); ?></strong><small><?php esc_html_e( 'After recorded item refunds', 'luma-commerce-core' ); ?></small></article><article><span><?php esc_html_e( 'Average order value', 'luma-commerce-core' ); ?></span><strong><?php echo wp_kses_post( luma_core_dashboard_money( $aov ) ); ?></strong><small><?php esc_html_e( 'After recorded refunds', 'luma-commerce-core' ); ?></small></article><article><span><?php esc_html_e( 'Refunds', 'luma-commerce-core' ); ?></span><strong><?php echo wp_kses_post( luma_core_dashboard_money( $metrics['refunds'] ) ); ?></strong><small><?php esc_html_e( 'Recorded WooCommerce refunds', 'luma-commerce-core' ); ?></small></article></div>
    <div class="luma-dashboard__grid"><section class="luma-dashboard-panel luma-dashboard-panel--wide"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Recent rhythm', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Sales trend', 'luma-commerce-core' ); ?></h2></div><small><?php echo esc_html( sprintf( _n( 'Last %d day', 'Last %d days', $chart_span, 'luma-commerce-core' ), $chart_span ) ); ?></small></div><div class="luma-sales-chart" aria-label="<?php echo esc_attr( sprintf( _n( 'Sales for the last %d day', 'Sales for the last %d days', $chart_span, 'luma-commerce-core' ), $chart_span ) ); ?>"><?php foreach ( $chart_days as $date => $value ) : $height = $chart_max ? max( 4, round( ( $value / $chart_max ) * 100 ) ) : 4; ?><div class="luma-sales-chart__day"><div class="luma-sales-chart__bar" style="height:<?php echo esc_attr( $height ); ?>%" title="<?php echo esc_attr( $date . ': ' . wp_strip_all_tags( luma_core_dashboard_money( $value ) ) ); ?>"></div><small><?php echo esc_html( wp_date( 'd M', strtotime( $date ), wp_timezone() ) ); ?></small></div><?php endforeach; ?></div></section>
    <section class="luma-dashboard-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Collection', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Top products', 'luma-commerce-core' ); ?></h2></div></div><?php if ( $metrics['products'] ) : ?><table class="widefat striped luma-dashboard-table"><thead><tr><th><?php esc_html_e( 'Product', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Qty', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Revenue', 'luma-commerce-core' ); ?></th></tr></thead><tbody><?php $rank = 0; foreach ( $metrics['products'] as $product_id => $product_data ) : if ( $rank++ >= 5 ) break; ?><tr><td><?php echo esc_html( $product_data['name'] ); ?></td><td><?php echo esc_html( number_format_i18n( $product_data['quantity'] ) ); ?></td><td><?php echo wp_kses_post( luma_core_dashboard_money( $product_data['revenue'] ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="luma-dashboard-empty"><?php esc_html_e( 'No qualifying orders in this period.', 'luma-commerce-core' ); ?></p><?php endif; ?></section>
    <section class="luma-dashboard-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Payments', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Payment methods', 'luma-commerce-core' ); ?></h2></div></div><?php if ( $metrics['payments'] ) : ?><table class="widefat striped luma-dashboard-table"><thead><tr><th><?php esc_html_e( 'Method', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Orders', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Net', 'luma-commerce-core' ); ?></th></tr></thead><tbody><?php foreach ( $metrics['payments'] as $method => $payment_data ) : ?><tr><td><?php echo esc_html( $method ); ?></td><td><?php echo esc_html( number_format_i18n( $payment_data['orders'] ) ); ?></td><td><?php echo wp_kses_post( luma_core_dashboard_money( $payment_data['total'] ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="luma-dashboard-empty"><?php esc_html_e( 'No payment data in this period.', 'luma-commerce-core' ); ?></p><?php endif; ?></section>
    <section class="luma-dashboard-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Profit lens', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Profit analysis', 'luma-commerce-core' ); ?></h2></div></div><?php if ( $metrics['tracked_items'] ) : ?><div class="luma-dashboard-profit-grid"><div><span><?php esc_html_e( 'Product profit', 'luma-commerce-core' ); ?></span><strong><?php echo wp_kses_post( luma_core_dashboard_money( $metrics['profit'] ) ); ?></strong></div><div><span><?php esc_html_e( 'Operating estimate', 'luma-commerce-core' ); ?></span><strong><?php echo wp_kses_post( luma_core_dashboard_money( $metrics['operating_profit'] ) ); ?></strong></div></div><p class="luma-dashboard-note"><?php echo esc_html( sprintf( __( '%d%% of sold units have a real cost recorded.', 'luma-commerce-core' ), $coverage ) ); ?></p><p class="luma-dashboard-note"><?php echo esc_html( sprintf( __( 'Configured operating deductions: payment fees %s, fulfillment %s per order and overhead %s per order.', 'luma-commerce-core' ), $metrics['expense_inputs']['payment_fee_percent'] . '% + ' . wp_strip_all_tags( luma_core_dashboard_money( $metrics['expense_inputs']['payment_fee_fixed'] ) ) . '/order', wp_strip_all_tags( luma_core_dashboard_money( $metrics['expense_inputs']['fulfillment_cost'] ) ), wp_strip_all_tags( luma_core_dashboard_money( $metrics['expense_inputs']['operating_overhead'] ) ) ) ); ?></p><p class="luma-dashboard-note"><?php esc_html_e( 'These are merchant-entered estimates. Gateway fees, tax, shipping overhead or other costs not entered in Settings are excluded.', 'luma-commerce-core' ); ?></p><?php else : ?><strong class="luma-dashboard-profit">—</strong><p class="luma-dashboard-note"><?php esc_html_e( 'Add a real unit cost to products to calculate product and operating profit. Luma never estimates unknown costs.', 'luma-commerce-core' ); ?></p><?php endif; ?></section>
    <section class="luma-dashboard-panel luma-dashboard-panel--wide"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Operations', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Recent orders', 'luma-commerce-core' ); ?></h2></div><a class="button" href="<?php echo esc_url( luma_core_dashboard_orders_url() ); ?>"><?php esc_html_e( 'Open all orders', 'luma-commerce-core' ); ?></a></div><?php if ( $metrics['recent_orders'] ) : ?><table class="widefat striped luma-dashboard-table"><thead><tr><th><?php esc_html_e( 'Order', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Customer', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Payment', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Status', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Net', 'luma-commerce-core' ); ?></th></tr></thead><tbody><?php foreach ( $metrics['recent_orders'] as $recent ) : ?><tr><td><a href="<?php echo esc_url( $recent['url'] ); ?>">#<?php echo esc_html( $recent['number'] ); ?></a><small class="luma-dashboard-table__subline"><?php echo esc_html( $recent['date'] ); ?></small></td><td><?php echo esc_html( $recent['customer'] ); ?></td><td><?php echo esc_html( $recent['payment'] ); ?></td><td><?php echo esc_html( $recent['status'] ); ?></td><td><?php echo wp_kses_post( luma_core_dashboard_money( $recent['total'] ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="luma-dashboard-empty"><?php esc_html_e( 'No qualifying orders in this period.', 'luma-commerce-core' ); ?></p><?php endif; ?></section>
    <section class="luma-dashboard-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Inventory', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Low-stock alerts', 'luma-commerce-core' ); ?></h2></div><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Products', 'luma-commerce-core' ); ?></a></div><?php if ( $metrics['low_stock'] ) : ?><table class="widefat striped luma-dashboard-table"><thead><tr><th><?php esc_html_e( 'Product', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Units left', 'luma-commerce-core' ); ?></th></tr></thead><tbody><?php foreach ( $metrics['low_stock'] as $low_stock ) : ?><tr><td><a href="<?php echo esc_url( $low_stock['url'] ); ?>"><?php echo esc_html( $low_stock['name'] ); ?></a></td><td><strong><?php echo esc_html( number_format_i18n( $low_stock['quantity'] ) ); ?></strong></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="luma-dashboard-empty"><?php esc_html_e( 'No public managed-stock products are below the alert threshold.', 'luma-commerce-core' ); ?></p><?php endif; ?></section>
    <section class="luma-dashboard-panel"><div class="luma-dashboard-panel__heading"><div><p class="luma-admin-kicker"><?php esc_html_e( 'Promotions', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Coupon usage', 'luma-commerce-core' ); ?></h2></div></div><?php if ( $metrics['coupons'] ) : ?><table class="widefat striped luma-dashboard-table"><thead><tr><th><?php esc_html_e( 'Coupon', 'luma-commerce-core' ); ?></th><th><?php esc_html_e( 'Orders', 'luma-commerce-core' ); ?></th></tr></thead><tbody><?php foreach ( $metrics['coupons'] as $coupon_code => $usage ) : ?><tr><td><?php echo esc_html( $coupon_code ); ?></td><td><?php echo esc_html( number_format_i18n( $usage ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="luma-dashboard-empty"><?php esc_html_e( 'No coupon codes were used in this period.', 'luma-commerce-core' ); ?></p><?php endif; ?></section>
    </div><section class="luma-dashboard-panel luma-dashboard-panel--note"><strong><?php esc_html_e( 'Data note', 'luma-commerce-core' ); ?></strong><span><?php echo esc_html( sprintf( __( 'Figures use native WooCommerce orders from %s. Refunds are subtracted from net revenue. Cancelled, failed and pending orders are excluded.', 'luma-commerce-core' ), $range_labels[ $days ] ) ); ?></span><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=luma-control-settings' ) ); ?>"><?php esc_html_e( 'Open Luma settings', 'luma-commerce-core' ); ?></a></section></div>
    <?php
}

function luma_core_settings_page() {
    $settings = wp_parse_args( get_option( 'luma_core_settings', array() ), array(
        'threshold'   => 4999,
        'sale_text'   => 'Explore the current Luma edit',
        'meter_text'  => 'You are {remaining} away from free delivery',
        'coupon_code' => '',
        'coupon_text' => 'Use code for an extra saving',
        'countdown'   => '',
        'whatsapp'    => '',
        'popup_enabled' => false,
        'popup_title' => 'Your first Luma move',
        'popup_text' => 'Join the edit and unlock your first-order offer.',
        'popup_delay' => 8,
        'module_wishlist' => true,
        'module_compare' => true,
        'module_quick_view' => true,
        'module_cart_drawer' => true,
        'module_search' => true,
        'module_filters' => true,
        'module_sticky_atc' => true,
        'module_post_purchase' => true,
        'analytics_enabled' => false,
        'bundle_enabled' => true,
        'bundle_skus' => 'LUMA-DEMO-002, LUMA-DEMO-003, LUMA-DEMO-009',
        'bundle_title' => 'Complete the look',
        'bundle_copy' => 'The finishing pieces, selected for this edit.',
        'order_bump_enabled' => true,
        'order_bump_sku' => 'LUMA-DEMO-009',
        'order_bump_title' => 'Add a finishing detail',
        'order_bump_copy' => 'Complete your rotation with a small extra.',
        'cart_recommendations_enabled' => true,
        'cart_recommendation_limit' => 2,
        'cart_recommendation_kicker' => 'The finishing pieces',
        'cart_recommendation_title' => 'Complete your bag',
        'payment_fee_percent' => 0,
        'payment_fee_fixed' => 0,
        'fulfillment_cost' => 0,
        'operating_overhead' => 0,
        'low_stock_threshold' => 5,
    ) );
    ?>
    <div class="wrap"><h1>Luma Control Center</h1><p>Conversion tools built into Luma Core. Every banner can also be placed in Elementor using the shortcodes below.</p><form method="post" action="options.php">
    <?php settings_fields( 'luma_core_settings_group' ); ?><input type="hidden" name="luma_core_settings[module_settings_present]" value="1">
    <table class="form-table" role="presentation">
        <tr><th scope="row"><label for="luma-threshold">Free delivery threshold</label></th><td><input id="luma-threshold" name="luma_core_settings[threshold]" type="number" min="0" step="1" value="<?php echo esc_attr( $settings['threshold'] ); ?>" /> <span class="description">For example PKR 4999.</span></td></tr>
        <tr><th scope="row"><label for="luma-sale-text">Sale banner copy</label></th><td><input class="regular-text" id="luma-sale-text" name="luma_core_settings[sale_text]" type="text" value="<?php echo esc_attr( $settings['sale_text'] ); ?>" /></td></tr>
        <tr><th scope="row"><label for="luma-meter-text">Shipping meter copy</label></th><td><input class="regular-text" id="luma-meter-text" name="luma_core_settings[meter_text]" type="text" value="<?php echo esc_attr( $settings['meter_text'] ); ?>" /> <span class="description">Use {remaining} for the amount left.</span></td></tr>
        <tr><th scope="row"><label for="luma-coupon-code">Promo coupon code</label></th><td><input class="regular-text" id="luma-coupon-code" name="luma_core_settings[coupon_code]" type="text" value="<?php echo esc_attr( $settings['coupon_code'] ); ?>" /> <span class="description">Must exist in WooCommerce → Marketing → Coupons.</span></td></tr>
        <tr><th scope="row"><label for="luma-coupon-text">Promo coupon copy</label></th><td><input class="regular-text" id="luma-coupon-text" name="luma_core_settings[coupon_text]" type="text" value="<?php echo esc_attr( $settings['coupon_text'] ); ?>" /></td></tr>
        <tr><th scope="row"><label for="luma-countdown">Sale countdown end</label></th><td><input class="regular-text" id="luma-countdown" name="luma_core_settings[countdown]" type="text" value="<?php echo esc_attr( $settings['countdown'] ); ?>" placeholder="2026-12-31 23:59" /> <span class="description">Optional. Store timezone is used.</span></td></tr>
        <tr><th scope="row"><label for="luma-whatsapp">WhatsApp number</label></th><td><input class="regular-text" id="luma-whatsapp" name="luma_core_settings[whatsapp]" type="text" value="<?php echo esc_attr( $settings['whatsapp'] ); ?>" placeholder="923001234567" /> <span class="description">Optional. Adds a floating chat button.</span></td></tr>
        <tr><th scope="row">Core modules</th><td><?php foreach ( array( 'module_wishlist' => 'Wishlist', 'module_compare' => 'Compare', 'module_quick_view' => 'Quick View and Quick Add', 'module_cart_drawer' => 'AJAX Cart Drawer', 'module_search' => 'Predictive Search', 'module_filters' => 'Shop Filters', 'module_sticky_atc' => 'Sticky Add to Cart', 'module_post_purchase' => 'Post-purchase recommendations' ) as $module => $label ) : ?><label style="display:block;margin:4px 0"><input name="luma_core_settings[<?php echo esc_attr( $module ); ?>]" type="checkbox" value="1" <?php checked( $settings[ $module ], true ); ?> /> <?php echo esc_html( $label ); ?></label><?php endforeach; ?><p class="description">Disable modules you do not need; the rest of the store remains native WooCommerce.</p></td></tr>
        <tr><th scope="row">Welcome offer popup</th><td><label><input id="luma-popup-enabled" name="luma_core_settings[popup_enabled]" type="checkbox" value="1" <?php checked( $settings['popup_enabled'], true ); ?> /> Show a one-time new-customer offer.</label></td></tr>
        <tr><th scope="row"><label for="luma-popup-title">Popup heading</label></th><td><input class="regular-text" id="luma-popup-title" name="luma_core_settings[popup_title]" type="text" value="<?php echo esc_attr( $settings['popup_title'] ); ?>" /></td></tr>
        <tr><th scope="row"><label for="luma-popup-text">Popup message</label></th><td><input class="regular-text" id="luma-popup-text" name="luma_core_settings[popup_text]" type="text" value="<?php echo esc_attr( $settings['popup_text'] ); ?>" /></td></tr>
        <tr><th scope="row"><label for="luma-popup-delay">Popup delay</label></th><td><input id="luma-popup-delay" name="luma_core_settings[popup_delay]" type="number" min="0" max="60" value="<?php echo esc_attr( $settings['popup_delay'] ); ?>" /> <span class="description">Seconds; exit intent also works on desktop.</span></td></tr>
        <tr><th scope="row">Campaign measurement</th><td><label><input name="luma_core_settings[analytics_enabled]" type="checkbox" value="1" <?php checked( $settings['analytics_enabled'], true ); ?> /> Enable non-personal campaign attribution and dataLayer funnel events.</label><p class="description">A storefront consent notice appears first. Only UTM source, medium, campaign and content plus non-personal funnel events are stored; no email or customer profile is sent to analytics.</p></td></tr>
        <tr><th scope="row">Complete-the-look bundle</th><td><label><input name="luma_core_settings[bundle_enabled]" type="checkbox" value="1" <?php checked( $settings['bundle_enabled'], true ); ?> /> Show the curated add-all bundle on product pages.</label><br><label>Product SKUs <input class="regular-text" name="luma_core_settings[bundle_skus]" type="text" value="<?php echo esc_attr( $settings['bundle_skus'] ); ?>" /></label><p class="description">Comma-separated simple-product SKUs, editable in WooCommerce → Products.</p></td></tr>
        <tr><th scope="row"><label for="luma-bundle-title">Bundle heading</label></th><td><input class="regular-text" id="luma-bundle-title" name="luma_core_settings[bundle_title]" type="text" value="<?php echo esc_attr( $settings['bundle_title'] ); ?>" /><br><input class="regular-text" name="luma_core_settings[bundle_copy]" type="text" value="<?php echo esc_attr( $settings['bundle_copy'] ); ?>" /></td></tr>
        <tr><th scope="row">Checkout order bump</th><td><label><input name="luma_core_settings[order_bump_enabled]" type="checkbox" value="1" <?php checked( $settings['order_bump_enabled'], true ); ?> /> Offer one relevant add-on at checkout.</label><br><label>Product SKU <input name="luma_core_settings[order_bump_sku]" type="text" value="<?php echo esc_attr( $settings['order_bump_sku'] ); ?>" /></label><br><input class="regular-text" name="luma_core_settings[order_bump_title]" type="text" value="<?php echo esc_attr( $settings['order_bump_title'] ); ?>" /><br><input class="regular-text" name="luma_core_settings[order_bump_copy]" type="text" value="<?php echo esc_attr( $settings['order_bump_copy'] ); ?>" /><p class="description">Use a simple, in-stock product. The customer chooses; it is never added silently.</p></td></tr>
        <tr><th scope="row">Cart recommendations</th><td><input type="hidden" name="luma_core_settings[cart_recommendations_enabled]" value="0"><label><input name="luma_core_settings[cart_recommendations_enabled]" type="checkbox" value="1" <?php checked( $settings['cart_recommendations_enabled'], true ); ?> /> Show real cross-sell/category recommendations in the cart drawer.</label><br><label for="luma-cart-recommendation-limit">Maximum cards <input id="luma-cart-recommendation-limit" name="luma_core_settings[cart_recommendation_limit]" type="number" min="1" max="4" value="<?php echo esc_attr( $settings['cart_recommendation_limit'] ); ?>" /></label><br><input class="regular-text" name="luma_core_settings[cart_recommendation_kicker]" type="text" value="<?php echo esc_attr( $settings['cart_recommendation_kicker'] ); ?>" aria-label="Cart recommendation kicker" /><br><input class="regular-text" name="luma_core_settings[cart_recommendation_title]" type="text" value="<?php echo esc_attr( $settings['cart_recommendation_title'] ); ?>" aria-label="Cart recommendation title" /><p class="description">Native WooCommerce cross-sells are used first, with real same-category products as fallback. Products already in the cart are excluded.</p></td></tr>
        <tr><th scope="row">Profit inputs</th><td><label for="luma-payment-fee-percent">Payment fee percentage <input id="luma-payment-fee-percent" name="luma_core_settings[payment_fee_percent]" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( $settings['payment_fee_percent'] ); ?>" /> %</label><br><label for="luma-payment-fee-fixed">Payment fee per order <input id="luma-payment-fee-fixed" name="luma_core_settings[payment_fee_fixed]" type="number" min="0" step="0.01" value="<?php echo esc_attr( $settings['payment_fee_fixed'] ); ?>" /></label><br><label for="luma-fulfillment-cost">Fulfillment cost per order <input id="luma-fulfillment-cost" name="luma_core_settings[fulfillment_cost]" type="number" min="0" step="0.01" value="<?php echo esc_attr( $settings['fulfillment_cost'] ); ?>" /></label><br><label for="luma-operating-overhead">Other operating cost per order <input id="luma-operating-overhead" name="luma_core_settings[operating_overhead]" type="number" min="0" step="0.01" value="<?php echo esc_attr( $settings['operating_overhead'] ); ?>" /></label><p class="description">Optional real merchant inputs. These are subtracted only from the operating-profit estimate; taxes and unknown costs are not guessed.</p></td></tr>
        <tr><th scope="row"><label for="luma-low-stock-threshold">Low-stock alert</label></th><td><input id="luma-low-stock-threshold" name="luma_core_settings[low_stock_threshold]" type="number" min="0" max="100" step="1" value="<?php echo esc_attr( $settings['low_stock_threshold'] ); ?>" /> units or fewer <p class="description">The Luma Dashboard lists public, managed-stock products at or below this real inventory level. Backorders and products without managed stock are not listed.</p></td></tr>
    </table>
    <?php submit_button(); ?></form><hr><section id="luma-demo-installer" class="luma-demo-installer"><h2>One-click dummy store</h2><p>Creates editable sample products, categories, pages, menu items and a demo homepage setting. Running it again updates the Luma demo products instead of duplicating them.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="luma_install_demo"><input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'luma_install_demo' ) ); ?>"><?php submit_button( 'Install / update Luma demo store', 'secondary', 'submit', false ); ?></form></section><hr><p><strong>Elementor shortcodes:</strong> <code>[luma_sale_bar]</code> <code>[luma_coupon]</code> <code>[luma_shipping_meter]</code> <code>[luma_countdown]</code> <code>[luma_trust_bar]</code> <code>[luma_size_guide]</code> <code>[luma_recently_viewed]</code> <code>[luma_recommendations]</code> <code>[luma_wishlist]</code> <code>[luma_compare]</code> <code>[luma_saved_items]</code> <code>[luma_bundle]</code> <code>[luma_order_bump]</code></p></div>
    <?php
}

function luma_core_shop_url() {
    return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
}

function luma_core_sale_bar_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'text' => luma_core_option( 'sale_text', 'Explore the current Luma edit' ) ), $atts, 'luma_sale_bar' );
    $has_sale = function_exists( 'wc_get_product_ids_on_sale' ) && (bool) wc_get_product_ids_on_sale();
    if ( ! $has_sale && ! luma_core_coupon_available( luma_core_option( 'coupon_code', '' ) ) ) return '';
    return '<div class="luma-sale-bar"><span class="luma-sale-bar__dot"></span><strong>' . esc_html( $atts['text'] ) . '</strong><span>' . esc_html__( 'Current offers', 'luma-commerce-core' ) . '</span><a href="' . esc_url( luma_core_shop_url() ) . '">' . esc_html__( 'Shop the edit', 'luma-commerce-core' ) . ' ↗</a></div>';
}
add_shortcode( 'luma_sale_bar', 'luma_core_sale_bar_shortcode' );

function luma_core_coupon_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'code' => luma_core_option( 'coupon_code', '' ), 'text' => luma_core_option( 'coupon_text', 'Use code for an extra saving' ) ), $atts, 'luma_coupon' );
    if ( ! luma_core_coupon_available( $atts['code'] ) ) return '';
    return '<div class="luma-coupon-booster"><div><span class="luma-kicker">Private offer</span><strong>' . esc_html( $atts['text'] ) . '</strong></div><button class="luma-apply-coupon" type="button" data-coupon="' . esc_attr( $atts['code'] ) . '"><span class="luma-coupon-code">' . esc_html( $atts['code'] ) . '</span><span class="luma-coupon-action">Apply ↗</span></button><span class="luma-coupon-status" aria-live="polite"></span></div>';
}
add_shortcode( 'luma_coupon', 'luma_core_coupon_shortcode' );

function luma_core_shipping_meter_shortcode() {
    if ( ! luma_core_cart_available() ) return '';
    $threshold = (float) luma_core_option( 'threshold', 4999 );
    $subtotal  = (float) WC()->cart->get_cart_contents_total();
    if ( $threshold <= 0 ) return '';
    $progress  = min( 100, ( $subtotal / $threshold ) * 100 );
    $remaining = max( 0, $threshold - $subtotal );
    $text = $remaining > 0 ? str_replace( '{remaining}', wp_strip_all_tags( wc_price( $remaining ) ), luma_core_option( 'meter_text', 'You are {remaining} away from free delivery' ) ) : __( 'You unlocked free delivery', 'luma-commerce-core' );
    return '<div class="luma-shipping-meter"><div class="luma-shipping-meter__copy"><span>' . esc_html( $text ) . '</span><strong>' . esc_html( wp_strip_all_tags( wc_price( $threshold ) ) ) . '</strong></div><div class="luma-shipping-meter__track"><span style="width:' . esc_attr( $progress ) . '%"></span></div></div>';
}
add_shortcode( 'luma_shipping_meter', 'luma_core_shipping_meter_shortcode' );

function luma_core_countdown_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'end' => luma_core_option( 'countdown', '' ), 'label' => __( 'Offer ends in', 'luma-commerce-core' ) ), $atts, 'luma_countdown' );
    $end = trim( (string) $atts['end'] );
    if ( '' === $end ) return '';
    $date = DateTime::createFromFormat( '!Y-m-d H:i', $end, wp_timezone() );
    if ( ! $date || $date->format( 'Y-m-d H:i' ) !== $end || $date->getTimestamp() <= current_time( 'timestamp' ) ) return '';
    return '<div class="luma-countdown" data-end="' . esc_attr( $date->format( DATE_ATOM ) ) . '"><span class="luma-countdown__label">' . esc_html( $atts['label'] ) . '</span><span class="luma-countdown__time"><b data-unit="days">00</b><i>d</i><b data-unit="hours">00</b><i>h</i><b data-unit="minutes">00</b><i>m</i><b data-unit="seconds">00</b><i>s</i></span></div>';
}
add_shortcode( 'luma_countdown', 'luma_core_countdown_shortcode' );

function luma_core_trust_bar_shortcode() {
    $items = array();
    if ( get_page_by_path( 'shipping-returns', OBJECT, 'page' ) ) $items[] = array( 'icon' => '↻', 'title' => __( 'Shipping & returns', 'luma-commerce-core' ), 'copy' => __( 'See store policy', 'luma-commerce-core' ) );
    if ( is_ssl() ) $items[] = array( 'icon' => '▣', 'title' => __( 'Secure checkout', 'luma-commerce-core' ), 'copy' => __( 'Protected connection', 'luma-commerce-core' ) );
    $gateways = array();
    if ( function_exists( 'WC' ) && WC() && WC()->payment_gateways() ) $gateways = WC()->payment_gateways()->get_available_payment_gateways();
    if ( isset( $gateways['cod'] ) ) $items[] = array( 'icon' => '⌁', 'title' => __( 'Cash on delivery', 'luma-commerce-core' ), 'copy' => __( 'Pay on delivery', 'luma-commerce-core' ) );
    if ( luma_core_option( 'whatsapp', '' ) ) $items[] = array( 'icon' => '✦', 'title' => __( 'WhatsApp support', 'luma-commerce-core' ), 'copy' => __( 'Message the store', 'luma-commerce-core' ) );
    if ( ! $items ) return '';
    $html = '<div class="luma-trust-bar">';
    foreach ( $items as $item ) $html .= '<div><b aria-hidden="true">' . esc_html( $item['icon'] ) . '</b><strong>' . esc_html( $item['title'] ) . '</strong><span>' . esc_html( $item['copy'] ) . '</span></div>';
    return $html . '</div>';
}
add_shortcode( 'luma_trust_bar', 'luma_core_trust_bar_shortcode' );

function luma_core_size_guide_shortcode() {
    ob_start(); ?>
    <div class="luma-size-guide"><button class="luma-size-guide__trigger" type="button" aria-expanded="false"><?php esc_html_e( 'Size guide', 'luma-commerce-core' ); ?> <span>↗</span></button><div class="luma-size-guide__modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Size guide', 'luma-commerce-core' ); ?>" hidden><div class="luma-size-guide__backdrop"></div><div class="luma-size-guide__dialog"><button class="luma-size-guide__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'luma-commerce-core' ); ?>">×</button><p class="luma-kicker"><?php esc_html_e( 'Find your fit', 'luma-commerce-core' ); ?></p><h2><?php esc_html_e( 'Luma size guide', 'luma-commerce-core' ); ?></h2><p><?php esc_html_e( 'Use your usual size. For an oversized fit, move up one size.', 'luma-commerce-core' ); ?></p><table><thead><tr><th>Size</th><th>Chest</th><th>Waist</th><th>Hip</th></tr></thead><tbody><tr><td>XS</td><td>34–36</td><td>28–30</td><td>34–36</td></tr><tr><td>S</td><td>36–38</td><td>30–32</td><td>36–38</td></tr><tr><td>M</td><td>38–40</td><td>32–34</td><td>38–40</td></tr><tr><td>L</td><td>40–42</td><td>34–36</td><td>40–42</td></tr><tr><td>XL</td><td>42–44</td><td>36–38</td><td>42–44</td></tr></tbody></table><small><?php esc_html_e( 'Measurements shown in inches. Always check the product description for a specific fit note.', 'luma-commerce-core' ); ?></small></div></div></div>
    <?php return ob_get_clean();
}
add_shortcode( 'luma_size_guide', 'luma_core_size_guide_shortcode' );

function luma_core_product_mini_card( $product ) {
    if ( ! $product ) return '';
    $actions = '<div class="luma-mini-product__actions">';
    if ( luma_core_option( 'module_wishlist', true ) ) $actions .= '<button class="luma-wishlist-toggle" type="button" data-wishlist-id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr__( 'Add to wish list', 'luma-commerce-core' ) . '" aria-pressed="false">♡</button>';
    if ( luma_core_option( 'module_compare', true ) ) $actions .= '<button class="luma-compare-toggle" type="button" data-compare-id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr__( 'Add to compare', 'luma-commerce-core' ) . '" aria-pressed="false">' . esc_html__( 'Compare', 'luma-commerce-core' ) . '</button>';
    $actions .= '</div>';
    return '<article class="luma-mini-product" data-product-id="' . esc_attr( $product->get_id() ) . '"><a href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</a><div><h3><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3><span>' . wp_kses_post( $product->get_price_html() ) . '</span>' . $actions . '</div></article>';
}

function luma_core_collection_shortcode( $type ) {
    return '<section class="luma-local-collection luma-local-collection--' . esc_attr( $type ) . '" data-luma-collection="' . esc_attr( $type ) . '"><div class="luma-collection-heading"><p class="luma-kicker">' . esc_html__( 'Your edit', 'luma-commerce-core' ) . '</p><h2>' . esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ) . '</h2></div><div class="luma-collection-grid" data-luma-collection-grid><span class="luma-collection-loading">' . esc_html__( 'Loading your edit…', 'luma-commerce-core' ) . '</span></div></section>';
}
function luma_core_wishlist_shortcode() { return luma_core_option( 'module_wishlist', true ) ? luma_core_collection_shortcode( 'wishlist' ) : ''; }
function luma_core_compare_shortcode() { return luma_core_option( 'module_compare', true ) ? luma_core_collection_shortcode( 'compare' ) : ''; }
function luma_core_recently_viewed_shortcode() { return luma_core_collection_shortcode( 'recently_viewed' ); }
add_shortcode( 'luma_wishlist', 'luma_core_wishlist_shortcode' );
add_shortcode( 'luma_compare', 'luma_core_compare_shortcode' );
add_shortcode( 'luma_recently_viewed', 'luma_core_recently_viewed_shortcode' );

function luma_core_loop_actions() {
    global $product;
    if ( ! $product ) return;
    $id = $product->get_id();
    echo '<div class="luma-loop-actions">';
    if ( luma_core_option( 'module_wishlist', true ) ) echo '<button class="luma-wishlist-toggle" type="button" data-wishlist-id="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Add to wish list', 'luma-commerce-core' ) . '" aria-pressed="false">♡</button>';
    if ( luma_core_option( 'module_compare', true ) ) echo '<button class="luma-compare-toggle" type="button" data-compare-id="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Add to compare', 'luma-commerce-core' ) . '" aria-pressed="false">' . esc_html__( 'Compare', 'luma-commerce-core' ) . '</button>';
    if ( luma_core_option( 'module_quick_view', true ) ) { echo '<button class="luma-quick-view" type="button" data-product-id="' . esc_attr( $id ) . '">' . esc_html__( 'Quick view', 'luma-commerce-core' ) . '</button>'; if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) echo '<button class="luma-quick-add" type="button" data-product-id="' . esc_attr( $id ) . '">' . esc_html__( 'Quick add', 'luma-commerce-core' ) . ' <span>+</span></button>'; }
    echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item', 'luma_core_loop_actions', 12 );

function luma_core_sticky_atc() {
    global $product;
    if ( ! luma_core_option( 'module_sticky_atc', true ) || ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) return;
    echo '<div class="luma-sticky-atc"><div><small>' . esc_html( $product->get_name() ) . '</small><strong>' . wp_kses_post( $product->get_price_html() ) . '</strong></div><div class="luma-sticky-atc__actions"><button type="button" class="luma-sticky-atc__button">' . esc_html__( 'Add to bag', 'luma-commerce-core' ) . ' <span>↗</span></button><button type="button" class="luma-sticky-buy-now">' . esc_html__( 'Buy now', 'luma-commerce-core' ) . ' <span>↗</span></button></div></div>';
}
add_action( 'woocommerce_after_add_to_cart_form', 'luma_core_sticky_atc', 20 );

function luma_core_buy_now_button() {
    global $product;
    if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) return;
    echo '<button type="button" class="luma-buy-now">' . esc_html__( 'Buy now', 'luma-commerce-core' ) . ' <span>↗</span></button><button type="button" class="luma-share-product" data-share-title="' . esc_attr( $product->get_name() ) . '">' . esc_html__( 'Share', 'luma-commerce-core' ) . ' <span>↗</span></button>';
}
add_action( 'woocommerce_after_add_to_cart_button', 'luma_core_buy_now_button', 15 );

function luma_core_buy_now_redirect( $url ) {
    return ! empty( $_REQUEST['luma_buy_now'] ) ? wc_get_checkout_url() : $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'luma_core_buy_now_redirect' );

function luma_core_product_signals() {
    global $product;
    if ( ! $product ) return;
    $stock = $product->managing_stock() ? (int) $product->get_stock_quantity() : 0;
    $sales = (int) $product->get_total_sales();
    $signals = array();
    if ( $stock > 0 && $stock <= 8 && ! $product->backorders_allowed() ) $signals[] = '<span class="luma-signal luma-signal--urgent">' . sprintf( esc_html__( 'Only %d left', 'luma-commerce-core' ), $stock ) . '</span>';
    elseif ( $product->is_in_stock() ) $signals[] = '<span class="luma-signal">' . esc_html__( 'In stock · ready to ship', 'luma-commerce-core' ) . '</span>';
    if ( $sales > 0 ) $signals[] = '<span class="luma-signal">' . esc_html__( 'Popular pick', 'luma-commerce-core' ) . '</span>';
    $reassurance = array();
    if ( function_exists( 'WC' ) && WC() && WC()->payment_gateways() ) { $gateways = WC()->payment_gateways()->get_available_payment_gateways(); if ( isset( $gateways['cod'] ) ) $reassurance[] = __( 'Cash on delivery', 'luma-commerce-core' ); }
    if ( get_page_by_path( 'shipping-returns', OBJECT, 'page' ) ) $reassurance[] = __( 'Shipping & returns', 'luma-commerce-core' );
    if ( is_ssl() ) $reassurance[] = __( 'Secure checkout', 'luma-commerce-core' );
    if ( luma_core_option( 'whatsapp', '' ) ) $reassurance[] = __( 'WhatsApp support', 'luma-commerce-core' );
    echo $signals ? '<div class="luma-product-signals">' . implode( '', $signals ) . '</div>' : '';
    if ( $reassurance ) echo '<div class="luma-product-reassurance">' . implode( '', array_map( function( $text ) { return '<span>' . esc_html( $text ) . '</span>'; }, $reassurance ) ) . '</div>';
}
add_action( 'woocommerce_single_product_summary', 'luma_core_product_signals', 12 );

function luma_core_register_waitlist_post_type() {
    register_post_type( 'luma_waitlist', array( 'labels' => array( 'name' => 'Luma waitlist', 'singular_name' => 'Waitlist signup' ), 'public' => false, 'show_ui' => true, 'show_in_menu' => 'luma-control-center', 'supports' => array( 'title' ) ) );
}
add_action( 'init', 'luma_core_register_waitlist_post_type' );

function luma_core_waitlist_columns( $columns ) {
    return array( 'cb' => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">', 'title' => 'Product / customer', 'luma_waitlist_email' => 'Email', 'luma_waitlist_status' => 'Status', 'date' => 'Requested' );
}
add_filter( 'manage_luma_waitlist_posts_columns', 'luma_core_waitlist_columns' );

function luma_core_waitlist_column_content( $column, $post_id ) {
    if ( 'luma_waitlist_email' === $column ) echo esc_html( get_post_meta( $post_id, '_luma_waitlist_email', true ) );
    if ( 'luma_waitlist_status' === $column ) echo esc_html( ucfirst( get_post_meta( $post_id, '_luma_waitlist_status', true ) ?: 'open' ) );
}
add_action( 'manage_luma_waitlist_posts_custom_column', 'luma_core_waitlist_column_content', 10, 2 );

function luma_core_register_lead_post_type() {
    register_post_type( 'luma_lead', array( 'labels' => array( 'name' => 'Luma leads', 'singular_name' => 'Luma lead' ), 'public' => false, 'show_ui' => true, 'show_in_menu' => 'luma-control-center', 'supports' => array( 'title' ) ) );
}
add_action( 'init', 'luma_core_register_lead_post_type' );

function luma_core_register_recovery_post_type() {
    register_post_type( 'luma_recovery', array( 'labels' => array( 'name' => 'Luma cart recovery', 'singular_name' => 'Cart recovery' ), 'public' => false, 'show_ui' => true, 'show_in_menu' => 'luma-control-center', 'supports' => array( 'title' ) ) );
}
add_action( 'init', 'luma_core_register_recovery_post_type' );

function luma_core_recovery_columns( $columns ) {
    return array( 'cb' => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">', 'title' => 'Customer', 'luma_recovery_email' => 'Email', 'luma_recovery_status' => 'Status', 'luma_recovery_last_seen' => 'Last seen', 'date' => 'Created' );
}
add_filter( 'manage_luma_recovery_posts_columns', 'luma_core_recovery_columns' );

function luma_core_recovery_column_content( $column, $post_id ) {
    if ( 'luma_recovery_email' === $column ) echo esc_html( get_post_meta( $post_id, '_luma_recovery_email', true ) );
    if ( 'luma_recovery_status' === $column ) echo esc_html( ucfirst( get_post_meta( $post_id, '_luma_recovery_status', true ) ?: 'open' ) );
    if ( 'luma_recovery_last_seen' === $column ) echo esc_html( get_post_meta( $post_id, '_luma_recovery_last_seen', true ) );
}
add_action( 'manage_luma_recovery_posts_custom_column', 'luma_core_recovery_column_content', 10, 2 );

function luma_core_recovery_checkout_field() {
    if ( ! luma_core_cart_available() || WC()->cart->is_empty() ) return;
    echo '<p class="form-row luma-recovery-consent"><label><input type="checkbox" name="luma_recovery_consent" value="1"> Email me a reminder about this bag if I do not complete checkout.</label><span>Only used for this reminder.</span></p>';
}
add_action( 'woocommerce_after_order_notes', 'luma_core_recovery_checkout_field' );

function luma_core_save_recovery( $email ) {
    if ( ! luma_core_cart_available() || WC()->cart->is_empty() || ! is_email( $email ) ) return;
    $items = array();
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $item = isset( $cart_item['data'] ) ? $cart_item['data'] : false;
        if ( $item && $item->exists() ) $items[] = array( 'product_id' => $item->get_id(), 'name' => $item->get_name(), 'quantity' => (int) $cart_item['quantity'], 'price' => (float) $item->get_price() );
    }
    if ( ! $items ) return;
    $existing = get_posts( array( 'post_type' => 'luma_recovery', 'post_status' => 'private', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_luma_recovery_email', 'value' => $email ), array( 'key' => '_luma_recovery_status', 'value' => 'open' ) ), 'fields' => 'ids' ) );
    $recovery_id = $existing ? (int) $existing[0] : wp_insert_post( array( 'post_type' => 'luma_recovery', 'post_status' => 'private', 'post_title' => $email ) );
    if ( $recovery_id ) { update_post_meta( $recovery_id, '_luma_recovery_email', $email ); update_post_meta( $recovery_id, '_luma_recovery_cart', $items ); update_post_meta( $recovery_id, '_luma_recovery_status', 'open' ); update_post_meta( $recovery_id, '_luma_recovery_last_seen', current_time( 'mysql' ) ); }
}

function luma_core_capture_recovery_checkout( $post_data ) {
    parse_str( (string) $post_data, $data );
    $email = ! empty( $data['billing_email'] ) ? sanitize_email( $data['billing_email'] ) : '';
    if ( ! is_email( $email ) ) return;
    if ( ! empty( $data['luma_recovery_consent'] ) ) {
        luma_core_save_recovery( $email );
    } else {
        $entries = get_posts( array( 'post_type' => 'luma_recovery', 'post_status' => 'private', 'posts_per_page' => 10, 'meta_query' => array( array( 'key' => '_luma_recovery_email', 'value' => $email ), array( 'key' => '_luma_recovery_status', 'value' => 'open' ) ), 'fields' => 'ids' ) );
        foreach ( $entries as $entry_id ) update_post_meta( $entry_id, '_luma_recovery_status', 'revoked' );
    }
}
add_action( 'woocommerce_checkout_update_order_review', 'luma_core_capture_recovery_checkout' );

function luma_core_recovery_order_meta( $order ) {
    if ( ! empty( $_POST['luma_recovery_consent'] ) ) { $email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : ''; if ( is_email( $email ) ) { $order->update_meta_data( '_luma_recovery_consent', 'yes' ); $order->update_meta_data( '_luma_recovery_email', $email ); } }
}
add_action( 'woocommerce_checkout_create_order', 'luma_core_recovery_order_meta' );

function luma_core_close_recovery( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $email = $order->get_billing_email();
    if ( ! is_email( $email ) ) return;
    $entries = get_posts( array( 'post_type' => 'luma_recovery', 'post_status' => 'private', 'posts_per_page' => 10, 'meta_query' => array( array( 'key' => '_luma_recovery_email', 'value' => $email ), array( 'key' => '_luma_recovery_status', 'value' => 'open' ) ), 'fields' => 'ids' ) );
    foreach ( $entries as $entry_id ) { update_post_meta( $entry_id, '_luma_recovery_status', 'converted' ); update_post_meta( $entry_id, '_luma_recovery_order', $order_id ); }
}
add_action( 'woocommerce_thankyou', 'luma_core_close_recovery', 8 );

function luma_core_waitlist_form() {
    global $product;
    if ( ! $product || $product->is_in_stock() ) return;
    echo '<div class="luma-waitlist"><p class="luma-kicker">Missed this one?</p><h3>Get the restock note.</h3><p>Leave your email and we will notify you when this piece returns.</p><form data-luma-waitlist><input type="hidden" name="product_id" value="' . esc_attr( $product->get_id() ) . '"><label class="screen-reader-text" for="luma-waitlist-email">Email address</label><input id="luma-waitlist-email" type="email" name="email" required placeholder="Email address"><button type="submit">Notify me ↗</button><span class="luma-waitlist-status" aria-live="polite"></span></form></div>';
}
add_action( 'woocommerce_single_product_summary', 'luma_core_waitlist_form', 31 );

function luma_core_loop_signal() {
    global $product;
    if ( ! $product ) return;
    $signals = array();
    $stock = $product->managing_stock() ? (int) $product->get_stock_quantity() : 0;
    if ( $product->is_on_sale() ) $signals[] = __( 'Sale', 'luma-commerce-core' );
    if ( $stock > 0 && $stock <= 8 && ! $product->backorders_allowed() ) $signals[] = sprintf( __( 'Only %d left', 'luma-commerce-core' ), $stock );
    elseif ( (int) $product->get_total_sales() > 0 ) $signals[] = __( 'Popular', 'luma-commerce-core' );
    if ( $signals ) echo '<span class="luma-loop-signal">' . esc_html( implode( ' · ', $signals ) ) . '</span>';
}
function luma_core_loop_rating() {
    global $product;
    if ( ! $product || ! $product->get_review_count() ) return;
    $count = (int) $product->get_review_count();
    echo '<div class="luma-loop-rating"><span aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'luma-commerce-core' ), number_format_i18n( (float) $product->get_average_rating(), 1 ) ) ) . '">' . wp_kses_post( wc_get_rating_html( $product->get_average_rating(), $count ) ) . '</span><small>' . esc_html( sprintf( _n( '%d review', '%d reviews', $count, 'luma-commerce-core' ), $count ) ) . '</small></div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'luma_core_loop_rating', 11 );
add_action( 'woocommerce_after_shop_loop_item_title', 'luma_core_loop_signal', 12 );

function luma_core_shop_toolbar() {
    if ( ! luma_core_option( 'module_filters', true ) || ( ! is_post_type_archive( 'product' ) && ! is_tax( 'product_cat' ) ) ) return;
    $current_order = isset( $_GET['luma_orderby'] ) && is_scalar( $_GET['luma_orderby'] ) ? sanitize_key( wp_unslash( $_GET['luma_orderby'] ) ) : '';
    $current_cat = isset( $_GET['luma_cat'] ) && is_scalar( $_GET['luma_cat'] ) ? sanitize_title( wp_unslash( $_GET['luma_cat'] ) ) : '';
    $stock_filter = isset( $_GET['stock_status'] ) && is_scalar( $_GET['stock_status'] ) ? sanitize_key( wp_unslash( $_GET['stock_status'] ) ) : '';
    $sale_filter = ! empty( $_GET['on_sale'] );
    $queried_category = is_tax( 'product_cat' ) ? get_queried_object() : false;
    $context_category = $queried_category && ! empty( $queried_category->slug ) ? sanitize_title( $queried_category->slug ) : '';
    if ( ! $current_cat ) $current_cat = $context_category;
    $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
    $attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? array_slice( (array) wc_get_attribute_taxonomies(), 0, 2 ) : array();
    $published_products = wp_count_posts( 'product' );
    $loop_total = function_exists( 'wc_get_loop_prop' ) ? wc_get_loop_prop( 'total', false ) : false;
    $total_products = false !== $loop_total ? (int) $loop_total : ( $published_products && isset( $published_products->publish ) ? (int) $published_products->publish : 0 );
    $current_attributes = isset( $_GET['luma_attr'] ) && is_array( $_GET['luma_attr'] ) ? wp_unslash( $_GET['luma_attr'] ) : array();
    ob_start(); ?>
    <form class="luma-shop-toolbar" method="get" data-luma-filters>
        <?php if ( $context_category ) : ?><input type="hidden" name="luma_context_cat" value="<?php echo esc_attr( $context_category ); ?>"><?php endif; ?>
        <div class="luma-shop-toolbar__intro"><span class="luma-kicker"><?php esc_html_e( 'Refine the edit', 'luma-commerce-core' ); ?></span><strong data-luma-filter-count aria-live="polite"><?php echo esc_html( $total_products ); ?> <?php esc_html_e( 'pieces', 'luma-commerce-core' ); ?></strong><button type="button" class="luma-filter-toggle" data-luma-filter-toggle aria-expanded="false" aria-controls="luma-filter-panel"><?php esc_html_e( 'Filters', 'luma-commerce-core' ); ?></button></div>
        <div class="luma-shop-toolbar__active" data-luma-active-filters aria-live="polite"></div>
        <div class="luma-shop-toolbar__controls" id="luma-filter-panel" data-luma-filter-panel aria-hidden="false"><button type="button" class="luma-filter-close" data-luma-filter-close aria-label="<?php esc_attr_e( 'Close filters', 'luma-commerce-core' ); ?>">× <span><?php esc_html_e( 'Filters', 'luma-commerce-core' ); ?></span></button>
            <label><?php esc_html_e( 'Category', 'luma-commerce-core' ); ?><select name="luma_cat"><option value=""><?php esc_html_e( 'All', 'luma-commerce-core' ); ?></option><?php if ( ! is_wp_error( $categories ) ) foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $current_cat, $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option><?php endforeach; ?></select></label>
            <label><?php esc_html_e( 'Min price', 'luma-commerce-core' ); ?><input type="number" name="min_price" min="0" value="<?php echo esc_attr( isset( $_GET['min_price'] ) && is_scalar( $_GET['min_price'] ) ? wp_unslash( $_GET['min_price'] ) : '' ); ?>" placeholder="0"></label>
            <label><?php esc_html_e( 'Max price', 'luma-commerce-core' ); ?><input type="number" name="max_price" min="0" value="<?php echo esc_attr( isset( $_GET['max_price'] ) && is_scalar( $_GET['max_price'] ) ? wp_unslash( $_GET['max_price'] ) : '' ); ?>" placeholder="Any"></label>
            <label><?php esc_html_e( 'Sort', 'luma-commerce-core' ); ?><select name="luma_orderby"><option value=""><?php esc_html_e( 'Latest', 'luma-commerce-core' ); ?></option><option value="price" <?php selected( $current_order, 'price' ); ?>><?php esc_html_e( 'Price: low to high', 'luma-commerce-core' ); ?></option><option value="price-desc" <?php selected( $current_order, 'price-desc' ); ?>><?php esc_html_e( 'Price: high to low', 'luma-commerce-core' ); ?></option><option value="popularity" <?php selected( $current_order, 'popularity' ); ?>><?php esc_html_e( 'Most loved', 'luma-commerce-core' ); ?></option></select></label>
            <label class="luma-filter-check"><input type="checkbox" name="stock_status" value="instock" <?php checked( $stock_filter, 'instock' ); ?>> <?php esc_html_e( 'In stock', 'luma-commerce-core' ); ?></label>
            <label class="luma-filter-check"><input type="checkbox" name="on_sale" value="1" <?php checked( $sale_filter ); ?>> <?php esc_html_e( 'On sale', 'luma-commerce-core' ); ?></label>
            <?php foreach ( $attributes as $attribute ) : $taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name ); $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 8 ) ); if ( is_wp_error( $terms ) || ! $terms ) continue; $selected_terms = isset( $current_attributes[ $taxonomy ] ) && is_array( $current_attributes[ $taxonomy ] ) ? array_map( 'sanitize_title', array_filter( $current_attributes[ $taxonomy ], 'is_scalar' ) ) : array(); ?><fieldset class="luma-filter-attribute"><legend><?php echo esc_html( $attribute->attribute_label ); ?></legend><?php foreach ( $terms as $term ) : ?><label><input type="checkbox" name="luma_attr[<?php echo esc_attr( $taxonomy ); ?>][]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $selected_terms, true ) ); ?>> <?php echo esc_html( $term->name ); ?></label><?php endforeach; ?></fieldset><?php endforeach; ?>
            <button type="submit"><?php esc_html_e( 'Apply', 'luma-commerce-core' ); ?> ↗</button><a href="<?php echo esc_url( remove_query_arg( array( 'luma_cat', 'luma_orderby', 'min_price', 'max_price', 'stock_status', 'on_sale', 'luma_attr' ) ) ); ?>"><?php esc_html_e( 'Reset', 'luma-commerce-core' ); ?></a>
        </div>
        <button type="button" class="luma-filter-backdrop" data-luma-filter-backdrop hidden aria-label="<?php esc_attr_e( 'Close filters', 'luma-commerce-core' ); ?>"></button>
    </form>
    <?php echo ob_get_clean();
}
add_action( 'woocommerce_before_shop_loop', 'luma_core_shop_toolbar', 9 );

function luma_core_filter_query_args( $request ) {
    $args = array( 'status' => 'publish', 'limit' => max( 4, min( 48, (int) apply_filters( 'loop_shop_per_page', 12 ) ) ), 'orderby' => 'date', 'order' => 'DESC' );
    if ( isset( $request['paged'] ) && is_scalar( $request['paged'] ) ) $args['page'] = max( 1, absint( $request['paged'] ) );
    $category_key = isset( $request['luma_cat'] ) && is_scalar( $request['luma_cat'] ) && '' !== $request['luma_cat'] ? 'luma_cat' : 'luma_context_cat';
    $category = isset( $request[ $category_key ] ) && is_scalar( $request[ $category_key ] ) ? sanitize_title( wp_unslash( $request[ $category_key ] ) ) : '';
    $tag = isset( $request['product_tag'] ) && is_scalar( $request['product_tag'] ) ? sanitize_title( wp_unslash( $request['product_tag'] ) ) : '';
    if ( $category ) $args['category'] = $category;
    if ( $tag ) $args['tag'] = $tag;
    if ( ! empty( $request['stock_status'] ) && 'instock' === sanitize_key( is_scalar( $request['stock_status'] ) ? wp_unslash( $request['stock_status'] ) : '' ) ) $args['stock_status'] = 'instock';
    if ( ! empty( $request['on_sale'] ) ) $args['on_sale'] = true;
    $meta_query = array();
    if ( isset( $request['min_price'] ) && is_scalar( $request['min_price'] ) && is_numeric( $request['min_price'] ) ) $meta_query[] = array( 'key' => '_price', 'value' => (float) $request['min_price'], 'compare' => '>=', 'type' => 'NUMERIC' );
    if ( isset( $request['max_price'] ) && is_scalar( $request['max_price'] ) && is_numeric( $request['max_price'] ) ) $meta_query[] = array( 'key' => '_price', 'value' => (float) $request['max_price'], 'compare' => '<=', 'type' => 'NUMERIC' );
    if ( $meta_query ) $args['meta_query'] = $meta_query;
    $attributes = isset( $request['luma_attr'] ) && is_array( $request['luma_attr'] ) ? $request['luma_attr'] : array();
    $allowed_taxonomies = array( 'product_cat', 'product_tag' );
    if ( function_exists( 'wc_get_attribute_taxonomies' ) ) foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) $allowed_taxonomies[] = wc_attribute_taxonomy_name( $attribute->attribute_name );
    $tax_query = array();
    foreach ( $attributes as $taxonomy => $terms ) if ( in_array( sanitize_key( $taxonomy ), array_unique( $allowed_taxonomies ), true ) && is_array( $terms ) ) { $terms = array_filter( array_map( 'sanitize_title', array_filter( wp_unslash( $terms ), 'is_scalar' ) ) ); if ( $terms ) $tax_query[] = array( 'taxonomy' => sanitize_key( $taxonomy ), 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN' ); }
    if ( $tax_query ) $args['tax_query'] = $tax_query;
    $orderby = isset( $request['luma_orderby'] ) && is_scalar( $request['luma_orderby'] ) ? sanitize_key( wp_unslash( $request['luma_orderby'] ) ) : '';
    if ( 'price' === $orderby ) { $args['orderby'] = 'price'; $args['order'] = 'ASC'; }
    if ( 'price-desc' === $orderby ) { $args['orderby'] = 'price'; $args['order'] = 'DESC'; }
    if ( 'popularity' === $orderby ) $args['orderby'] = 'popularity';
    return $args;
}

function luma_core_filter_products_query( $query ) {
    if ( ! luma_core_option( 'module_filters', true ) || is_admin() || ! $query->is_main_query() || ( ! is_post_type_archive( 'product' ) && ! is_tax( 'product_cat' ) ) ) return;
    $request = $_GET;
    $args = luma_core_filter_query_args( $request );
    if ( ! empty( $args['category'] ) ) {
        $tax_query = (array) $query->get( 'tax_query' );
        $explicit_category = isset( $request['luma_cat'] ) && is_scalar( $request['luma_cat'] ) && '' !== $request['luma_cat'];
        if ( $explicit_category ) $tax_query = array_values( array_filter( $tax_query, function( $clause ) { return ! is_array( $clause ) || empty( $clause['taxonomy'] ) || 'product_cat' !== $clause['taxonomy']; } ) );
        $tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $args['category'] );
        $query->set( 'tax_query', $tax_query );
    }
    if ( ! empty( $args['tax_query'] ) ) $query->set( 'tax_query', array_merge( (array) $query->get( 'tax_query' ), $args['tax_query'] ) );
    /* The tag filter was resolved for the AJAX endpoint but silently dropped
       here, so ?product_tag= on a shop archive filtered nothing at all. */
    if ( ! empty( $args['tag'] ) ) {
        $tag_tax_query = (array) $query->get( 'tax_query' );
        $tag_tax_query[] = array( 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => $args['tag'] );
        $query->set( 'tax_query', $tag_tax_query );
    }
    if ( ! empty( $args['meta_query'] ) ) $query->set( 'meta_query', array_merge( (array) $query->get( 'meta_query' ), $args['meta_query'] ) );
    if ( ! empty( $args['stock_status'] ) ) { $stock_meta = array( 'key' => '_stock_status', 'value' => 'instock' ); $query->set( 'meta_query', array_merge( (array) $query->get( 'meta_query' ), array( $stock_meta ) ) ); }
    if ( ! empty( $args['on_sale'] ) && function_exists( 'wc_get_product_ids_on_sale' ) ) $query->set( 'post__in', wc_get_product_ids_on_sale() ?: array( 0 ) );
    $requested_order = isset( $request['luma_orderby'] ) && is_scalar( $request['luma_orderby'] ) ? sanitize_key( wp_unslash( $request['luma_orderby'] ) ) : '';
    if ( in_array( $requested_order, array( 'price', 'price-desc' ), true ) ) { $query->set( 'meta_key', '_price' ); $query->set( 'orderby', 'meta_value_num' ); $query->set( 'order', 'price' === $requested_order ? 'ASC' : 'DESC' ); }
    if ( 'popularity' === ( $args['orderby'] ?? '' ) ) { $query->set( 'meta_key', 'total_sales' ); $query->set( 'orderby', 'meta_value_num' ); $query->set( 'order', 'DESC' ); }
}
add_action( 'pre_get_posts', 'luma_core_filter_products_query' );

function luma_core_ajax_product_card( $product ) {
    if ( ! $product ) return '';
    $badge = $product->is_on_sale() ? '<span class="onsale">' . esc_html__( 'Sale', 'luma-commerce-core' ) . '</span>' : '';
    $stock = $product->managing_stock() && (int) $product->get_stock_quantity() > 0 && (int) $product->get_stock_quantity() <= 8 && ! $product->backorders_allowed() ? '<span class="luma-loop-signal">' . sprintf( esc_html__( 'Only %d left', 'luma-commerce-core' ), (int) $product->get_stock_quantity() ) . '</span>' : '';
    $rating = $product->get_review_count() ? '<div class="luma-loop-rating"><span aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'luma-commerce-core' ), number_format_i18n( (float) $product->get_average_rating(), 1 ) ) ) . '">' . wp_kses_post( wc_get_rating_html( $product->get_average_rating(), $product->get_review_count() ) ) . '</span><small>' . esc_html( sprintf( _n( '%d review', '%d reviews', (int) $product->get_review_count(), 'luma-commerce-core' ), (int) $product->get_review_count() ) ) . '</small></div>' : '';
    $actions = '<div class="luma-loop-actions">';
    if ( luma_core_option( 'module_wishlist', true ) ) $actions .= '<button class="luma-wishlist-toggle" type="button" data-wishlist-id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr__( 'Add to wish list', 'luma-commerce-core' ) . '">♡</button>';
    if ( luma_core_option( 'module_compare', true ) ) $actions .= '<button class="luma-compare-toggle" type="button" data-compare-id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr__( 'Add to compare', 'luma-commerce-core' ) . '" aria-pressed="false">' . esc_html__( 'Compare', 'luma-commerce-core' ) . '</button>';
    if ( luma_core_option( 'module_quick_view', true ) ) { $actions .= '<button class="luma-quick-view" type="button" data-product-id="' . esc_attr( $product->get_id() ) . '">' . esc_html__( 'Quick view', 'luma-commerce-core' ) . '</button>'; if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) $actions .= '<button class="luma-quick-add" type="button" data-product-id="' . esc_attr( $product->get_id() ) . '">' . esc_html__( 'Quick add', 'luma-commerce-core' ) . ' <span>+</span></button>'; }
    $actions .= '</div>';
    return '<li class="product type-product luma-product-loop-item" data-product-id="' . esc_attr( $product->get_id() ) . '"><a class="woocommerce-LoopProduct-link woocommerce-loop-product__link" href="' . esc_url( $product->get_permalink() ) . '">' . $badge . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</a>' . $stock . '<h2 class="woocommerce-loop-product__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h2><span class="price">' . wp_kses_post( $product->get_price_html() ) . '</span>' . $rating . $actions . '</li>';
}

/**
 * Pagination markup for an AJAX-filtered result set.
 *
 * Without this the shop kept the pagination rendered for the *unfiltered*
 * query, so a filtered view offered page numbers that no longer existed and
 * clicking them produced an empty grid.
 *
 * @param int    $total_pages Total pages for the filtered query.
 * @param int    $current     Current page.
 * @param string $base_url    Filtered archive URL supplied by the browser.
 * @return string
 */
function luma_core_filter_pagination( $total_pages, $current, $base_url = '' ) {
    $total_pages = (int) $total_pages;
    if ( $total_pages < 2 ) return '';

    // Only accept a same-origin URL from the client; otherwise use the shop.
    $home_host = (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: '' );
    $candidate = $base_url ? esc_url_raw( (string) $base_url ) : '';
    $candidate_host = $candidate ? (string) ( wp_parse_url( $candidate, PHP_URL_HOST ) ?: '' ) : '';
    if ( '' === $candidate || $candidate_host !== $home_host ) $candidate = luma_core_shop_url();

    $candidate = remove_query_arg( 'paged', $candidate );
    $links = paginate_links(
        array(
            'base'      => add_query_arg( 'paged', '%#%', $candidate ),
            'format'    => '',
            'current'   => max( 1, (int) $current ),
            'total'     => $total_pages,
            'type'      => 'array',
            'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
            'next_text' => is_rtl() ? '&larr;' : '&rarr;',
            'mid_size'  => 1,
        )
    );
    if ( ! $links ) return '';
    return '<ul class="page-numbers">' . implode( '', array_map( static function ( $link ) { return '<li>' . $link . '</li>'; }, $links ) ) . '</ul>';
}

function luma_core_ajax_filter_products() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! function_exists( 'wc_get_products' ) ) wp_send_json_error( array( 'message' => __( 'Product filters are unavailable.', 'luma-commerce-core' ) ), 400 );
    if ( ! luma_core_option( 'module_filters', true ) ) wp_send_json_error( array( 'message' => __( 'Filters are disabled.', 'luma-commerce-core' ) ), 403 );
    $args = luma_core_filter_query_args( $_POST );
    $args['paginate'] = true;
    $results = wc_get_products( $args );
    $products = is_object( $results ) && isset( $results->products ) ? $results->products : (array) $results;
    $total = is_object( $results ) && isset( $results->total ) ? (int) $results->total : count( $products );
    $per_page = max( 1, (int) $args['limit'] );
    $total_pages = (int) ceil( $total / $per_page );
    $current_page = isset( $args['page'] ) ? (int) $args['page'] : 1;
    $base_url = isset( $_POST['base_url'] ) && is_scalar( $_POST['base_url'] ) ? wp_unslash( $_POST['base_url'] ) : '';
    ob_start();
    foreach ( $products as $product ) echo luma_core_ajax_product_card( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- the card is escaped when built.
    wp_send_json_success(
        array(
            'html'         => ob_get_clean(),
            'count'        => $total,
            'total_pages'  => $total_pages,
            'current_page' => $current_page,
            'pagination'   => luma_core_filter_pagination( $total_pages, $current_page, $base_url ),
            'message'      => $products ? '' : esc_html__( 'No products found for these filters.', 'luma-commerce-core' ),
        )
    );
}
add_action( 'wp_ajax_luma_filter_products', 'luma_core_ajax_filter_products' );
add_action( 'wp_ajax_nopriv_luma_filter_products', 'luma_core_ajax_filter_products' );

function luma_core_footer_features() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    global $product;
    $current_product = ( is_product() && $product ) ? $product : ( is_product() ? wc_get_product( get_the_ID() ) : false );
    if ( $current_product ) echo '<span class="luma-current-product" data-luma-current-product="' . esc_attr( $current_product->get_id() ) . '" hidden></span>';
    ?>
    <?php if ( luma_core_option( 'module_cart_drawer', true ) ) : ?>
    <div class="luma-cart-drawer" id="luma-cart-drawer" aria-hidden="true"><div class="luma-cart-drawer__backdrop"></div><aside class="luma-cart-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping bag', 'luma-commerce-core' ); ?>"><div class="luma-cart-drawer__header"><span class="luma-kicker"><?php esc_html_e( 'Your edit', 'luma-commerce-core' ); ?></span><button class="luma-cart-drawer__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'luma-commerce-core' ); ?>">×</button><h2><?php esc_html_e( 'Your bag', 'luma-commerce-core' ); ?></h2></div><div class="luma-cart-drawer__body"><div class="luma-cart-notices" data-cart-notices aria-live="polite"></div><div data-cart-body><p class="luma-cart-empty"><?php esc_html_e( 'Your bag is ready when you are.', 'luma-commerce-core' ); ?></p></div><div class="luma-cart-drawer__meter" data-cart-meter><?php echo do_shortcode( '[luma_shipping_meter]' ); ?></div><div class="luma-cart-drawer__recommendations" data-cart-recommendations><?php echo luma_core_cart_recommendations(); ?></div><div class="luma-cart-drawer__saved" data-cart-saved><?php echo luma_core_render_saved_items(); ?></div></div><div class="luma-cart-drawer__footer"><div><span><?php esc_html_e( 'Subtotal', 'luma-commerce-core' ); ?></span><strong data-cart-subtotal><?php echo wp_kses_post( luma_core_cart_available() ? WC()->cart->get_cart_subtotal() : wc_price( 0 ) ); ?></strong></div><a class="luma-button" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'View bag', 'luma-commerce-core' ); ?> <span>↗</span></a><a class="luma-drawer-checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'luma-commerce-core' ); ?> <span>↗</span></a></div></aside></div>
    <?php endif; ?>
    <?php if ( luma_core_option( 'module_compare', true ) ) : ?>
    <div class="luma-compare-tray" aria-live="polite"><span><strong data-compare-count>0</strong> <?php esc_html_e( 'items ready to compare', 'luma-commerce-core' ); ?></span><a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>"><?php esc_html_e( 'Compare now', 'luma-commerce-core' ); ?> ↗</a></div>
    <?php endif; ?>
    <?php if ( luma_core_option( 'whatsapp', '' ) ) : ?><a class="luma-whatsapp" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', luma_core_option( 'whatsapp', '' ) ) ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'luma-commerce-core' ); ?>">WA<span><?php esc_html_e( 'Chat with us', 'luma-commerce-core' ); ?></span></a><?php endif; ?>
    <?php if ( luma_core_option( 'analytics_enabled', false ) && '' === luma_core_analytics_consent_state() ) : ?><aside class="luma-analytics-consent" id="luma-analytics-consent" aria-labelledby="luma-analytics-consent-title"><div><strong id="luma-analytics-consent-title"><?php esc_html_e( 'Help us improve the Luma edit', 'luma-commerce-core' ); ?></strong><p><?php esc_html_e( 'Allow anonymous campaign and storefront measurement. No email, account or personal profile is sent.', 'luma-commerce-core' ); ?></p></div><div><button type="button" data-luma-analytics="accept"><?php esc_html_e( 'Allow measurement', 'luma-commerce-core' ); ?></button><button type="button" class="luma-analytics-consent__reject" data-luma-analytics="reject"><?php esc_html_e( 'Not now', 'luma-commerce-core' ); ?></button></div></aside><?php endif; ?>
    <?php
}
add_action( 'wp_footer', 'luma_core_footer_features', 30 );

function luma_core_offer_popup() {
    /*
     * Guard the WooCommerce conditional tags: they are undefined when the
     * plugin is deactivated, which used to fatal on every front-end page if a
     * merchant had previously enabled the popup.
     */
    if ( ! class_exists( 'WooCommerce' ) || ! luma_core_option( 'popup_enabled', false ) ) return;
    if ( ! luma_core_coupon_available( luma_core_option( 'coupon_code', '' ) ) ) return;
    if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) return;
    ?>
    <div class="luma-offer-popup" id="luma-offer-popup" data-delay="<?php echo esc_attr( luma_core_option( 'popup_delay', 8 ) ); ?>" aria-hidden="true"><div class="luma-offer-popup__backdrop"></div><section class="luma-offer-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="luma-offer-title"><button class="luma-offer-popup__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'luma-commerce-core' ); ?>">×</button><div class="luma-offer-popup__visual"><span>LUMA<br>01</span></div><div class="luma-offer-popup__content"><p class="luma-kicker"><?php esc_html_e( 'A little something extra', 'luma-commerce-core' ); ?></p><h2 id="luma-offer-title"><?php echo esc_html( luma_core_option( 'popup_title', 'Your first Luma move' ) ); ?></h2><p><?php echo esc_html( luma_core_option( 'popup_text', 'Join the edit and unlock your first-order offer.' ) ); ?></p><form data-luma-lead><label class="screen-reader-text" for="luma-lead-email"><?php esc_html_e( 'Email address', 'luma-commerce-core' ); ?></label><input id="luma-lead-email" type="email" name="email" required placeholder="<?php esc_attr_e( 'Your email address', 'luma-commerce-core' ); ?>"><input class="luma-form-trap" type="text" name="luma_website" tabindex="-1" autocomplete="off" aria-hidden="true"><button type="submit"><?php esc_html_e( 'Unlock offer', 'luma-commerce-core' ); ?> <span>↗</span></button><span class="luma-lead-status" aria-live="polite"></span></form><div class="luma-offer-code"><?php esc_html_e( 'Use code', 'luma-commerce-core' ); ?> <strong><?php echo esc_html( luma_core_option( 'coupon_code', '' ) ); ?></strong><button type="button" class="luma-copy-offer" data-offer-code="<?php echo esc_attr( luma_core_option( 'coupon_code', '' ) ); ?>"><?php esc_html_e( 'Copy code', 'luma-commerce-core' ); ?></button></div><small><?php esc_html_e( 'Coupon terms apply. You can unsubscribe anytime.', 'luma-commerce-core' ); ?></small></div></section></div>
    <?php
}
add_action( 'wp_footer', 'luma_core_offer_popup', 32 );

function luma_core_quick_add() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_option( 'module_quick_view', true ) ) wp_send_json_error( array( 'message' => __( 'Quick add is disabled.', 'luma-commerce-core' ) ), 403 );
    if ( ! luma_core_cart_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    $product = luma_core_public_product( isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0 );
    if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) wp_send_json_error( array( 'message' => __( 'This item is not available.', 'luma-commerce-core' ) ), 400 );
    if ( ! WC()->cart->add_to_cart( $product->get_id(), 1 ) ) wp_send_json_error( array( 'message' => __( 'Could not add this item.', 'luma-commerce-core' ) ), 400 );
    WC()->cart->calculate_totals();
    wp_send_json_success( array_merge( luma_core_cart_payload(), array( 'message' => __( 'Added to bag', 'luma-commerce-core' ) ) ) );
}
add_action( 'wp_ajax_luma_quick_add', 'luma_core_quick_add' );
add_action( 'wp_ajax_nopriv_luma_quick_add', 'luma_core_quick_add' );

function luma_core_render_cart_items() {
    if ( ! luma_core_cart_available() || WC()->cart->is_empty() ) return '<p class="luma-cart-empty">' . esc_html__( 'Your bag is ready when you are.', 'luma-commerce-core' ) . '</p>';
    ob_start(); echo '<ul class="luma-mini-cart">';
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $item_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
        if ( ! $item_product || ! $item_product->exists() || $cart_item['quantity'] <= 0 ) continue;
        echo '<li data-cart-key="' . esc_attr( $cart_item_key ) . '"><a class="luma-mini-cart__image" href="' . esc_url( $item_product->get_permalink( $cart_item ) ) . '">' . wp_kses_post( $item_product->get_image( 'woocommerce_thumbnail' ) ) . '</a><div class="luma-mini-cart__info"><a class="luma-mini-cart__name" href="' . esc_url( $item_product->get_permalink( $cart_item ) ) . '">' . esc_html( $item_product->get_name() ) . '</a><span class="luma-mini-cart__meta">' . wp_kses_post( wc_get_formatted_cart_item_data( $cart_item ) ) . '</span><strong>' . wp_kses_post( WC()->cart->get_product_price( $item_product ) ) . '</strong><button type="button" class="luma-save-for-later" data-cart-key="' . esc_attr( $cart_item_key ) . '">' . esc_html__( 'Save for later', 'luma-commerce-core' ) . '</button><div class="luma-mini-cart__controls"><button type="button" data-cart-action="minus" aria-label="' . esc_attr__( 'Decrease quantity', 'luma-commerce-core' ) . '">−</button><span>' . esc_html( $cart_item['quantity'] ) . '</span><button type="button" data-cart-action="plus" aria-label="' . esc_attr__( 'Increase quantity', 'luma-commerce-core' ) . '">+</button><button type="button" class="luma-mini-cart__remove" data-cart-action="remove">' . esc_html__( 'Remove', 'luma-commerce-core' ) . '</button></div></div></li>';
    }
    echo '</ul>'; return ob_get_clean();
}

function luma_core_saved_item_key( $product_id, $variation_id = 0, $variation = array() ) {
    return md5( absint( $product_id ) . '|' . absint( $variation_id ) . '|' . wp_json_encode( (array) $variation ) );
}

function luma_core_normalize_saved_items( $items ) {
    $normalized = array();
    foreach ( (array) $items as $saved ) {
        if ( ! is_array( $saved ) ) continue;
        $product_id = isset( $saved['product_id'] ) ? absint( $saved['product_id'] ) : 0;
        if ( ! $product_id ) continue;
        $variation_id = isset( $saved['variation_id'] ) ? absint( $saved['variation_id'] ) : 0;
        $variation = array();
        if ( isset( $saved['variation'] ) && is_array( $saved['variation'] ) ) foreach ( $saved['variation'] as $name => $value ) if ( is_scalar( $value ) && '' !== (string) $value ) $variation[ sanitize_key( $name ) ] = function_exists( 'wc_clean' ) ? wc_clean( (string) $value ) : sanitize_text_field( (string) $value );
        $normalized[] = array( 'key' => luma_core_saved_item_key( $product_id, $variation_id, $variation ), 'product_id' => $product_id, 'variation_id' => $variation_id, 'variation' => $variation, 'quantity' => min( 999, max( 1, absint( $saved['quantity'] ?? 1 ) ) ) );
        if ( count( $normalized ) >= 20 ) break;
    }
    return $normalized;
}

function luma_core_merge_saved_items( $primary, $secondary ) {
    $merged = luma_core_normalize_saved_items( $primary );
    foreach ( luma_core_normalize_saved_items( $secondary ) as $incoming ) {
        $found = false;
        foreach ( $merged as &$saved ) if ( $saved['key'] === $incoming['key'] ) { $saved['quantity'] = min( 999, $saved['quantity'] + $incoming['quantity'] ); $found = true; break; }
        unset( $saved );
        if ( ! $found && count( $merged ) < 20 ) $merged[] = $incoming;
    }
    return luma_core_normalize_saved_items( $merged );
}

function luma_core_saved_items() {
    if ( ! luma_core_session_available() ) return array();
    $items = WC()->session->get( 'luma_saved_items', null );
    if ( null === $items && is_user_logged_in() ) $items = get_user_meta( get_current_user_id(), 'luma_saved_items', true );
    return luma_core_normalize_saved_items( $items );
}

function luma_core_set_saved_items( $items ) {
    $items = luma_core_normalize_saved_items( $items );
    if ( function_exists( 'WC' ) && WC() && WC()->session ) WC()->session->set( 'luma_saved_items', $items );
    if ( is_user_logged_in() ) update_user_meta( get_current_user_id(), 'luma_saved_items', $items );
}

function luma_core_sync_saved_items_account() {
    if ( ! is_user_logged_in() || ! luma_core_session_available() ) return;
    $user_id = get_current_user_id();
    if ( $user_id === (int) WC()->session->get( 'luma_saved_user_id', 0 ) ) return;
    $session_items = WC()->session->get( 'luma_saved_items', array() );
    $account_items = get_user_meta( $user_id, 'luma_saved_items', true );
    luma_core_set_saved_items( luma_core_merge_saved_items( $session_items, $account_items ) );
    WC()->session->set( 'luma_saved_user_id', $user_id );
}
add_action( 'wp_loaded', 'luma_core_sync_saved_items_account', 20 );

function luma_core_clear_saved_items_session() {
    if ( function_exists( 'WC' ) && WC() && WC()->session ) { WC()->session->set( 'luma_saved_items', array() ); WC()->session->set( 'luma_saved_user_id', 0 ); }
}
add_action( 'wp_logout', 'luma_core_clear_saved_items_session' );

function luma_core_saved_items_exporter( $email_address, $page = 1 ) {
    $user = get_user_by( 'email', $email_address );
    if ( ! $user || 1 !== (int) $page ) return array( 'data' => array(), 'done' => true );
    $items = luma_core_normalize_saved_items( get_user_meta( $user->ID, 'luma_saved_items', true ) );
    return array( 'data' => $items ? array( array( 'group_id' => 'luma-saved-items', 'group_label' => __( 'Luma saved items', 'luma-commerce-core' ), 'item_id' => 'luma_saved_items', 'data' => array( array( 'name' => __( 'Saved products and quantities', 'luma-commerce-core' ), 'value' => wp_json_encode( $items ) ) ) ) ) : array(), 'done' => true );
}
add_filter( 'wp_privacy_personal_data_exporters', function( $exporters ) { $exporters['luma-saved-items'] = array( 'exporter_friendly_name' => __( 'Luma saved items', 'luma-commerce-core' ), 'callback' => 'luma_core_saved_items_exporter' ); return $exporters; } );

function luma_core_saved_items_eraser( $email_address, $page = 1 ) {
    $user = get_user_by( 'email', $email_address );
    if ( $user && 1 === (int) $page ) {
        delete_user_meta( $user->ID, 'luma_saved_items' );
        if ( $user->ID === get_current_user_id() ) luma_core_clear_saved_items_session();
    }
    return array( 'items_removed' => (bool) $user, 'items_retained' => false, 'messages' => array(), 'done' => true );
}
add_filter( 'wp_privacy_personal_data_erasers', function( $erasers ) { $erasers['luma-saved-items'] = array( 'eraser_friendly_name' => __( 'Luma saved items', 'luma-commerce-core' ), 'callback' => 'luma_core_saved_items_eraser' ); return $erasers; } );

/*
 * Waitlist sign-ups and newsletter leads both store a customer email address in
 * a private post, but only "saved items" was registered with WordPress's
 * personal-data tools. Without exporters and erasers a merchant could not
 * answer a subject-access or deletion request for this data.
 */
function luma_core_privacy_email_records( $email_address, $page = 1 ) {
    $records = array();
    $per_page = 50;
    $offset = max( 0, ( (int) $page - 1 ) * $per_page );
    $types = array(
        'luma_waitlist' => array(
            'label'     => __( 'Restock request', 'luma-commerce-core' ),
            'email_key' => '_luma_waitlist_email',
        ),
        'luma_lead'     => array(
            'label'     => __( 'Newsletter sign-up', 'luma-commerce-core' ),
            'email_key' => '_luma_lead_email',
        ),
    );
    foreach ( $types as $post_type => $config ) {
        if ( ! post_type_exists( $post_type ) ) continue;
        $posts = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => 'private',
                'posts_per_page' => $per_page,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'meta_key'       => $config['email_key'],
                'meta_value'     => $email_address,
            )
        );
        foreach ( $posts as $post ) {
            $data = array(
                array( 'name' => __( 'Email address', 'luma-commerce-core' ), 'value' => $email_address ),
                array( 'name' => __( 'Recorded', 'luma-commerce-core' ), 'value' => get_the_date( '', $post ) ),
            );
            if ( 'luma_waitlist' === $post_type ) {
                $product_id = (int) get_post_meta( $post->ID, '_luma_waitlist_product', true );
                if ( $product_id ) $data[] = array( 'name' => __( 'Product', 'luma-commerce-core' ), 'value' => get_the_title( $product_id ) );
                if ( get_post_meta( $post->ID, '_luma_waitlist_notified', true ) ) $data[] = array( 'name' => __( 'Notified', 'luma-commerce-core' ), 'value' => (string) get_post_meta( $post->ID, '_luma_waitlist_notified', true ) );
            }
            if ( 'luma_lead' === $post_type && get_post_meta( $post->ID, '_luma_lead_coupon', true ) ) {
                $data[] = array( 'name' => __( 'Offer stored', 'luma-commerce-core' ), 'value' => (string) get_post_meta( $post->ID, '_luma_lead_coupon', true ) );
            }
            $records[] = array( 'group_id' => 'luma-' . $post_type, 'group_label' => $config['label'], 'item_id' => $post_type . '-' . $post->ID, 'data' => $data );
        }
    }
    return $records;
}

function luma_core_privacy_exporter( $email_address, $page = 1 ) {
    $email_address = is_string( $email_address ) ? strtolower( sanitize_email( $email_address ) ) : '';
    if ( ! is_email( $email_address ) ) return array( 'data' => array(), 'done' => true );
    return array( 'data' => luma_core_privacy_email_records( $email_address, $page ), 'done' => count( luma_core_privacy_email_records( $email_address, $page + 1 ) ) < 1 );
}

function luma_core_privacy_eraser( $email_address, $page = 1 ) {
    $email_address = is_string( $email_address ) ? strtolower( sanitize_email( $email_address ) ) : '';
    if ( ! is_email( $email_address ) ) return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
    $removed = false;
    foreach ( luma_core_privacy_email_records( $email_address, $page ) as $record ) {
        $post_id = (int) substr( (string) $record['item_id'], strrpos( (string) $record['item_id'], '-' ) + 1 );
        if ( $post_id && wp_delete_post( $post_id, true ) ) $removed = true;
    }
    return array(
        'items_removed'  => $removed,
        'items_retained' => false,
        'messages'       => array(),
        'done'           => count( luma_core_privacy_email_records( $email_address, $page + 1 ) ) < 1,
    );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['luma-email-records'] = array(
        'exporter_friendly_name' => __( 'Luma restock requests and newsletter sign-ups', 'luma-commerce-core' ),
        'callback'               => 'luma_core_privacy_exporter',
    );
    return $exporters;
} );
add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['luma-email-records'] = array(
        'eraser_friendly_name' => __( 'Luma restock requests and newsletter sign-ups', 'luma-commerce-core' ),
        'callback'             => 'luma_core_privacy_eraser',
    );
    return $erasers;
} );

/**
 * Contribute Luma Core's data handling to Settings -> Privacy so the merchant's
 * policy is accurate without them having to reverse-engineer the plugin.
 */
function luma_core_privacy_policy_content() {
    $content = '<h2>' . esc_html__( 'Luma Core', 'luma-commerce-core' ) . '</h2>';
    $content .= '<ul>';
    $content .= '<li>' . esc_html__( 'Restock requests store the email address and product a visitor asked about, so they can be notified when it returns. Entries are deleted after the notification is sent or on request.', 'luma-commerce-core' ) . '</li>';
    $content .= '<li>' . esc_html__( 'Newsletter sign-ups store the email address and any offer code shown at the time of sign-up. A hidden honeypot field rejects automated submissions.', 'luma-commerce-core' ) . '</li>';
    $content .= '<li>' . esc_html__( 'Saved-for-later items are kept in the WooCommerce session for guests and in user meta for signed-in customers. They contain product references only.', 'luma-commerce-core' ) . '</li>';
    $content .= '<li>' . esc_html__( 'Campaign measurement is off by default. When a merchant enables it, only UTM source, medium, campaign and content are stored, and only after the visitor accepts in the on-site notice.', 'luma-commerce-core' ) . '</li>';
    $content .= '</ul>';
    wp_add_privacy_policy_content( __( 'Luma Core', 'luma-commerce-core' ), $content );
}
add_action( 'admin_init', 'luma_core_privacy_policy_content' );

function luma_core_render_saved_items() {
    if ( ! luma_core_session_available() ) return '';
    $items = luma_core_saved_items();
    if ( ! $items ) return '';
    $html = '<section class="luma-saved-items" aria-labelledby="luma-saved-items-title"><p class="luma-kicker">' . esc_html__( 'Keep it close', 'luma-commerce-core' ) . '</p><h2 id="luma-saved-items-title">' . esc_html__( 'Saved for later', 'luma-commerce-core' ) . '</h2>';
    foreach ( $items as $saved ) {
        $product_id = isset( $saved['product_id'] ) ? absint( $saved['product_id'] ) : 0;
        $variation_id = isset( $saved['variation_id'] ) ? absint( $saved['variation_id'] ) : 0;
        $variation = isset( $saved['variation'] ) && is_array( $saved['variation'] ) ? $saved['variation'] : array();
        $key = ! empty( $saved['key'] ) ? sanitize_key( $saved['key'] ) : luma_core_saved_item_key( $product_id, $variation_id, $variation );
        $product = luma_core_public_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) {
            $html .= '<article class="luma-saved-item"><div><strong>' . esc_html__( 'Saved product unavailable', 'luma-commerce-core' ) . '</strong><button type="button" class="luma-saved-action" data-saved-action="remove" data-saved-key="' . esc_attr( $key ) . '">' . esc_html__( 'Remove', 'luma-commerce-core' ) . '</button></div></article>';
            continue;
        }
        $quantity = isset( $saved['quantity'] ) ? max( 1, absint( $saved['quantity'] ) ) : 1;
        $can_move = $product->is_purchasable() && $product->is_in_stock();
        $action = $can_move ? '<button type="button" class="luma-saved-action" data-saved-action="move" data-saved-key="' . esc_attr( $key ) . '">' . esc_html__( 'Move to bag', 'luma-commerce-core' ) . ' +</button>' : '<a class="luma-saved-action" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View piece', 'luma-commerce-core' ) . ' ↗</a>';
        $html .= '<article class="luma-saved-item" data-saved-key="' . esc_attr( $key ) . '"><a class="luma-saved-item__image" href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</a><div><h3><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3><span>' . wp_kses_post( $product->get_price_html() ) . '</span><small>' . sprintf( esc_html__( 'Quantity: %d', 'luma-commerce-core' ), $quantity ) . '</small><div class="luma-saved-item__actions">' . $action . '<button type="button" class="luma-saved-action luma-saved-action--remove" data-saved-action="remove" data-saved-key="' . esc_attr( $key ) . '">' . esc_html__( 'Remove', 'luma-commerce-core' ) . '</button></div></div></article>';
    }
    return $html . '</section>';
}
add_shortcode( 'luma_saved_items', 'luma_core_render_saved_items' );

function luma_core_cart_recommendations() {
    if ( ! luma_core_option( 'cart_recommendations_enabled', true ) || ! luma_core_cart_available() || ! function_exists( 'wc_get_products' ) || WC()->cart->is_empty() ) return '';
    $limit = max( 1, min( 4, absint( luma_core_option( 'cart_recommendation_limit', 2 ) ) ) );
    $cart_ids = array(); $cross_sell_ids = array(); $category_slugs = array();
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $item = isset( $cart_item['data'] ) ? $cart_item['data'] : false;
        if ( ! $item || ! $item->exists() ) continue;
        $cart_ids[] = $item->get_id();
        $cross_sell_ids = array_merge( $cross_sell_ids, (array) $item->get_cross_sell_ids() );
        $terms = wp_get_post_terms( $item->get_parent_id() ? $item->get_parent_id() : $item->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
        if ( ! is_wp_error( $terms ) ) $category_slugs = array_merge( $category_slugs, $terms );
    }
    $cart_ids = array_values( array_unique( array_filter( array_map( 'absint', $cart_ids ) ) ) );
    $query = array( 'status' => 'publish', 'limit' => $limit, 'exclude' => $cart_ids, 'orderby' => 'popularity' );
    $candidate_ids = array_values( array_diff( array_unique( array_map( 'absint', $cross_sell_ids ) ), $cart_ids ) );
    if ( $candidate_ids ) $query['include'] = $candidate_ids;
    elseif ( $category_slugs ) $query['category'] = array_values( array_unique( array_filter( array_map( 'sanitize_title', $category_slugs ) ) ) );
    $products = wc_get_products( $query );
    if ( ! $products && isset( $query['include'] ) ) { unset( $query['include'] ); if ( $category_slugs ) $query['category'] = array_values( array_unique( array_filter( array_map( 'sanitize_title', $category_slugs ) ) ) ); $products = wc_get_products( $query ); }
    if ( ! $products ) return '';
    $html = '<section class="luma-cart-recommendations" aria-labelledby="luma-cart-recommendations-title"><p class="luma-kicker">' . esc_html( luma_core_option( 'cart_recommendation_kicker', 'The finishing pieces' ) ) . '</p><h2 id="luma-cart-recommendations-title">' . esc_html( luma_core_option( 'cart_recommendation_title', 'Complete your bag' ) ) . '</h2><div class="luma-cart-recommendations__grid">';
    foreach ( $products as $product ) {
        $action = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ? '<button class="luma-quick-add" type="button" data-product-id="' . esc_attr( $product->get_id() ) . '">' . esc_html__( 'Quick add', 'luma-commerce-core' ) . ' +</button>' : '<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View piece', 'luma-commerce-core' ) . ' ↗</a>';
        $html .= '<article data-product-id="' . esc_attr( $product->get_id() ) . '"><a class="luma-cart-recommendations__image" href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</a><h3><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3><span>' . wp_kses_post( $product->get_price_html() ) . '</span>' . $action . '</article>';
    }
    return $html . '</div></section>';
}

function luma_core_cart_notices() {
    if ( ! function_exists( 'wc_print_notices' ) ) return '';
    ob_start(); wc_print_notices(); return ob_get_clean();
}

function luma_core_cart_payload() {
    return array( 'html' => luma_core_render_cart_items(), 'meter' => do_shortcode( '[luma_shipping_meter]' ), 'recommendations' => luma_core_cart_recommendations(), 'saved' => luma_core_render_saved_items(), 'notices' => luma_core_cart_notices(), 'count' => WC()->cart->get_cart_contents_count(), 'subtotal' => WC()->cart->get_cart_subtotal() );
}

function luma_core_cart_contents() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_cart_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    wp_send_json_success( luma_core_cart_payload() );
}
add_action( 'wp_ajax_luma_cart_contents', 'luma_core_cart_contents' );
add_action( 'wp_ajax_nopriv_luma_cart_contents', 'luma_core_cart_contents' );

function luma_core_update_cart_item() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_cart_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    $key = isset( $_POST['cart_key'] ) ? wc_clean( wp_unslash( $_POST['cart_key'] ) ) : '';
    $quantity = isset( $_POST['quantity'] ) ? max( 0, absint( $_POST['quantity'] ) ) : 0;
    $cart = WC()->cart->get_cart();
    if ( ! $key || ! isset( $cart[ $key ] ) ) wp_send_json_error( array( 'message' => __( 'Cart item not found.', 'luma-commerce-core' ) ), 404 );
    if ( 0 === $quantity ) WC()->cart->remove_cart_item( $key ); else WC()->cart->set_quantity( $key, $quantity, true );
    WC()->cart->calculate_totals();
    wp_send_json_success( luma_core_cart_payload() );
}
add_action( 'wp_ajax_luma_update_cart_item', 'luma_core_update_cart_item' );
add_action( 'wp_ajax_nopriv_luma_update_cart_item', 'luma_core_update_cart_item' );

function luma_core_save_for_later() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_cart_available() || ! luma_core_session_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    $cart_key = isset( $_POST['cart_key'] ) && is_scalar( $_POST['cart_key'] ) ? wc_clean( wp_unslash( $_POST['cart_key'] ) ) : '';
    $cart = WC()->cart->get_cart();
    if ( ! $cart_key || ! isset( $cart[ $cart_key ] ) ) wp_send_json_error( array( 'message' => __( 'Cart item not found.', 'luma-commerce-core' ) ), 404 );
    $cart_item = $cart[ $cart_key ];
    $product = isset( $cart_item['data'] ) ? $cart_item['data'] : false;
    if ( ! $product || ! $product->exists() ) wp_send_json_error( array( 'message' => __( 'This item is unavailable.', 'luma-commerce-core' ) ), 400 );
    $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : $product->get_id();
    $variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
    $variation = isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? $cart_item['variation'] : array();
    $saved_key = luma_core_saved_item_key( $product_id, $variation_id, $variation );
    $items = luma_core_saved_items(); $found = false;
    foreach ( $items as &$saved ) {
        $existing_key = ! empty( $saved['key'] ) ? sanitize_key( $saved['key'] ) : luma_core_saved_item_key( isset( $saved['product_id'] ) ? $saved['product_id'] : 0, isset( $saved['variation_id'] ) ? $saved['variation_id'] : 0, isset( $saved['variation'] ) ? $saved['variation'] : array() );
        if ( $existing_key === $saved_key ) { $saved['quantity'] = min( 999, max( 1, absint( $saved['quantity'] ?? 1 ) + absint( $cart_item['quantity'] ) ) ); $saved['key'] = $saved_key; $found = true; break; }
    }
    unset( $saved );
    if ( ! $found ) array_unshift( $items, array( 'key' => $saved_key, 'product_id' => $product_id, 'variation_id' => $variation_id, 'variation' => $variation, 'quantity' => min( 999, max( 1, absint( $cart_item['quantity'] ) ) ) ) );
    luma_core_set_saved_items( $items );
    WC()->cart->remove_cart_item( $cart_key ); WC()->cart->calculate_totals();
    wp_send_json_success( array_merge( luma_core_cart_payload(), array( 'message' => __( 'Saved for later.', 'luma-commerce-core' ) ) ) );
}
add_action( 'wp_ajax_luma_save_for_later', 'luma_core_save_for_later' );
add_action( 'wp_ajax_nopriv_luma_save_for_later', 'luma_core_save_for_later' );

function luma_core_move_saved_to_cart() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_cart_available() || ! luma_core_session_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    $saved_key = isset( $_POST['saved_key'] ) && is_scalar( $_POST['saved_key'] ) ? sanitize_key( wp_unslash( $_POST['saved_key'] ) ) : '';
    $items = luma_core_saved_items(); $match = false; $match_index = -1;
    foreach ( $items as $index => $saved ) {
        $key = ! empty( $saved['key'] ) ? sanitize_key( $saved['key'] ) : luma_core_saved_item_key( isset( $saved['product_id'] ) ? $saved['product_id'] : 0, isset( $saved['variation_id'] ) ? $saved['variation_id'] : 0, isset( $saved['variation'] ) ? $saved['variation'] : array() );
        if ( $saved_key && $key === $saved_key ) { $match = $saved; $match['key'] = $key; $match_index = $index; break; }
    }
    if ( false === $match ) wp_send_json_error( array( 'message' => __( 'Saved item not found.', 'luma-commerce-core' ) ), 404 );
    $product_id = isset( $match['product_id'] ) ? absint( $match['product_id'] ) : 0;
    $variation_id = isset( $match['variation_id'] ) ? absint( $match['variation_id'] ) : 0;
    $variation = isset( $match['variation'] ) && is_array( $match['variation'] ) ? $match['variation'] : array();
    $product = luma_core_public_product( $variation_id ? $variation_id : $product_id );
    $parent = $variation_id ? luma_core_public_product( $product_id ) : $product;
    if ( ! $product || ! $parent || ! $product->is_purchasable() || ! $product->is_in_stock() ) wp_send_json_error( array( 'message' => __( 'This saved piece is no longer available.', 'luma-commerce-core' ) ), 400 );
    $quantity = min( 999, max( 1, absint( $match['quantity'] ?? 1 ) ) );
    if ( ! WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) ) wp_send_json_error( array( 'message' => __( 'This saved piece could not be added.', 'luma-commerce-core' ) ), 400 );
    unset( $items[ $match_index ] ); luma_core_set_saved_items( array_values( $items ) ); WC()->cart->calculate_totals();
    wp_send_json_success( array_merge( luma_core_cart_payload(), array( 'message' => __( 'Moved back to your bag.', 'luma-commerce-core' ) ) ) );
}
add_action( 'wp_ajax_luma_move_saved_to_cart', 'luma_core_move_saved_to_cart' );
add_action( 'wp_ajax_nopriv_luma_move_saved_to_cart', 'luma_core_move_saved_to_cart' );

function luma_core_remove_saved_item() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_session_available() ) wp_send_json_error( array( 'message' => __( 'Saved items are unavailable.', 'luma-commerce-core' ) ), 400 );
    $saved_key = isset( $_POST['saved_key'] ) && is_scalar( $_POST['saved_key'] ) ? sanitize_key( wp_unslash( $_POST['saved_key'] ) ) : '';
    $items = luma_core_saved_items(); $remaining = array(); $removed = false;
    foreach ( $items as $saved ) {
        $key = ! empty( $saved['key'] ) ? sanitize_key( $saved['key'] ) : luma_core_saved_item_key( isset( $saved['product_id'] ) ? $saved['product_id'] : 0, isset( $saved['variation_id'] ) ? $saved['variation_id'] : 0, isset( $saved['variation'] ) ? $saved['variation'] : array() );
        if ( $saved_key && $key === $saved_key ) { $removed = true; continue; }
        $remaining[] = $saved;
    }
    if ( ! $removed ) wp_send_json_error( array( 'message' => __( 'Saved item not found.', 'luma-commerce-core' ) ), 404 );
    luma_core_set_saved_items( $remaining );
    wp_send_json_success( array( 'saved' => luma_core_render_saved_items(), 'message' => __( 'Removed from saved items.', 'luma-commerce-core' ) ) );
}
add_action( 'wp_ajax_luma_remove_saved_item', 'luma_core_remove_saved_item' );
add_action( 'wp_ajax_nopriv_luma_remove_saved_item', 'luma_core_remove_saved_item' );

function luma_core_quick_view() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_option( 'module_quick_view', true ) ) wp_send_json_error( array( 'message' => __( 'Quick view is disabled.', 'luma-commerce-core' ) ), 403 );
    $product = luma_core_public_product( isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0 );
    if ( ! $product ) wp_send_json_error( array( 'message' => __( 'Product not found.', 'luma-commerce-core' ) ), 404 );
    ob_start(); ?>
    <div class="luma-quick-view__image"><?php echo wp_kses_post( $product->get_image( 'woocommerce_single' ) ); ?></div><div class="luma-quick-view__content"><p class="luma-kicker"><?php esc_html_e( 'Quick look', 'luma-commerce-core' ); ?></p><h2><?php echo esc_html( $product->get_name() ); ?></h2><div class="luma-quick-view__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div><div class="luma-quick-view__summary"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div><a class="luma-button" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'View product', 'luma-commerce-core' ); ?> <span>↗</span></a></div>
    <?php wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_luma_quick_view', 'luma_core_quick_view' );
add_action( 'wp_ajax_nopriv_luma_quick_view', 'luma_core_quick_view' );

function luma_core_apply_coupon() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_cart_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    $code = isset( $_POST['coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon'] ) ) : '';
    if ( '' === $code ) wp_send_json_error( array( 'message' => __( 'Enter a coupon code.', 'luma-commerce-core' ) ), 400 );
    $applied = WC()->cart->apply_coupon( $code );
    if ( $applied || WC()->cart->has_discount( $code ) ) wp_send_json_success( array( 'message' => __( 'Offer applied to your bag.', 'luma-commerce-core' ) ) );
    wp_send_json_error( array( 'message' => __( 'This offer is not available for the current bag.', 'luma-commerce-core' ) ), 400 );
}
add_action( 'wp_ajax_luma_apply_coupon', 'luma_core_apply_coupon' );
add_action( 'wp_ajax_nopriv_luma_apply_coupon', 'luma_core_apply_coupon' );

function luma_core_predictive_search() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! function_exists( 'wc_get_products' ) ) wp_send_json_success( array( 'html' => '' ) );
    if ( ! luma_core_option( 'module_search', true ) ) wp_send_json_success( array( 'html' => '' ) );
    $raw_term = isset( $_POST['term'] ) ? wp_unslash( $_POST['term'] ) : '';
    $term = is_string( $raw_term ) ? sanitize_text_field( $raw_term ) : '';
    if ( strlen( $term ) < 2 ) wp_send_json_success( array( 'html' => '' ) );
    $cache_key = 'luma_predictive_' . md5( strtolower( $term ) );
    $cached_html = get_transient( $cache_key );
    if ( false !== $cached_html ) wp_send_json_success( array( 'html' => $cached_html ) );
    $products = wc_get_products( array( 'status' => 'publish', 'limit' => 6, 'search' => $term, 'orderby' => 'relevance' ) );
    $taxonomies = array( 'product_cat', 'product_tag' );
    if ( function_exists( 'wc_get_attribute_taxonomies' ) ) foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) $taxonomies[] = wc_attribute_taxonomy_name( $attribute->attribute_name );
    $related_ids = array();
    foreach ( array_unique( array_filter( $taxonomies, 'taxonomy_exists' ) ) as $taxonomy ) {
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'name__like' => $term, 'number' => 8 ) );
        if ( ! is_wp_error( $terms ) ) foreach ( $terms as $matched_term ) { $objects = get_objects_in_term( $matched_term->term_id, $taxonomy ); if ( ! is_wp_error( $objects ) ) $related_ids = array_merge( $related_ids, (array) $objects ); }
    }
    if ( $related_ids ) $products = array_merge( $products, wc_get_products( array( 'status' => 'publish', 'limit' => 6, 'include' => array_slice( array_unique( array_map( 'absint', $related_ids ) ), 0, 18 ), 'orderby' => 'popularity' ) ) );
    $unique = array(); foreach ( $products as $product ) $unique[ $product->get_id() ] = $product; $products = array_slice( array_values( $unique ), 0, 6 );
    ob_start();
    if ( $products ) foreach ( $products as $product ) echo '<a role="option" class="luma-predictive-result" href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '<span><strong>' . esc_html( $product->get_name() ) . '</strong><small>' . wp_kses_post( $product->get_price_html() ) . ( ! $product->is_in_stock() ? ' · ' . esc_html__( 'Sold out', 'luma-commerce-core' ) : '' ) . '</small></span><b aria-hidden="true">↗</b></a>';
    else echo '<p class="luma-predictive-empty">' . esc_html__( 'No products found.', 'luma-commerce-core' ) . '</p>';
    $view_all = add_query_arg( array( 'post_type' => 'product', 's' => $term ), home_url( '/' ) );
    echo '<a class="luma-predictive-view-all" href="' . esc_url( $view_all ) . '">' . esc_html__( 'View all results', 'luma-commerce-core' ) . ' ↗</a>';
    $html = ob_get_clean();
    set_transient( $cache_key, $html, MINUTE_IN_SECONDS );
    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_luma_predictive_search', 'luma_core_predictive_search' );
add_action( 'wp_ajax_nopriv_luma_predictive_search', 'luma_core_predictive_search' );

function luma_core_compare_table( $products ) {
    if ( ! $products ) return '';
    $attributes = array();
    foreach ( $products as $product ) foreach ( (array) $product->get_attributes() as $attribute ) {
        if ( ! is_object( $attribute ) || ( method_exists( $attribute, 'get_visible' ) && ! $attribute->get_visible() ) ) continue;
        $name = method_exists( $attribute, 'get_name' ) ? $attribute->get_name() : '';
        if ( $name ) $attributes[ $name ] = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name ) : ucwords( str_replace( array( 'pa_', '-' ), array( '', ' ' ), $name ) );
    }
    $html = '<div class="luma-compare-table-wrap"><table class="luma-compare-table"><thead><tr><th scope="col">' . esc_html__( 'Compare pieces', 'luma-commerce-core' ) . '</th>';
    foreach ( $products as $product ) $html .= '<th scope="col" data-compare-product="' . esc_attr( $product->get_id() ) . '"><a href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</a><strong><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></strong><button type="button" class="luma-compare-toggle" data-compare-id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr__( 'Remove from compare', 'luma-commerce-core' ) . '" aria-pressed="true">' . esc_html__( 'Remove', 'luma-commerce-core' ) . '</button></th>';
    $html .= '</tr></thead><tbody>';
    $rows = array( 'price' => __( 'Price', 'luma-commerce-core' ), 'rating' => __( 'Reviews', 'luma-commerce-core' ), 'availability' => __( 'Availability', 'luma-commerce-core' ) );
    foreach ( $attributes as $name => $label ) $rows[ 'attribute_' . sanitize_key( $name ) ] = $label;
    $rows['action'] = __( 'Action', 'luma-commerce-core' );
    foreach ( $rows as $key => $label ) {
        $html .= '<tr><th scope="row">' . esc_html( $label ) . '</th>';
        foreach ( $products as $product ) {
            $value = '';
            if ( 'price' === $key ) $value = wp_kses_post( $product->get_price_html() );
            elseif ( 'rating' === $key ) $value = $product->get_review_count() ? wp_kses_post( wc_get_rating_html( $product->get_average_rating(), $product->get_review_count() ) ) . '<small>' . esc_html( sprintf( _n( '%d review', '%d reviews', (int) $product->get_review_count(), 'luma-commerce-core' ), (int) $product->get_review_count() ) ) . '</small>' : '<span class="luma-compare-muted">' . esc_html__( 'No reviews yet', 'luma-commerce-core' ) . '</span>';
            elseif ( 'availability' === $key ) $value = $product->is_in_stock() ? '<span class="luma-compare-in-stock">' . esc_html__( 'In stock', 'luma-commerce-core' ) . '</span>' : '<span class="luma-compare-out-of-stock">' . esc_html__( 'Sold out', 'luma-commerce-core' ) . '</span>';
            elseif ( 'action' === $key ) $value = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ? '<button class="luma-quick-add" type="button" data-product-id="' . esc_attr( $product->get_id() ) . '">' . esc_html__( 'Quick add', 'luma-commerce-core' ) . ' +</button>' : '<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View piece', 'luma-commerce-core' ) . ' ↗</a>';
            else {
                $attribute_name = substr( $key, 10 );
                foreach ( $attributes as $name => $unused ) if ( sanitize_key( $name ) === $attribute_name ) $value = $product->get_attribute( $name );
                $value = $value ? esc_html( $value ) : '<span class="luma-compare-muted">—</span>';
            }
            $html .= '<td data-compare-product="' . esc_attr( $product->get_id() ) . '">' . $value . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function luma_core_local_collection() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! function_exists( 'wc_get_products' ) ) wp_send_json_success( array( 'html' => '<p class="luma-collection-empty">' . esc_html__( 'The shop is unavailable.', 'luma-commerce-core' ) . '</p>' ) );
    $collection = isset( $_POST['collection'] ) && is_scalar( $_POST['collection'] ) ? sanitize_key( wp_unslash( $_POST['collection'] ) ) : '';
    $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_filter( array_map( 'absint', $_POST['ids'] ) ) ) : array();
    $ids = array_slice( $ids, 0, 'compare' === $collection ? 4 : 12 );
    if ( ! $ids ) wp_send_json_success( array( 'html' => '<p class="luma-collection-empty">' . esc_html__( 'Nothing saved here yet.', 'luma-commerce-core' ) . '</p>' ) );
    $products = wc_get_products( array( 'include' => $ids, 'limit' => 'compare' === $collection ? 4 : 12, 'status' => 'publish' ) );
    $by_id = array(); foreach ( $products as $product ) $by_id[ $product->get_id() ] = $product;
    $ordered_products = array(); foreach ( $ids as $id ) if ( isset( $by_id[ $id ] ) ) $ordered_products[] = $by_id[ $id ];
    if ( 'compare' === $collection ) wp_send_json_success( array( 'html' => $ordered_products ? luma_core_compare_table( $ordered_products ) : '<p class="luma-collection-empty">' . esc_html__( 'Nothing saved here yet.', 'luma-commerce-core' ) . '</p>' ) );
    ob_start(); foreach ( $ordered_products as $product ) echo luma_core_product_mini_card( $product );
    $html = ob_get_clean();
    wp_send_json_success( array( 'html' => $html ? $html : '<p class="luma-collection-empty">' . esc_html__( 'Nothing saved here yet.', 'luma-commerce-core' ) . '</p>' ) );
}
add_action( 'wp_ajax_luma_local_collection', 'luma_core_local_collection' );
add_action( 'wp_ajax_nopriv_luma_local_collection', 'luma_core_local_collection' );

function luma_core_sync_collection() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => __( 'Login required.', 'luma-commerce-core' ) ), 401 );
    if ( ! isset( $_POST['collection'] ) || ! is_scalar( $_POST['collection'] ) ) wp_send_json_error( array( 'message' => __( 'Invalid collection.', 'luma-commerce-core' ) ), 400 );
    $collection = sanitize_key( wp_unslash( $_POST['collection'] ) );
    if ( ! in_array( $collection, array( 'wishlist', 'compare' ), true ) ) wp_send_json_error( array( 'message' => __( 'Invalid collection.', 'luma-commerce-core' ) ), 400 );
    $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_slice( array_filter( array_map( 'absint', $_POST['ids'] ) ), 0, 20 ) ) : array();
    update_user_meta( get_current_user_id(), 'luma_' . $collection, $ids );
    wp_send_json_success( array( 'ids' => $ids ) );
}
add_action( 'wp_ajax_luma_sync_collection', 'luma_core_sync_collection' );

function luma_core_recommendation_card( $product ) {
    if ( ! $product ) return '';
    $badge = $product->is_on_sale() ? __( 'Sale', 'luma-commerce-core' ) : ( (int) $product->get_total_sales() > 0 ? __( 'Popular', 'luma-commerce-core' ) : '' );
    $rating = $product->get_review_count() ? '<div class="luma-loop-rating"><span aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'luma-commerce-core' ), number_format_i18n( (float) $product->get_average_rating(), 1 ) ) ) . '">' . wp_kses_post( wc_get_rating_html( $product->get_average_rating(), $product->get_review_count() ) ) . '</span></div>' : '';
    $action = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ? '<button class="luma-quick-add luma-recommendation__quick-add" type="button" data-product-id="' . esc_attr( $product->get_id() ) . '" aria-label="' . esc_attr( sprintf( __( 'Quick add %s', 'luma-commerce-core' ), $product->get_name() ) ) . '">' . esc_html__( 'Quick add', 'luma-commerce-core' ) . ' +</button>' : '<a class="luma-recommendation__view" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View piece', 'luma-commerce-core' ) . ' ↗</a>';
    return '<article class="luma-recommendation" data-product-id="' . esc_attr( $product->get_id() ) . '"><a class="luma-recommendation__image" href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . ( $badge ? '<span>' . esc_html( $badge ) . '</span>' : '' ) . '</a><h3><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3><div>' . wp_kses_post( $product->get_price_html() ) . '</div>' . $rating . '<div class="luma-recommendation__action">' . $action . '</div></article>';
}

function luma_core_recommendations_shortcode( $atts ) {
    if ( ! function_exists( 'wc_get_products' ) ) return '';
    $atts = shortcode_atts( array( 'title' => 'You may also like', 'limit' => 4 ), $atts, 'luma_recommendations' );
    $limit = max( 1, min( 8, absint( $atts['limit'] ) ) );
    global $product;
    $categories = ( is_product() && $product ) ? wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) ) : array();
    if ( is_wp_error( $categories ) ) $categories = array();
    $products = ( is_product() && $product ) ? wc_get_products( array( 'status' => 'publish', 'limit' => $limit, 'exclude' => array( $product->get_id() ), 'category' => $categories, 'orderby' => 'popularity' ) ) : wc_get_products( array( 'status' => 'publish', 'limit' => $limit, 'orderby' => 'popularity' ) );
    if ( ! $products && is_product() && $product ) $products = wc_get_products( array( 'status' => 'publish', 'limit' => $limit, 'exclude' => array( $product->get_id() ), 'orderby' => 'popularity' ) );
    if ( ! $products ) return '';
    $html = '<section class="luma-recommendations"><div class="luma-recommendations__heading"><p class="luma-kicker">The next piece</p><h2>' . esc_html( $atts['title'] ) . '</h2></div><div class="luma-recommendations__grid">';
    foreach ( $products as $item ) $html .= luma_core_recommendation_card( $item );
    return $html . '</div></section>';
}
add_shortcode( 'luma_recommendations', 'luma_core_recommendations_shortcode' );

function luma_core_single_recommendations() {
    if ( is_product() ) echo do_shortcode( '[luma_recommendations]' );
}
add_action( 'woocommerce_after_single_product_summary', 'luma_core_single_recommendations', 25 );



function luma_core_review_summary() {
    global $product;
    if ( ! $product || ! $product->get_review_count() ) return;
    $count = (int) $product->get_review_count();
    $average = (float) $product->get_average_rating();
    echo '<div class="luma-review-summary"><span class="luma-review-summary__stars" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'luma-commerce-core' ), number_format_i18n( $average, 1 ) ) ) . '">' . wp_kses_post( wc_get_rating_html( $average, $count ) ) . '</span><a href="#reviews">' . esc_html( sprintf( _n( '%d customer review', '%d customer reviews', $count, 'luma-commerce-core' ), $count ) ) . '</a></div>';
}
add_action( 'woocommerce_after_single_product_summary', 'luma_core_review_summary', 8 );

function luma_core_products_from_skus( $value ) {
    if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) return array();
    $skus = is_array( $value ) ? $value : preg_split( '/[,\\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
    $products = array();
    foreach ( $skus as $sku ) { $id = wc_get_product_id_by_sku( sanitize_text_field( $sku ) ); if ( $id ) { $item = luma_core_public_product( $id ); if ( $item ) $products[] = $item; } }
    return $products;
}

function luma_core_bundle_shortcode() {
    if ( ! class_exists( 'WooCommerce' ) || ! luma_core_option( 'bundle_enabled', true ) ) return '';
    global $product;
    if ( ! $product ) return '';
    $products = array();
    foreach ( luma_core_products_from_skus( luma_core_option( 'bundle_skus', '' ) ) as $item ) {
        if ( $item->get_id() !== $product->get_id() && $item->is_type( 'simple' ) && $item->is_purchasable() && $item->is_in_stock() ) $products[] = $item;
    }
    if ( ! $products ) return '';
    ob_start(); ?>
    <section class="luma-bundle" data-luma-bundle>
        <div class="luma-bundle__heading"><div><p class="luma-kicker"><?php esc_html_e( 'Styled together', 'luma-commerce-core' ); ?></p><h3><?php echo esc_html( luma_core_option( 'bundle_title', 'Complete the look' ) ); ?></h3><p><?php echo esc_html( luma_core_option( 'bundle_copy', 'The finishing pieces, selected for this edit.' ) ); ?></p></div><span class="luma-bundle__count"><?php echo esc_html( count( $products ) ); ?> <?php esc_html_e( 'pieces', 'luma-commerce-core' ); ?></span></div>
        <div class="luma-bundle__items">
        <?php foreach ( $products as $item ) : ?><label class="luma-bundle__item"><input type="checkbox" value="<?php echo esc_attr( $item->get_id() ); ?>" checked><span class="luma-bundle__check">✓</span><?php echo wp_kses_post( $item->get_image( 'woocommerce_thumbnail' ) ); ?><span class="luma-bundle__details"><strong><?php echo esc_html( $item->get_name() ); ?></strong><small><?php echo wp_kses_post( $item->get_price_html() ); ?></small></span></label><?php endforeach; ?>
        </div><button type="button" class="luma-add-bundle"><?php esc_html_e( 'Add all to bag', 'luma-commerce-core' ); ?> <span>↗</span></button><span class="luma-bundle-status" aria-live="polite"></span>
    </section>
    <?php return ob_get_clean();
}
add_shortcode( 'luma_bundle', 'luma_core_bundle_shortcode' );

function luma_core_single_bundle() { if ( is_product() ) echo do_shortcode( '[luma_bundle]' ); }
add_action( 'woocommerce_after_add_to_cart_form', 'luma_core_single_bundle', 18 );

function luma_core_add_bundle() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_option( 'bundle_enabled', true ) || ! luma_core_cart_available() ) wp_send_json_error( array( 'message' => __( 'Bundles are unavailable.', 'luma-commerce-core' ) ), 400 );
    $requested = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? array_slice( array_filter( array_map( 'absint', $_POST['product_ids'] ) ), 0, 6 ) : array();
    $allowed = array(); foreach ( luma_core_products_from_skus( luma_core_option( 'bundle_skus', '' ) ) as $allowed_product ) $allowed[] = $allowed_product->get_id();
    $added = 0;
    foreach ( $requested as $id ) {
        if ( ! in_array( $id, $allowed, true ) ) continue;
        $item = luma_core_public_product( $id );
        if ( ! $item || ! $item->is_type( 'simple' ) || ! $item->is_purchasable() || ! $item->is_in_stock() ) continue;
        if ( WC()->cart->add_to_cart( $id, 1 ) ) $added++;
    }
    if ( ! $added ) wp_send_json_error( array( 'message' => __( 'Choose an available piece to add.', 'luma-commerce-core' ) ), 400 );
    WC()->cart->calculate_totals();
    wp_send_json_success( array_merge( luma_core_cart_payload(), array( 'added' => $added, 'message' => sprintf( _n( '%d piece added to your bag.', '%d pieces added to your bag.', $added, 'luma-commerce-core' ), $added ) ) ) );
}
add_action( 'wp_ajax_luma_add_bundle', 'luma_core_add_bundle' );
add_action( 'wp_ajax_nopriv_luma_add_bundle', 'luma_core_add_bundle' );

function luma_core_order_bump_candidate() {
    if ( ! luma_core_option( 'order_bump_enabled', true ) ) return false;
    $products = luma_core_products_from_skus( luma_core_option( 'order_bump_sku', '' ) );
    $item = $products ? $products[0] : false;
    return ( $item && $item->is_type( 'simple' ) && $item->is_purchasable() && $item->is_in_stock() ) ? $item : false;
}

function luma_core_order_bump_product() {
    $item = luma_core_order_bump_candidate();
    if ( ! $item ) return false;
    if ( luma_core_cart_available() ) foreach ( WC()->cart->get_cart() as $cart_item ) if ( ! empty( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === $item->get_id() ) return false;
    return $item;
}

function luma_core_order_bump_shortcode() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! luma_core_cart_available() ) return '';
    $item = luma_core_order_bump_product();
    if ( ! $item ) return '';
    return '<div class="luma-order-bump" data-luma-order-bump data-product-id="' . esc_attr( $item->get_id() ) . '"><label><input type="checkbox" class="luma-order-bump__toggle"><span><strong>' . esc_html( luma_core_option( 'order_bump_title', 'Add a finishing detail' ) ) . '</strong><small>' . esc_html( luma_core_option( 'order_bump_copy', 'Complete your rotation with a small extra.' ) ) . '</small></span><b>+' . wp_kses_post( $item->get_price_html() ) . '</b></label><span class="luma-order-bump__status" aria-live="polite"></span></div>';
}
add_shortcode( 'luma_order_bump', 'luma_core_order_bump_shortcode' );
add_action( 'woocommerce_review_order_before_payment', function() { echo do_shortcode( '[luma_order_bump]' ); }, 9 );

function luma_core_toggle_order_bump() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! luma_core_cart_available() ) wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'luma-commerce-core' ) ), 400 );
    $item = luma_core_order_bump_candidate();
    $id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $add = ! empty( $_POST['add'] );
    if ( ! $item || $id !== $item->get_id() ) wp_send_json_error( array( 'message' => __( 'This add-on is unavailable.', 'luma-commerce-core' ) ), 400 );
    if ( $add ) { if ( ! WC()->cart->add_to_cart( $id, 1 ) ) wp_send_json_error( array( 'message' => __( 'Could not add the add-on.', 'luma-commerce-core' ) ), 400 ); }
    else foreach ( WC()->cart->get_cart() as $key => $cart_item ) if ( ! empty( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === $id ) WC()->cart->remove_cart_item( $key );
    WC()->cart->calculate_totals();
    wp_send_json_success( array( 'message' => $add ? 'Add-on added.' : 'Add-on removed.', 'subtotal' => WC()->cart->get_cart_subtotal(), 'count' => WC()->cart->get_cart_contents_count() ) );
}
add_action( 'wp_ajax_luma_toggle_order_bump', 'luma_core_toggle_order_bump' );
add_action( 'wp_ajax_nopriv_luma_toggle_order_bump', 'luma_core_toggle_order_bump' );

/**
 * One-click dummy store installer. It creates safe demo products/pages/menu items
 * that can be edited or deleted like normal WordPress content.
 */
function luma_core_demo_categories() {
    $names = array( 'Men', 'Women', 'Juniors', 'New In', 'Sale', 'Outerwear', 'T-shirts', 'Bottoms', 'Accessories' );
    $ids = array();
    foreach ( $names as $name ) {
        $term = term_exists( $name, 'product_cat' );
        if ( ! $term ) $term = wp_insert_term( $name, 'product_cat', array( 'slug' => sanitize_title( $name ) ) );
        if ( ! is_wp_error( $term ) ) $ids[ sanitize_title( $name ) ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
    }
    return $ids;
}

function luma_core_demo_image( $filename ) {
    $source = function_exists( 'get_theme_file_path' ) ? get_theme_file_path( 'assets/images/' . $filename ) : '';
    if ( ! $source || ! file_exists( $source ) ) return 0;
    $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'OR', array( 'key' => '_luma_demo_image', 'value' => $filename ), array( 'key' => '_luma_demo_image_source', 'value' => $filename ) ) ) );
    if ( $existing ) return (int) $existing[0];
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $source_filename = $filename;
    $upload = wp_upload_dir();
    $filename = wp_unique_filename( $upload['path'], $filename );
    $destination = trailingslashit( $upload['path'] ) . $filename;
    if ( ! copy( $source, $destination ) ) return 0;
    $filetype = wp_check_filetype( $filename, null );
    $attachment_id = wp_insert_attachment( array( 'post_mime_type' => $filetype['type'], 'post_title' => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ), 'post_status' => 'inherit' ), $destination );
    if ( is_wp_error( $attachment_id ) ) return 0;
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $destination ) );
    update_post_meta( $attachment_id, '_luma_demo_image', $filename );
    update_post_meta( $attachment_id, '_luma_demo_image_source', $source_filename );
    return (int) $attachment_id;
}

function luma_core_demo_products() {
    return array(
        array( 'type' => 'variable', 'name' => 'Utility Overshirt', 'sku' => 'LUMA-DEMO-001', 'price' => '5490', 'sale' => '', 'cat' => 'outerwear', 'gender' => 'Men', 'image' => 'luma-product-overshirt.jpg' ),
        array( 'name' => 'Boxy Heavyweight Tee', 'sku' => 'LUMA-DEMO-002', 'price' => '3490', 'sale' => '2990', 'cat' => 't-shirts', 'gender' => 'Men', 'image' => 'luma-product-tee.jpg' ),
        array( 'name' => 'Wide Leg Cargo', 'sku' => 'LUMA-DEMO-003', 'price' => '6990', 'sale' => '', 'cat' => 'bottoms', 'gender' => 'Men', 'image' => 'luma-product-cargo.jpg' ),
        array( 'type' => 'variable', 'name' => 'Everyday Zip Jacket', 'sku' => 'LUMA-DEMO-004', 'price' => '7990', 'sale' => '', 'cat' => 'outerwear', 'gender' => 'Women', 'image' => 'luma-product-jacket.jpg' ),
        array( 'name' => 'Cropped Utility Tee', 'sku' => 'LUMA-DEMO-005', 'price' => '2990', 'sale' => '2490', 'cat' => 't-shirts', 'gender' => 'Women', 'image' => 'luma-product-tee.jpg' ),
        array( 'name' => 'Relaxed Carpenter Pant', 'sku' => 'LUMA-DEMO-006', 'price' => '5990', 'sale' => '', 'cat' => 'bottoms', 'gender' => 'Women', 'image' => 'luma-product-cargo.jpg' ),
        array( 'type' => 'variable', 'name' => 'Signal Graphic Tee', 'sku' => 'LUMA-DEMO-007', 'price' => '2490', 'sale' => '', 'cat' => 't-shirts', 'gender' => 'Juniors', 'image' => 'luma-product-tee.jpg' ),
        array( 'name' => 'Track Shell', 'sku' => 'LUMA-DEMO-008', 'price' => '6490', 'sale' => '', 'cat' => 'outerwear', 'gender' => 'Juniors', 'image' => 'luma-product-jacket.jpg' ),
        array( 'name' => 'Low Profile Cap', 'sku' => 'LUMA-DEMO-009', 'price' => '1990', 'sale' => '', 'cat' => 'accessories', 'gender' => 'New In', 'image' => 'luma-product-accessories.jpg' ),
        array( 'name' => 'Canvas Crossbody', 'sku' => 'LUMA-DEMO-010', 'price' => '2990', 'sale' => '', 'cat' => 'accessories', 'gender' => 'New In', 'image' => 'luma-product-accessories.jpg' ),
        array( 'name' => 'Contrast Sweatshirt', 'sku' => 'LUMA-DEMO-011', 'price' => '4990', 'sale' => '3990', 'cat' => 't-shirts', 'gender' => 'Sale', 'image' => 'luma-product-hoodie.jpg' ),
        array( 'name' => 'Night Shift Hoodie', 'sku' => 'LUMA-DEMO-012', 'price' => '5990', 'sale' => '4490', 'cat' => 'outerwear', 'gender' => 'Sale', 'image' => 'luma-product-hoodie.jpg' ),
    );
}

function luma_core_demo_post_id( $slug, $post_type = 'page', $title = '' ) {
    $marked = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_luma_demo_page', 'meta_value' => $slug ) );
    if ( $marked ) return (int) $marked[0];
    $post = get_page_by_path( $slug, OBJECT, $post_type );
    return $post && ( '' === $title || $post->post_title === $title ) ? (int) $post->ID : 0;
}

function luma_core_demo_pages( $image_urls = array() ) {
    $about_image = ! empty( $image_urls['luma-about.jpg'] ) ? $image_urls['luma-about.jpg'] : ( function_exists( 'get_theme_file_uri' ) ? get_theme_file_uri( 'assets/images/luma-about.jpg' ) : '' );
    $contact_image = ! empty( $image_urls['luma-contact.jpg'] ) ? $image_urls['luma-contact.jpg'] : ( function_exists( 'get_theme_file_uri' ) ? get_theme_file_uri( 'assets/images/luma-contact.jpg' ) : '' );
    $detail_image = ! empty( $image_urls['luma-detail.jpg'] ) ? $image_urls['luma-detail.jpg'] : ( function_exists( 'get_theme_file_uri' ) ? get_theme_file_uri( 'assets/images/luma-detail.jpg' ) : '' );
    $pages = array(
        array( 'title' => 'Home', 'slug' => 'home', 'content' => '' ),
        array( 'title' => 'About Luma', 'slug' => 'about-luma', 'content' => '<div class="luma-page-hero"><img src="' . esc_url( $about_image ) . '" alt="Luma studio collection"><p class="luma-kicker">The Luma point of view</p><h1>Made for<br>everyday<br>motion.</h1><p>We make clothes for the pace of real life: considered layers, relaxed shapes and details that earn their place in your wardrobe.</p></div><div class="luma-page-copy"><h2>Quietly confident.</h2><p>Luma is a study in balance. Utility meets ease. Texture meets structure. Every piece is designed to feel familiar on the first wear and better with time.</p><div class="luma-page-columns"><div><strong>01 / Move</strong><p>Relaxed proportions and practical details that keep up with a changing day.</p></div><div><strong>02 / Make</strong><p>Thoughtful fabrics, durable construction and a focus on the way a garment feels.</p></div><div><strong>03 / Repeat</strong><p>Pieces made to be worn often, styled freely and kept close for longer.</p></div></div></div>' ),
        array( 'title' => 'Shop', 'slug' => 'shop', 'content' => '' ),
        array( 'title' => 'Wishlist', 'slug' => 'wishlist', 'content' => '[luma_wishlist]' ),
        array( 'title' => 'Compare', 'slug' => 'compare', 'content' => '[luma_compare]' ),
        array( 'title' => 'Recently Viewed', 'slug' => 'recently-viewed', 'content' => '[luma_recently_viewed]' ),
        array( 'title' => 'Sale', 'slug' => 'sale', 'content' => '[luma_sale_bar]' . PHP_EOL . '[luma_countdown]' . PHP_EOL . '[products on_sale="true" columns="4" limit="12"]' ),
        array( 'title' => 'Size Guide', 'slug' => 'size-guide', 'content' => '<img class="luma-page-wide-image" src="' . esc_url( $detail_image ) . '" alt="Luma fabric detail"><h1>Find your fit.</h1><p>Our shapes are designed with room to move. Measure around the fullest part of your chest, waist and hips, then compare with the table below.</p>[luma_size_guide]' ),
        array( 'title' => 'Contact', 'slug' => 'contact', 'content' => '<div class="luma-page-hero"><img src="' . esc_url( $contact_image ) . '" alt="Luma studio team"><p class="luma-kicker">We are here to help</p><h1>Let\'s talk.</h1><p>Questions about fit, an order or finding the right piece? Send us a message and our team will get back to you within one business day.</p></div><div class="luma-page-columns"><div><strong>Customer care</strong><p>Monday–Saturday<br>10:00–18:00</p></div><div><strong>Fit advice</strong><p>Tell us what you usually wear and how you like your clothes to fit.</p></div><div><strong>Order help</strong><p>Keep your order number nearby so we can help you faster.</p></div></div>' ),
        array( 'title' => 'Shipping & Returns', 'slug' => 'shipping-returns', 'content' => '<h1>Shipping & returns.</h1><div class="luma-page-columns"><div><strong>Delivery</strong><p>Orders are carefully checked and prepared before dispatch. Delivery timing and charges are shown at checkout for your address.</p></div><div><strong>Exchanges</strong><p>If your fit is not right, contact customer care with your order number and we will guide you through the available exchange options.</p></div><div><strong>Condition</strong><p>Items should be unworn, unwashed and returned with original tags attached.</p></div></div><p class="luma-page-note">For help with a delivery or exchange, contact customer care and keep your order number nearby.</p>' ),
        array( 'title' => 'FAQs', 'slug' => 'faqs', 'content' => '<h1>Questions,<br>answered.</h1><details open><summary>How do Luma pieces fit?</summary><p>Our core collection has a relaxed, easy fit. Check the product notes and size guide for the best choice.</p></details><details><summary>Can I change my order?</summary><p>Contact customer care as soon as possible. We will try to help before the order is packed.</p></details><details><summary>How do I care for my pieces?</summary><p>Follow the care label on each garment. Wash thoughtfully, avoid unnecessary heat and allow pieces to dry naturally when possible.</p></details><details><summary>How can I track delivery?</summary><p>Use the tracking link shared after dispatch or visit the Track Order page.</p></details>' ),
        array( 'title' => 'Track Order', 'slug' => 'track-order', 'content' => '<h1>Track your<br>order.</h1><p>Enter the tracking information shared in your dispatch message to follow your delivery. Need help? Our customer care team is ready to assist.</p><form class="luma-track-box" data-luma-track-form><label>Order number<input name="order_id" type="number" min="1" required placeholder="e.g. 1042"></label><label>Email address<input name="email" type="email" required placeholder="you@example.com"></label><button class="luma-button luma-track-submit" type="submit">Find order <span>↗</span></button><p class="luma-track-status" aria-live="polite"></p></form>' ),
        array( 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'content' => '<h1>Privacy<br>policy.</h1><p>We collect only the information needed to process orders, provide support and improve the Luma experience. We do not sell personal information. We use order and contact information only to process purchases, provide support and improve the Luma experience. You may contact the store team to ask what information is held about your order.</p>' ),
        array( 'title' => 'Terms & Conditions', 'slug' => 'terms-conditions', 'content' => '<h1>Terms &<br>conditions.</h1><p>Orders are placed in good faith and processed after payment or confirmation. Pricing, delivery, payment and exchange details are shown at checkout and form part of your order agreement.</p>' ),
    );
    $ids = array();
    foreach ( $pages as $page ) {
        $id = luma_core_demo_post_id( $page['slug'], 'page', $page['title'] );
        $data = array( 'post_title' => $page['title'], 'post_name' => $page['slug'], 'post_content' => $page['content'], 'post_status' => 'publish', 'post_type' => 'page' );
        $id ? $id = wp_update_post( array_merge( $data, array( 'ID' => $id ) ), true ) : $id = wp_insert_post( $data, true );
        if ( $id && ! is_wp_error( $id ) ) { update_post_meta( $id, '_luma_demo_page', $page['slug'] ); $ids[ $page['slug'] ] = (int) $id; }
    }
    update_option( 'show_on_front', 'page' );
    if ( ! empty( $ids['home'] ) ) update_option( 'page_on_front', $ids['home'] );
    if ( ! empty( $ids['shop'] ) && function_exists( 'wc_get_page_id' ) ) update_option( 'woocommerce_shop_page_id', $ids['shop'] );
    return $ids;
}

function luma_core_demo_menu( $pages ) {
    $menu = wp_get_nav_menu_object( 'Luma Main Menu' );
    $menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( 'Luma Main Menu' );
    if ( ! $menu_id ) return;
    $items = wp_get_nav_menu_items( $menu_id );
    if ( ! $items ) {
        $links = array( 'New In' => array( 'term' => 'new-in' ), 'Men' => array( 'term' => 'men' ), 'Women' => array( 'term' => 'women' ), 'Juniors' => array( 'term' => 'juniors' ), 'Sale' => array( 'page' => 'sale' ) );
        foreach ( $links as $title => $link ) {
            $object_id = 0; $type = 'custom'; $url = home_url( '/' );
            if ( ! empty( $link['term'] ) ) { $term = get_term_by( 'slug', $link['term'], 'product_cat' ); if ( $term ) { $object_id = $term->term_id; $type = 'taxonomy'; $url = get_term_link( $term ); } }
            if ( ! empty( $link['page'] ) && isset( $pages[ $link['page'] ] ) ) { $object_id = $pages[ $link['page'] ]; $type = 'post_type'; $url = get_permalink( $object_id ); }
            if ( is_wp_error( $url ) ) $url = home_url( '/' );
            wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $title, 'menu-item-url' => $url, 'menu-item-object-id' => $object_id, 'menu-item-object' => 'taxonomy' === $type ? 'product_cat' : 'page', 'menu-item-type' => $type, 'menu-item-status' => 'publish' ) );
        }
    }
    $locations = get_theme_mod( 'nav_menu_locations', array() );
    $locations['primary'] = $menu_id; $locations['footer'] = $menu_id;
    set_theme_mod( 'nav_menu_locations', $locations );
}

function luma_core_demo_variations( $product, $item ) {
    if ( ! $product || ! $product->is_type( 'variable' ) ) return;
    foreach ( $product->get_children() as $variation_id ) wp_delete_post( $variation_id, true );
    $sizes = array( 'S', 'M', 'L', 'XL' );
    $colors = array( 'Black', 'Stone' );
    $attributes = array();
    $size_attribute = new WC_Product_Attribute(); $size_attribute->set_name( 'Size' ); $size_attribute->set_options( $sizes ); $size_attribute->set_position( 0 ); $size_attribute->set_visible( true ); $size_attribute->set_variation( true ); $attributes['size'] = $size_attribute;
    $color_attribute = new WC_Product_Attribute(); $color_attribute->set_name( 'Color' ); $color_attribute->set_options( $colors ); $color_attribute->set_position( 1 ); $color_attribute->set_visible( true ); $color_attribute->set_variation( true ); $attributes['color'] = $color_attribute;
    $product->set_attributes( $attributes ); $product->set_default_attributes( array( 'size' => 'M', 'color' => 'Black' ) ); $product->save();
    foreach ( $sizes as $size ) foreach ( $colors as $color ) {
        $variation = new WC_Product_Variation(); $variation->set_parent_id( $product->get_id() ); $variation->set_attributes( array( 'size' => $size, 'color' => $color ) ); $variation->set_regular_price( $item['price'] ); if ( $item['sale'] ) $variation->set_sale_price( $item['sale'] ); $variation->set_status( 'publish' ); $variation->set_manage_stock( false ); $variation->set_stock_quantity( null ); $variation->set_stock_status( 'instock' ); $variation->save();
    }
}

function luma_core_demo_coupon() {
    $coupons = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'any', 'posts_per_page' => 1, 'title' => 'LUMA10' ) );
    if ( ! $coupons ) { $coupon_id = wp_insert_post( array( 'post_title' => 'LUMA10', 'post_status' => 'publish', 'post_type' => 'shop_coupon' ) ); } else $coupon_id = $coupons[0]->ID;
    if ( ! $coupon_id || is_wp_error( $coupon_id ) ) return '';
    update_post_meta( $coupon_id, 'discount_type', 'percent' ); update_post_meta( $coupon_id, 'coupon_amount', '10' ); update_post_meta( $coupon_id, 'individual_use', 'no' ); update_post_meta( $coupon_id, 'usage_limit', '0' ); update_post_meta( $coupon_id, 'free_shipping', 'no' );
    return 'LUMA10';
}

function luma_core_demo_unique_sku( $desired_sku, $product_id = 0 ) {
    $desired_sku = sanitize_text_field( $desired_sku );
    if ( '' === $desired_sku || ! function_exists( 'wc_get_product_id_by_sku' ) ) return $desired_sku;
    for ( $attempt = 0; $attempt < 100; $attempt++ ) {
        $candidate = 0 === $attempt ? $desired_sku : $desired_sku . '-LUMA-' . $attempt;
        $existing_id = absint( wc_get_product_id_by_sku( $candidate ) );
        if ( ! $existing_id || $existing_id === absint( $product_id ) ) return $candidate;
    }
    return $desired_sku . '-LUMA-' . strtolower( wp_generate_password( 6, false, false ) );
}

function luma_core_demo_set_sku( $product, $desired_sku ) {
    if ( ! $product ) return '';
    $assigned_sku = luma_core_demo_unique_sku( $desired_sku, $product->get_id() );
    try {
        $product->set_sku( $assigned_sku );
        return $assigned_sku;
    } catch ( Throwable $error ) {
        for ( $attempt = 1; $attempt < 100; $attempt++ ) {
            $fallback_sku = sanitize_text_field( $desired_sku ) . '-LUMA-' . $attempt;
            try {
                $product->set_sku( $fallback_sku );
                return $fallback_sku;
            } catch ( Throwable $fallback_error ) {
                continue;
            }
        }
        $product->set_sku( '' );
        return '';
    }
}

function luma_core_install_demo_store() {
    if ( ! class_exists( 'WooCommerce' ) || ! current_user_can( 'manage_woocommerce' ) ) return array( 'products' => 0, 'pages' => 0 );
    $categories = luma_core_demo_categories();
    $image_ids = array();
    $demo_image_files = array( 'luma-hero.jpg', 'luma-men.jpg', 'luma-women.jpg', 'luma-drop.jpg', 'luma-about.jpg', 'luma-contact.jpg', 'luma-detail.jpg', 'luma-product-overshirt.jpg', 'luma-product-tee.jpg', 'luma-product-cargo.jpg', 'luma-product-jacket.jpg', 'luma-product-hoodie.jpg', 'luma-product-accessories.jpg' );
    foreach ( $demo_image_files as $filename ) $image_ids[ $filename ] = luma_core_demo_image( $filename );
    $image_urls = array(); foreach ( $image_ids as $filename => $attachment_id ) if ( $attachment_id ) $image_urls[ $filename ] = wp_get_attachment_url( $attachment_id );
    $created_products = 0;
    $demo_skus       = array();
    foreach ( luma_core_demo_products() as $item ) {
        $existing = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => '_luma_demo_sku', 'meta_value' => $item['sku'], 'fields' => 'ids' ) );
        $sku_product_id = function_exists( 'wc_get_product_id_by_sku' ) ? absint( wc_get_product_id_by_sku( $item['sku'] ) ) : 0;
        if ( ! $existing && $sku_product_id ) {
            $sku_product = wc_get_product( $sku_product_id );
            $is_matching_demo = $sku_product && ( $item['sku'] === get_post_meta( $sku_product_id, '_luma_demo_sku', true ) || $item['name'] === $sku_product->get_name() );
            if ( $is_matching_demo ) $existing = array( $sku_product_id );
        }
        $is_variable = ! empty( $item['type'] ) && 'variable' === $item['type'];
        $product = $existing ? wc_get_product( $existing[0] ) : ( $is_variable ? new WC_Product_Variable() : new WC_Product_Simple() );
        if ( $product && $is_variable && ! $product->is_type( 'variable' ) ) { wp_delete_post( $product->get_id(), true ); $product = new WC_Product_Variable(); }
        if ( ! $product ) continue;
        $assigned_sku = luma_core_demo_set_sku( $product, $item['sku'] );
        $product->set_name( $item['name'] ); $product->set_status( 'publish' );
        $product->set_regular_price( $item['price'] );
        if ( $item['sale'] ) $product->set_sale_price( $item['sale'] ); else $product->set_sale_price( '' );
        $product->set_description( '<p>' . esc_html( $item['name'] ) . ' is cut for an easy, everyday silhouette with considered utility details. Designed to layer, move and live in, it brings a confident finish to the daily uniform.</p><ul><li>Relaxed Luma fit</li><li>Comfort-first construction</li><li>Easy to style across the week</li></ul>' );
        $product->set_short_description( 'An easy Luma essential with a relaxed fit and everyday versatility.' );
        $is_sold_out = ! empty( $item['soldout'] );
        $product->set_manage_stock( $is_sold_out );
        $product->set_stock_quantity( $is_sold_out ? 0 : null );
        $product->set_stock_status( $is_sold_out ? 'outofstock' : 'instock' );
        $product->set_category_ids( array_filter( array( $categories[ sanitize_title( $item['gender'] ) ] ?? 0, $categories[ $item['cat'] ] ?? 0, $item['sale'] ? ( $categories['sale'] ?? 0 ) : 0 ) ) );
        if ( ! empty( $image_ids[ $item['image'] ] ) ) $product->set_image_id( $image_ids[ $item['image'] ] );
        try {
            $product->save();
        } catch ( Throwable $error ) {
            // A concurrent or stale SKU collision must never take down wp-admin.
            $assigned_sku = luma_core_demo_set_sku( $product, $item['sku'] . '-LUMA' );
            $product->save();
        }
        if ( $is_variable ) luma_core_demo_variations( $product, $item );
        update_post_meta( $product->get_id(), '_luma_demo_sku', $item['sku'] );
        $demo_skus[ $item['sku'] ] = $assigned_sku;
        $created_products++;
    }
    $pages = luma_core_demo_pages( $image_urls ); luma_core_demo_menu( $pages ); set_theme_mod( 'luma_brand_name', 'Luma' );
    $settings = get_option( 'luma_core_settings', array() );
    if ( empty( $settings['coupon_code'] ) ) $settings['coupon_code'] = luma_core_demo_coupon();
    if ( empty( $settings['coupon_text'] ) ) $settings['coupon_text'] = 'Take 10% off your first Luma edit';
    if ( ! isset( $settings['popup_enabled'] ) ) $settings['popup_enabled'] = true;
    if ( empty( $settings['popup_title'] ) ) $settings['popup_title'] = 'Your first Luma move';
    if ( empty( $settings['popup_text'] ) ) $settings['popup_text'] = 'Join the edit and unlock your first-order offer.';
    if ( ! isset( $settings['popup_delay'] ) ) $settings['popup_delay'] = 8;
    if ( ! isset( $settings['analytics_enabled'] ) ) $settings['analytics_enabled'] = false;
    if ( ! isset( $settings['bundle_enabled'] ) ) $settings['bundle_enabled'] = true;
    $bundle_demo_skus = array( 'LUMA-DEMO-002', 'LUMA-DEMO-003', 'LUMA-DEMO-009' );
    $assigned_bundle_skus = array_map( function( $sku ) use ( $demo_skus ) { return isset( $demo_skus[ $sku ] ) ? $demo_skus[ $sku ] : $sku; }, $bundle_demo_skus );
    if ( empty( $settings['bundle_skus'] ) || 'LUMA-DEMO-002, LUMA-DEMO-003, LUMA-DEMO-009' === $settings['bundle_skus'] ) $settings['bundle_skus'] = implode( ', ', $assigned_bundle_skus );
    if ( empty( $settings['bundle_title'] ) ) $settings['bundle_title'] = 'Complete the look';
    if ( empty( $settings['bundle_copy'] ) ) $settings['bundle_copy'] = 'The finishing pieces, selected for this edit.';
    if ( ! isset( $settings['order_bump_enabled'] ) ) $settings['order_bump_enabled'] = true;
    if ( empty( $settings['order_bump_sku'] ) || 'LUMA-DEMO-009' === $settings['order_bump_sku'] ) $settings['order_bump_sku'] = $demo_skus['LUMA-DEMO-009'] ?? 'LUMA-DEMO-009';
    if ( empty( $settings['order_bump_title'] ) ) $settings['order_bump_title'] = 'Add a finishing detail';
    if ( empty( $settings['order_bump_copy'] ) ) $settings['order_bump_copy'] = 'Complete your rotation with a small extra.';
    update_option( 'luma_core_settings', $settings );
    return array( 'products' => $created_products, 'pages' => count( $pages ) );
}

function luma_core_demo_install_action() {
    check_admin_referer( 'luma_install_demo' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'You do not have permission to install demo content.' );
    try {
        $result = luma_core_install_demo_store();
    } catch ( Throwable $error ) {
        error_log( 'Luma demo installer: ' . $error->getMessage() );
        $result = array( 'products' => 0, 'pages' => 0, 'error' => __( 'The demo installer stopped safely. Check the PHP error log for the exact cause.', 'luma-commerce-core' ) );
    }
    set_transient( 'luma_demo_notice_' . get_current_user_id(), $result, 60 );
    wp_safe_redirect( admin_url( 'admin.php?page=luma-control-center' ) ); exit;
}
add_action( 'admin_post_luma_install_demo', 'luma_core_demo_install_action' );

function luma_core_demo_notice() {
    $key = 'luma_demo_notice_' . get_current_user_id(); $result = get_transient( $key );
    if ( ! $result ) return;
    delete_transient( $key );
    if ( ! empty( $result['error'] ) ) echo '<div class="notice notice-error is-dismissible"><p><strong>Luma demo installer stopped safely.</strong> ' . esc_html( $result['error'] ) . '</p></div>';
    else echo '<div class="notice notice-success is-dismissible"><p><strong>Luma demo store installed.</strong> ' . esc_html( $result['products'] ) . ' products and ' . esc_html( $result['pages'] ) . ' pages are ready to edit.</p></div>';
}
add_action( 'admin_notices', 'luma_core_demo_notice' );

function luma_core_track_order() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! function_exists( 'wc_get_order' ) ) wp_send_json_error( array( 'message' => __( 'Order tracking is unavailable.', 'luma-commerce-core' ) ), 400 );
    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $order = $order_id ? wc_get_order( $order_id ) : false;
    if ( ! $order || ! $email || ! hash_equals( strtolower( (string) $order->get_billing_email() ), strtolower( $email ) ) ) wp_send_json_error( array( 'message' => __( 'We could not match that order. Check the order number and email.', 'luma-commerce-core' ) ), 404 );
    wp_send_json_success( array( 'message' => sprintf( /* translators: 1: order number, 2: order status. */ __( 'Order %1$s is %2$s.', 'luma-commerce-core' ), $order->get_order_number(), wc_get_order_status_name( $order->get_status() ) ), 'status' => $order->get_status(), 'date' => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '' ) );
}
add_action( 'wp_ajax_luma_track_order', 'luma_core_track_order' );
add_action( 'wp_ajax_nopriv_luma_track_order', 'luma_core_track_order' );

function luma_core_waitlist_signup() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    $raw_email = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
    $email = is_string( $raw_email ) ? strtolower( sanitize_email( $raw_email ) ) : '';
    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $product = luma_core_public_product( $product_id );
    if ( ! is_email( $email ) ) wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'luma-commerce-core' ) ), 400 );
    if ( ! $product ) wp_send_json_error( array( 'message' => __( 'This piece is unavailable.', 'luma-commerce-core' ) ), 404 );
    if ( $product->is_in_stock() ) wp_send_json_error( array( 'message' => __( 'This piece is available now.', 'luma-commerce-core' ) ), 400 );
    $existing = get_posts( array( 'post_type' => 'luma_waitlist', 'post_status' => 'private', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_luma_waitlist_email', 'value' => $email ), array( 'key' => '_luma_waitlist_product', 'value' => $product_id ) ), 'fields' => 'ids' ) );
    if ( ! $existing ) {
        $signup_id = wp_insert_post( array( 'post_type' => 'luma_waitlist', 'post_status' => 'private', 'post_title' => $product->get_name() . ' — ' . $email ), true );
        if ( $signup_id && ! is_wp_error( $signup_id ) ) { update_post_meta( $signup_id, '_luma_waitlist_email', $email ); update_post_meta( $signup_id, '_luma_waitlist_product', $product_id ); wp_mail( get_option( 'admin_email' ), 'New Luma restock request', $email . ' requested a restock notice for ' . $product->get_name() . '.' ); }
        else wp_send_json_error( array( 'message' => __( 'We could not save that request. Please try again.', 'luma-commerce-core' ) ), 500 );
    }
    wp_send_json_success( array( 'message' => __( 'You are on the list. We will be in touch when it returns.', 'luma-commerce-core' ) ) );
}
add_action( 'wp_ajax_luma_waitlist_signup', 'luma_core_waitlist_signup' );
add_action( 'wp_ajax_nopriv_luma_waitlist_signup', 'luma_core_waitlist_signup' );

function luma_core_notify_waitlist( $product_id, $stock_status ) {
    if ( 'instock' !== $stock_status ) return;
    $product = wc_get_product( $product_id );
    if ( ! $product ) return;
    $product_ids = array( $product->get_id() );
    if ( $product->get_parent_id() ) $product_ids[] = $product->get_parent_id();
    $entries = get_posts( array( 'post_type' => 'luma_waitlist', 'post_status' => 'private', 'posts_per_page' => 100, 'meta_query' => array( array( 'key' => '_luma_waitlist_product', 'value' => array_unique( $product_ids ), 'compare' => 'IN' ), array( 'key' => '_luma_waitlist_notified', 'compare' => 'NOT EXISTS' ) ), 'fields' => 'ids' ) );
    foreach ( $entries as $entry_id ) {
        $email = get_post_meta( $entry_id, '_luma_waitlist_email', true );
        if ( ! is_email( $email ) ) continue;
        $sent = wp_mail( $email, sprintf( __( '%s is back in stock', 'luma-commerce-core' ), $product->get_name() ), sprintf( __( '%s is available again. Shop it here: %s', 'luma-commerce-core' ), $product->get_name(), $product->get_permalink() ) );
        if ( $sent ) { update_post_meta( $entry_id, '_luma_waitlist_notified', current_time( 'mysql' ) ); update_post_meta( $entry_id, '_luma_waitlist_status', 'notified' ); }
    }
}
add_action( 'woocommerce_product_set_stock_status', 'luma_core_notify_waitlist', 10, 2 );

function luma_core_lead_capture() {
    check_ajax_referer( 'luma_core_nonce', 'nonce' );
    if ( ! empty( $_POST['luma_website'] ) ) wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'luma-commerce-core' ) ), 400 );
    $raw_email = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
    $email = is_string( $raw_email ) ? strtolower( sanitize_email( $raw_email ) ) : '';
    if ( ! is_email( $email ) ) wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'luma-commerce-core' ) ), 400 );
    $existing = get_posts( array( 'post_type' => 'luma_lead', 'post_status' => 'private', 'posts_per_page' => 1, 'meta_key' => '_luma_lead_email', 'meta_value' => $email, 'fields' => 'ids' ) );
    if ( ! $existing ) { $lead_id = wp_insert_post( array( 'post_type' => 'luma_lead', 'post_status' => 'private', 'post_title' => $email ) ); if ( $lead_id ) { update_post_meta( $lead_id, '_luma_lead_email', $email ); update_post_meta( $lead_id, '_luma_lead_coupon', luma_core_option( 'coupon_code', '' ) ); update_post_meta( $lead_id, '_luma_lead_source', 'welcome offer' ); wp_mail( get_option( 'admin_email' ), 'New Luma subscriber', $email . ' joined the Luma edit.' ); } }
    $coupon_code = luma_core_option( 'coupon_code', '' );
    wp_send_json_success( array( 'message' => luma_core_coupon_available( $coupon_code ) ? 'Your offer is unlocked. Keep an eye on your inbox for future Luma edits.' : 'Thanks for joining the Luma edit. Keep an eye on your inbox for future stories.' ) );
}
add_action( 'wp_ajax_luma_lead_capture', 'luma_core_lead_capture' );
add_action( 'wp_ajax_nopriv_luma_lead_capture', 'luma_core_lead_capture' );

/**
 * Luma block widget for Elementor.
 *
 * The class is declared on demand from the `elementor/widgets/register`
 * callback. Declaring it at plugin load time only worked when Elementor
 * happened to load first, so the widget silently disappeared on installs with
 * a different plugin order.
 */
function luma_core_define_elementor_widget() {
    if ( class_exists( 'Luma_Core_Elementor_Block_Widget' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
        return class_exists( 'Luma_Core_Elementor_Block_Widget' );
    }
    class Luma_Core_Elementor_Block_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'luma-commerce-block'; }
        public function get_title() { return __( 'Luma Commerce Block', 'luma-commerce-core' ); }
        public function get_icon() { return 'eicon-shop'; }
        public function get_categories() { return array( 'general' ); }
        protected function register_controls() {
            $this->start_controls_section( 'content_section', array( 'label' => __( 'Luma block', 'luma-commerce-core' ) ) );
            $this->add_control( 'block', array( 'label' => __( 'Block type', 'luma-commerce-core' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'trust_bar', 'options' => array( 'sale_bar' => 'Sale bar', 'coupon' => 'Coupon booster', 'shipping_meter' => 'Shipping meter', 'countdown' => 'Countdown', 'trust_bar' => 'Trust bar', 'size_guide' => 'Size guide', 'wishlist' => 'Wishlist', 'compare' => 'Compare', 'saved_items' => 'Saved for later', 'recently_viewed' => 'Recently viewed', 'bundle' => 'Complete-the-look bundle', 'order_bump' => 'Checkout order bump' ) ) );
            $this->end_controls_section();
        }
        protected function render() {
            $settings = $this->get_settings_for_display();
            $block = isset( $settings['block'] ) ? sanitize_key( $settings['block'] ) : '';
            $allowed = array( 'sale_bar', 'coupon', 'shipping_meter', 'countdown', 'trust_bar', 'size_guide', 'wishlist', 'compare', 'saved_items', 'recently_viewed', 'bundle', 'order_bump' );
            // Whitelist the block so a hand-edited setting cannot be used to
            // fire an arbitrary shortcode.
            if ( ! in_array( $block, $allowed, true ) ) return;
            echo do_shortcode( '[luma_' . $block . ']' );
        }
    }
    return true;
}

function luma_core_register_elementor_widget( $widgets_manager ) {
    if ( ! luma_core_define_elementor_widget() ) return;
    $widget = new Luma_Core_Elementor_Block_Widget();
    if ( method_exists( $widgets_manager, 'register' ) ) $widgets_manager->register( $widget );
    elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) $widgets_manager->register_widget_type( $widget ); // Elementor < 3.5.
}
add_action( 'elementor/widgets/register', 'luma_core_register_elementor_widget' );
add_action( 'elementor/widgets/widgets_registered', 'luma_core_register_elementor_widget' ); // Elementor < 3.5.

require_once LUMA_CORE_DIR . '/includes/post-purchase.php';
