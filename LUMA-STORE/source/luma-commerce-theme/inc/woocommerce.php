<?php
/**
 * WooCommerce compatibility.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;

function luma_commerce_woocommerce_setup() {
    add_theme_support( 'woocommerce', array(
        'thumbnail_image_width' => 720,
        'single_image_width'    => 1200,
        'product_grid'          => array(
            'default_rows'    => 4,
            'min_rows'        => 1,
            'max_rows'        => 12,
            'default_columns' => 4,
            'min_columns'     => 2,
            'max_columns'     => 5,
        ),
    ) );
    if ( get_theme_mod( 'luma_gallery_zoom', true ) ) add_theme_support( 'wc-product-gallery-zoom' );
    if ( get_theme_mod( 'luma_gallery_lightbox', true ) ) add_theme_support( 'wc-product-gallery-lightbox' );
    if ( get_theme_mod( 'luma_gallery_slider', true ) ) add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'luma_commerce_woocommerce_setup' );

function luma_commerce_wc_wrapper_start() {
    echo '<main id="primary" class="luma-main luma-shop-main"><div class="luma-container">';
}
function luma_commerce_wc_wrapper_end() {
    echo '</div></main>';
}

/*
 * WooCommerce ships its own content wrappers on these hooks. Its
 * `global/wrapper-start.php` prints `<div id="primary" class="content-area">
 * <main id="main" class="site-main">`, which collided with Luma's own
 * `<main id="primary">`: two elements sharing one id (breaking the Skip to
 * content link and HTML validation) and a `<main>` nested inside a `<main>`.
 * Luma renders its own wrappers, so the defaults must be unhooked.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

add_action( 'woocommerce_before_main_content', 'luma_commerce_wc_wrapper_start', 5 );
add_action( 'woocommerce_after_main_content', 'luma_commerce_wc_wrapper_end', 50 );

/**
 * The shop sidebar is not rendered by Luma templates; keep WooCommerce from
 * printing an empty `#secondary` wrapper on archives and single products.
 */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

function luma_commerce_loop_columns() {
    return max( 2, min( 5, absint( get_theme_mod( 'luma_product_columns', 4 ) ) ) );
}
add_filter( 'loop_shop_columns', 'luma_commerce_loop_columns' );

function luma_commerce_products_per_page() {
    return max( 4, min( 48, absint( get_theme_mod( 'luma_products_per_page', 12 ) ) ) );
}
add_filter( 'loop_shop_per_page', 'luma_commerce_products_per_page' );

function luma_commerce_add_to_cart_text( $text, $product ) {
    if ( $product && $product->is_type( 'simple' ) && $product->is_purchasable() ) {
        return __( 'Add to bag', 'luma-commerce' );
    }
    return $text;
}
add_filter( 'woocommerce_product_add_to_cart_text', 'luma_commerce_add_to_cart_text', 10, 2 );
add_filter( 'woocommerce_product_single_add_to_cart_text', function() { return __( 'Add to bag', 'luma-commerce' ); } );

function luma_commerce_loop_product_classes( $classes ) {
    $classes[] = 'luma-product-loop-item';
    return $classes;
}
add_filter( 'woocommerce_post_class', 'luma_commerce_loop_product_classes' );
