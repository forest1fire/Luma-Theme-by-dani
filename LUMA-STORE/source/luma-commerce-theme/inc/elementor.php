<?php
/**
 * Elementor Theme Builder integration.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;

function luma_commerce_register_elementor_locations( $manager ) {
    $manager->register_all_core_location();
}
add_action( 'elementor/theme/register_locations', 'luma_commerce_register_elementor_locations' );

/**
 * Render an Elementor Theme Builder location when available.
 *
 * @param string $location Elementor location slug.
 * @return bool
 */
function luma_commerce_elementor_location( $location ) {
    if ( function_exists( 'elementor_theme_do_location' ) ) {
        return (bool) elementor_theme_do_location( $location );
    }
    return false;
}
