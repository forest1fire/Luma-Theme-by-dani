<?php
/**
 * The header.
 *
 * @package Luma
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php if ( ! luma_commerce_elementor_location( 'header' ) ) : ?>
    <?php $announcement = trim( (string) get_theme_mod( 'luma_announcement_text', __( 'New season, sharper essentials.', 'luma-commerce' ) ) ); if ( $announcement ) : ?><div class="luma-announcement"><span><?php echo esc_html( $announcement ); ?></span><a href="<?php echo esc_url( luma_commerce_shop_url() ); ?>"><?php esc_html_e( 'Shop now', 'luma-commerce' ); ?> <span aria-hidden="true">↗</span></a></div><?php endif; ?>
    <div class="luma-utility-bar">
        <div class="luma-container luma-utility-inner">
            <span><?php esc_html_e( 'Luma / Streetwear for everyday motion', 'luma-commerce' ); ?></span>
            <?php if ( has_nav_menu( 'utility' ) ) : ?>
                <?php wp_nav_menu( array( 'theme_location' => 'utility', 'container' => false, 'menu_class' => 'luma-utility-menu', 'depth' => 1 ) ); ?>
            <?php else : ?>
                <div class="luma-utility-links"><a href="<?php echo esc_url( home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'Track order', 'luma-commerce' ); ?></a><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Help', 'luma-commerce' ); ?></a></div>
            <?php endif; ?>
        </div>
    </div>
    <header class="luma-site-header<?php echo get_theme_mod( 'luma_sticky_header', true ) ? ' luma-header--sticky' : ''; ?>">
        <div class="luma-container luma-header-row">
            <button class="luma-menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation"><span></span><span></span><span></span><span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'luma-commerce' ); ?></span></button>
            <div class="luma-branding">
                <?php if ( has_custom_logo() ) { the_custom_logo(); } else { ?><a class="luma-wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="luma-wordmark__name"><?php echo esc_html( luma_commerce_brand_name() ); ?><sup>®</sup></span><?php if ( luma_commerce_show_author_credit() ) : ?><span class="luma-wordmark__byline">By CodeWithDani</span><?php endif; ?></a><?php } ?>
            </div>
            <nav id="primary-navigation" class="luma-primary-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'luma-commerce' ); ?>">
                <?php if ( has_nav_menu( 'primary' ) ) { wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'luma-menu' ) ); } else { luma_commerce_fallback_menu(); } ?>
            </nav>
            <div class="luma-header-actions">
                <?php $wishlist_enabled = ! function_exists( 'luma_core_option' ) || luma_core_option( 'module_wishlist', true ); ?>
                <button class="luma-search-toggle luma-icon-link" type="button" aria-expanded="false" aria-controls="luma-search-panel" aria-label="<?php esc_attr_e( 'Search', 'luma-commerce' ); ?>"><span class="luma-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="10.8" cy="10.8" r="6.5"></circle><path d="m16 16 5 5"></path></svg></span><span class="luma-icon-label"><?php esc_html_e( 'Search', 'luma-commerce' ); ?></span></button>
                <?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?><a class="luma-icon-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" aria-label="<?php esc_attr_e( 'My account', 'luma-commerce' ); ?>"><span class="luma-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="8" r="4"></circle><path d="M4.5 21c.8-4.2 3.3-6.2 7.5-6.2s6.7 2 7.5 6.2"></path></svg></span><span class="luma-icon-label"><?php esc_html_e( 'Account', 'luma-commerce' ); ?></span></a><?php endif; ?>
                <?php if ( $wishlist_enabled ) : ?><a class="luma-icon-link luma-wishlist-link" href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" aria-label="<?php esc_attr_e( 'Wishlist', 'luma-commerce' ); ?>"><span class="luma-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20.4 4.8c-2.2-2.3-5.8-1.5-8.4 1C9.4 3.3 5.8 2.5 3.6 4.8c-2.5 2.6-1.7 6.2.6 8.8L12 21l7.8-7.4c2.3-2.6 3.1-6.2.6-8.8Z"></path></svg></span><span class="luma-icon-label"><?php esc_html_e( 'Wish list', 'luma-commerce' ); ?></span><span class="luma-wishlist-count" data-luma-wishlist-count aria-hidden="true">0</span></a><?php endif; ?>
                <?php luma_commerce_scheme_toggle(); ?>
                <?php luma_commerce_cart_link(); ?>
            </div>
        </div>
        <div id="luma-search-panel" class="luma-search-panel" hidden>
            <div class="luma-container">
                <?php get_search_form(); ?>
            </div>
        </div>
    </header>
<?php endif; ?>
