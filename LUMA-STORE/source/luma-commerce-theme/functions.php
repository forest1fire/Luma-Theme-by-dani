<?php
/**
 * Luma theme bootstrap.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'LUMA_COMMERCE_VERSION', '1.33.0' );
define( 'LUMA_COMMERCE_DIR', get_template_directory() );
define( 'LUMA_COMMERCE_URI', get_template_directory_uri() );

require_once LUMA_COMMERCE_DIR . '/inc/core.php';
require_once LUMA_COMMERCE_DIR . '/inc/elementor.php';
require_once LUMA_COMMERCE_DIR . '/inc/woocommerce.php';
