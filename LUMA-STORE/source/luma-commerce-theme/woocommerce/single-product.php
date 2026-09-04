<?php
/**
 * Single product with an Elementor Pro Theme Builder escape hatch.
 */
defined( 'ABSPATH' ) || exit;
get_header( 'shop' );
if ( ! luma_commerce_elementor_location( 'single' ) ) {
    do_action( 'woocommerce_before_main_content' );
    while ( have_posts() ) { the_post(); wc_get_template_part( 'content', 'single-product' ); }
    do_action( 'woocommerce_after_main_content' );
}
get_footer( 'shop' );
