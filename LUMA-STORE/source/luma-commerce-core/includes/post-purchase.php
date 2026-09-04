<?php
/**
 * Order-aware post-purchase merchandising.
 *
 * @package LumaCommerceCore
 */

defined( 'ABSPATH' ) || exit;

function luma_core_post_purchase_recommendations( $order_id ) {
    if ( ! luma_core_option( 'module_post_purchase', true ) || ! $order_id ) return;
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $authorized = is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id();
    $order_key = isset( $_GET['key'] ) && is_scalar( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
    if ( ! $authorized && $order_key && hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) $authorized = true;
    if ( ! $authorized ) return;

    $purchased_ids = array();
    $cross_sell_ids = array();
    $category_slugs = array();
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $purchased = $item->get_product();
        if ( ! $purchased ) continue;
        $purchased_ids[] = $purchased->get_id();
        $cross_sell_ids = array_merge( $cross_sell_ids, (array) $purchased->get_cross_sell_ids() );
        $terms = wp_get_post_terms( $purchased->get_parent_id() ? $purchased->get_parent_id() : $purchased->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
        if ( ! is_wp_error( $terms ) ) $category_slugs = array_merge( $category_slugs, $terms );
    }
    $purchased_ids = array_values( array_unique( array_filter( array_map( 'absint', $purchased_ids ) ) ) );
    $query = array( 'status' => 'publish', 'limit' => 4, 'exclude' => $purchased_ids, 'orderby' => 'popularity' );
    $candidate_cross_sells = array_values( array_diff( array_unique( array_map( 'absint', $cross_sell_ids ) ), $purchased_ids ) );
    if ( $candidate_cross_sells ) {
        $query['include'] = $candidate_cross_sells;
    } elseif ( ! empty( $category_slugs ) ) {
        $query['category'] = array_values( array_unique( array_filter( array_map( 'sanitize_title', $category_slugs ) ) ) );
    }
    $products = wc_get_products( $query );
    if ( ! $products && isset( $query['include'] ) ) { unset( $query['include'] ); $products = wc_get_products( $query ); }
    if ( ! $products ) return;
    echo '<section class="luma-post-purchase" aria-labelledby="luma-post-purchase-title"><p class="luma-kicker">' . esc_html__( 'After the order', 'luma-commerce-core' ) . '</p><h2 id="luma-post-purchase-title">' . esc_html__( 'Complete your rotation', 'luma-commerce-core' ) . '</h2><p class="luma-post-purchase__copy">' . esc_html__( 'A considered follow-up to the pieces in this order, selected from your store catalogue.', 'luma-commerce-core' ) . '</p><div class="luma-recommendations__grid">';
    foreach ( $products as $item ) echo luma_core_recommendation_card( $item );
    echo '</div></section>';
}
add_action( 'woocommerce_thankyou', 'luma_core_post_purchase_recommendations', 30 );

function luma_core_reorder_card( $product ) {
    if ( ! $product ) return '';
    $action = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ? '<a class="luma-reorder-link" href="' . esc_url( $product->add_to_cart_url() ) . '">' . esc_html__( 'Add again', 'luma-commerce-core' ) . ' ↗</a>' : '<a class="luma-reorder-link" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View item', 'luma-commerce-core' ) . ' ↗</a>';
    return '<article class="luma-reorder-card" data-product-id="' . esc_attr( $product->get_id() ) . '"><a class="luma-reorder-card__image" href="' . esc_url( $product->get_permalink() ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</a><div><h3><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3><span>' . wp_kses_post( $product->get_price_html() ) . '</span>' . $action . '</div></article>';
}

function luma_core_order_reorder_section( $order ) {
    if ( ! luma_core_option( 'module_post_purchase', true ) || ! $order || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) return;
    $products = array();
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $product = $item->get_product();
        if ( ! $product || 'publish' !== get_post_status( $product->get_id() ) ) continue;
        $products[ $product->get_id() ] = $product;
    }
    if ( ! $products ) return;
    echo '<section class="luma-reorder" aria-labelledby="luma-reorder-title"><p class="luma-kicker">' . esc_html__( 'From your order', 'luma-commerce-core' ) . '</p><h2 id="luma-reorder-title">' . esc_html__( 'Shop it again', 'luma-commerce-core' ) . '</h2><p class="luma-reorder__copy">' . esc_html__( 'Revisit the pieces from this order or add an available item back to your bag.', 'luma-commerce-core' ) . '</p><div class="luma-reorder-grid">';
    foreach ( $products as $product ) echo luma_core_reorder_card( $product );
    echo '</div></section>';
}
add_action( 'woocommerce_order_details_after_order_table', 'luma_core_order_reorder_section', 20 );
