<?php
/**
 * Product archive with an Elementor Pro Theme Builder escape hatch.
 */
defined( 'ABSPATH' ) || exit;
get_header( 'shop' );
if ( ! luma_commerce_elementor_location( 'archive' ) ) {
    do_action( 'woocommerce_before_main_content' );
    do_action( 'woocommerce_shop_loop_header' );
    if ( woocommerce_product_loop() ) {
        do_action( 'woocommerce_before_shop_loop' );
        ?>
        <div class="luma-shop-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Product view', 'luma-commerce' ); ?>"><span><?php esc_html_e( 'View', 'luma-commerce' ); ?></span><button type="button" class="is-active" data-luma-view="grid" aria-pressed="true"><?php esc_html_e( 'Grid', 'luma-commerce' ); ?></button><button type="button" data-luma-view="list" aria-pressed="false"><?php esc_html_e( 'List', 'luma-commerce' ); ?></button></div>
        <?php
        woocommerce_product_loop_start();
        if ( wc_get_loop_prop( 'total' ) ) {
            while ( have_posts() ) { the_post(); do_action( 'woocommerce_shop_loop' ); wc_get_template_part( 'content', 'product' ); }
        }
        woocommerce_product_loop_end();
        do_action( 'woocommerce_after_shop_loop' );
    } else { do_action( 'woocommerce_no_products_found' ); }
    do_action( 'woocommerce_after_main_content' );
    do_action( 'woocommerce_sidebar' );
}
get_footer( 'shop' );
