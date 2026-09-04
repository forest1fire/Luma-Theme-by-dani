<?php
/**
 * Search form template.
 *
 * Rendered more than once on some pages (header panel, 404 recovery, empty
 * search results), so every id is made unique with wp_unique_id() to keep the
 * markup valid and the aria-controls relationship pointing at the right list.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;

$luma_search_id    = wp_unique_id( 'luma-search-field-' );
$luma_results_id   = wp_unique_id( 'luma-predictive-results-' );
$luma_search_label = __( 'Search products, collections, stories…', 'luma-commerce' );
?>
<form role="search" method="get" class="luma-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
    <label class="screen-reader-text" for="<?php echo esc_attr( $luma_search_id ); ?>"><?php esc_html_e( 'Search for:', 'luma-commerce' ); ?></label>
    <input
        id="<?php echo esc_attr( $luma_search_id ); ?>"
        class="luma-predictive-input"
        type="search"
        value="<?php echo esc_attr( get_search_query() ); ?>"
        name="s"
        placeholder="<?php echo esc_attr( $luma_search_label ); ?>"
        autocomplete="off"
        role="combobox"
        aria-expanded="false"
        aria-controls="<?php echo esc_attr( $luma_results_id ); ?>"
        aria-autocomplete="list">
    <button type="submit"><?php esc_html_e( 'Search', 'luma-commerce' ); ?> <span aria-hidden="true">↗</span></button>
    <div id="<?php echo esc_attr( $luma_results_id ); ?>" class="luma-predictive-results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'luma-commerce' ); ?>" aria-live="polite"></div>
</form>
