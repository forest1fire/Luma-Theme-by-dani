<?php
/**
 * Theme setup and shared helpers.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;

function luma_commerce_setup() {
    load_theme_textdomain( 'luma-commerce', LUMA_COMMERCE_DIR . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'custom-logo', array(
        'height'      => 80,
        'width'       => 260,
        'flex-height' => true,
        'flex-width'  => true,
    ) );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'customize-selective-refresh-widgets' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );

    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'luma-commerce' ),
        'utility' => __( 'Utility Menu', 'luma-commerce' ),
        'footer'  => __( 'Footer Menu', 'luma-commerce' ),
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer widgets', 'luma-commerce' ),
        'id'            => 'footer-widgets',
        'description'   => __( 'Optional widgets shown above the footer legal row.', 'luma-commerce' ),
        'before_widget' => '<section id="%1$s" class="luma-footer-widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="luma-footer-widget__title">',
        'after_title'   => '</h2>',
    ) );
}
add_action( 'after_setup_theme', 'luma_commerce_setup' );

function luma_commerce_content_width() {
    $GLOBALS['content_width'] = apply_filters( 'luma_commerce_content_width', 1320 );
}
add_action( 'after_setup_theme', 'luma_commerce_content_width', 0 );

function luma_commerce_assets() {
    wp_enqueue_style( 'luma-commerce-style', get_stylesheet_uri(), array(), LUMA_COMMERCE_VERSION );
    wp_enqueue_style( 'luma-commerce-theme', LUMA_COMMERCE_URI . '/assets/css/theme.css', array( 'luma-commerce-style' ), LUMA_COMMERCE_VERSION );
    wp_enqueue_style( 'luma-commerce-seo-motion', LUMA_COMMERCE_URI . '/assets/css/seo-motion.css', array( 'luma-commerce-theme' ), LUMA_COMMERCE_VERSION );
    /* The motion kill-switch is emitted once, attached to the last stylesheet.
       It was previously printed twice (here and again inside $styles below). */
    if ( ! get_theme_mod( 'luma_enable_animations', true ) ) wp_add_inline_style( 'luma-commerce-seo-motion', '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important;}' );
    if ( is_rtl() ) wp_enqueue_style( 'luma-commerce-rtl', LUMA_COMMERCE_URI . '/assets/css/rtl.css', array( 'luma-commerce-theme' ), LUMA_COMMERCE_VERSION );
    $accent = sanitize_hex_color( get_theme_mod( 'luma_accent', '#f0523d' ) );
    $paper  = sanitize_hex_color( get_theme_mod( 'luma_paper', '#f5f2ec' ) );
    $heading_fonts = array( 'narrow' => '"Arial Narrow","Helvetica Neue",Arial,sans-serif', 'sans' => '"Helvetica Neue",Arial,sans-serif', 'serif' => 'Georgia,serif' );
    $body_fonts = array( 'system' => 'Inter,ui-sans-serif,system-ui,sans-serif', 'sans' => '"Helvetica Neue",Arial,sans-serif', 'serif' => 'Georgia,serif' );
    $heading_font = $heading_fonts[ get_theme_mod( 'luma_heading_font', 'narrow' ) ] ?? $heading_fonts['narrow'];
    $body_font = $body_fonts[ get_theme_mod( 'luma_body_font', 'system' ) ] ?? $body_fonts['system'];
    $container = luma_commerce_sanitize_container( get_theme_mod( 'luma_container_width', 1440 ) );
    $radius = luma_commerce_sanitize_choice( get_theme_mod( 'luma_radius', '2px' ), array( '0px' => 'Square', '2px' => 'Subtle', '6px' => 'Soft' ) );
    $scale = luma_commerce_sanitize_scale( get_theme_mod( 'luma_heading_scale', 1 ) );
    $body_size = max( 13, min( 18, absint( get_theme_mod( 'luma_body_size', 15 ) ) ) );
    $product_columns = max( 2, min( 5, absint( get_theme_mod( 'luma_product_columns', 4 ) ) ) );
    $styles = ':root{--luma-container:' . $container . 'px;--luma-product-columns:' . $product_columns . ';--luma-radius:' . $radius . ';--luma-display:' . $heading_font . ';--luma-body:' . $body_font . ';--luma-heading-scale:' . $scale . ';--luma-body-size:' . $body_size . 'px;' . ( $accent ? '--luma-accent:' . $accent . ';' : '' ) . ( $paper ? '--luma-paper:' . $paper . ';' : '' ) . '}';
    wp_add_inline_style( 'luma-commerce-theme', $styles );
    wp_enqueue_script( 'luma-commerce-theme', LUMA_COMMERCE_URI . '/assets/js/theme.js', array(), LUMA_COMMERCE_VERSION, true );

    /*
     * Every string theme.js writes into the DOM is localized here. The menu
     * toggle previously hardcoded "Open menu"/"Close menu" in English, so a
     * translated site announced the wrong language to screen readers.
     */
    wp_localize_script( 'luma-commerce-theme', 'lumaTheme', array(
        'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
        'shopUrl'      => luma_commerce_shop_url(),
        'scheme'       => luma_commerce_color_scheme(),
        'schemeToggle' => (bool) get_theme_mod( 'luma_scheme_toggle', true ),
        'i18n'         => array(
            'openMenu'      => __( 'Open menu', 'luma-commerce' ),
            'closeMenu'     => __( 'Close menu', 'luma-commerce' ),
            'openSearch'    => __( 'Open search', 'luma-commerce' ),
            'closeSearch'   => __( 'Close search', 'luma-commerce' ),
            'gridView'      => __( 'Grid view', 'luma-commerce' ),
            'listView'      => __( 'List view', 'luma-commerce' ),
            'darkMode'      => __( 'Switch to dark theme', 'luma-commerce' ),
            'lightMode'     => __( 'Switch to light theme', 'luma-commerce' ),
            'toTop'         => __( 'Back to top', 'luma-commerce' ),
            'viewBag'       => __( 'View shopping bag', 'luma-commerce' ),
            'viewWishlist'  => __( 'Wishlist', 'luma-commerce' ),
            'itemsInBag'    => __( '%d items in bag', 'luma-commerce' ),
            'oneItemInBag'  => __( '1 item in bag', 'luma-commerce' ),
            'itemsSaved'    => __( '%d saved items', 'luma-commerce' ),
            'oneItemSaved'  => __( '1 saved item', 'luma-commerce' ),
            'noItems'       => __( 'empty', 'luma-commerce' ),
        ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'luma_commerce_assets' );

function luma_commerce_has_seo_plugin() {
    return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'RankMath' );
}

function luma_commerce_meta_description() {
    if ( is_admin() || luma_commerce_has_seo_plugin() ) return;
    global $product;
    $description = '';
    if ( function_exists( 'is_product' ) && is_product() && $product ) $description = $product->get_short_description();
    elseif ( is_singular() && has_excerpt() ) $description = get_the_excerpt();
    elseif ( ( function_exists( 'is_shop' ) && is_shop() ) || is_post_type_archive( 'product' ) ) $description = sprintf( __( 'Explore the latest %s edit: considered pieces, easy layers and everyday essentials.', 'luma-commerce' ), luma_commerce_brand_name() );
    elseif ( is_front_page() ) $description = sprintf( __( '%s makes considered wardrobe pieces for everyday motion, with an original streetwear point of view.', 'luma-commerce' ), luma_commerce_brand_name() );
    else $description = sprintf( __( 'Discover %s: modern wardrobe pieces with a considered point of view.', 'luma-commerce' ), luma_commerce_brand_name() );
    $description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
    if ( function_exists( 'mb_substr' ) ) $description = mb_substr( $description, 0, 158 ); else $description = substr( $description, 0, 158 );
    if ( $description ) echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
}
add_action( 'wp_head', 'luma_commerce_meta_description', 2 );

function luma_commerce_social_meta() {
    if ( is_admin() || luma_commerce_has_seo_plugin() ) return;
    global $product;
    $url = is_singular() ? get_permalink() : home_url( '/' );
    $title = is_singular() ? get_the_title() : luma_commerce_brand_name();
    $description = '';
    if ( function_exists( 'is_product' ) && is_product() && $product ) $description = $product->get_short_description();
    elseif ( is_singular() && has_excerpt() ) $description = get_the_excerpt();
    else $description = sprintf( __( 'Discover %s: modern wardrobe pieces with a considered point of view.', 'luma-commerce' ), luma_commerce_brand_name() );
    $description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
    if ( function_exists( 'mb_substr' ) ) $description = mb_substr( $description, 0, 200 ); else $description = substr( $description, 0, 200 );
    $image = '';
    if ( is_singular() && has_post_thumbnail() ) $image = wp_get_attachment_image_url( get_post_thumbnail_id(), 'full' );
    if ( ! $image && get_theme_mod( 'custom_logo' ) ) $image = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
    echo '<meta property="og:type" content="' . esc_attr( is_singular() ? 'article' : 'website' ) . '">';
    echo '<meta property="og:site_name" content="' . esc_attr( luma_commerce_brand_name() ) . '">';
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '">';
    echo '<meta property="og:description" content="' . esc_attr( $description ) . '">';
    echo '<meta property="og:url" content="' . esc_url( $url ) . '">';
    echo '<meta name="twitter:card" content="summary_large_image">';
    if ( $image ) { echo '<meta property="og:image" content="' . esc_url( $image ) . '">'; echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">'; }
    echo "\n";
}
add_action( 'wp_head', 'luma_commerce_social_meta', 4 );

function luma_commerce_structured_data() {
    if ( is_admin() || luma_commerce_has_seo_plugin() ) return;
    $home = home_url( '/' ); $brand = luma_commerce_brand_name(); $graph = array( array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $brand, 'potentialAction' => array( '@type' => 'SearchAction', 'target' => $home . '?s={search_term_string}', 'query-input' => 'required name=search_term_string' ) ) );
    $logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '';
    $organization = array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => $brand, 'url' => $home );
    if ( $logo ) $organization['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
    $graph[] = $organization;
    echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ) ) . '</script>' . "\n";
}
add_action( 'wp_head', 'luma_commerce_structured_data', 3 );

function luma_commerce_preload_home_hero() {
    if ( ! is_front_page() || trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) || 'builder' === get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true ) ) return;
    echo '<link rel="preload" as="image" href="' . esc_url( luma_commerce_asset( 'images/luma-hero.jpg' ) ) . '" fetchpriority="high">' . "\n";
}
add_action( 'wp_head', 'luma_commerce_preload_home_hero', 1 );

function luma_commerce_skip_link() {
    echo '<a class="luma-skip-link" href="#primary">' . esc_html__( 'Skip to content', 'luma-commerce' ) . '</a>';
}
add_action( 'wp_body_open', 'luma_commerce_skip_link', 2 );

function luma_commerce_body_classes( $classes ) {
    $classes[] = 'luma-commerce';
    if ( class_exists( 'WooCommerce' ) ) {
        $classes[] = 'luma-has-woocommerce';
    }
    if ( class_exists( 'Elementor\Plugin' ) ) {
        $classes[] = 'luma-has-elementor';
    }
    if ( 'outline' === get_theme_mod( 'luma_button_style', 'solid' ) ) $classes[] = 'luma-buttons--outline';
    if ( ! get_theme_mod( 'luma_sticky_header', true ) ) $classes[] = 'luma-header--static';
    return $classes;
}
add_filter( 'body_class', 'luma_commerce_body_classes' );

function luma_commerce_fallback_menu() {
    $departments = array( 'new-in' => __( 'New in', 'luma-commerce' ), 'men' => __( 'Men', 'luma-commerce' ), 'women' => __( 'Women', 'luma-commerce' ), 'juniors' => __( 'Juniors', 'luma-commerce' ) );
    $product_types = array( 't-shirts' => __( 'T-shirts', 'luma-commerce' ), 'outerwear' => __( 'Outerwear', 'luma-commerce' ), 'bottoms' => __( 'Bottoms', 'luma-commerce' ), 'accessories' => __( 'Accessories', 'luma-commerce' ) );
    echo '<ul id="primary-menu" class="luma-menu">';
    echo '<li class="luma-menu-mega"><a href="' . esc_url( luma_commerce_shop_url() ) . '">' . esc_html__( 'Shop', 'luma-commerce' ) . '</a><div class="luma-mega-menu"><div><small>' . esc_html__( 'Departments', 'luma-commerce' ) . '</small>';
    foreach ( $departments as $slug => $label ) echo '<a href="' . esc_url( luma_commerce_category_url( $slug ) ) . '">' . esc_html( $label ) . '</a>';
    echo '</div><div><small>' . esc_html__( 'Shop by product', 'luma-commerce' ) . '</small>';
    foreach ( $product_types as $slug => $label ) echo '<a href="' . esc_url( luma_commerce_category_url( $slug ) ) . '">' . esc_html( $label ) . '</a>';
    echo '</div><div class="luma-mega-menu__feature"><b>' . esc_html__( 'Fresh drop', 'luma-commerce' ) . '</b><span>' . esc_html__( 'Utility layers for everyday motion.', 'luma-commerce' ) . '</span><a href="' . esc_url( luma_commerce_shop_url() ) . '">' . esc_html__( 'Explore', 'luma-commerce' ) . ' ↗</a></div></div></li>';
    foreach ( $departments as $slug => $label ) echo '<li><a href="' . esc_url( luma_commerce_category_url( $slug ) ) . '">' . esc_html( $label ) . '</a></li>';
    echo '<li class="luma-menu-sale"><a href="' . esc_url( home_url( '/sale/' ) ) . '">' . esc_html__( 'Sale', 'luma-commerce' ) . '</a></li>';
    echo '</ul>';
}

function luma_commerce_fallback_footer_menu() {
    echo '<ul class="luma-footer-menu"><li><a href="' . esc_url( luma_commerce_shop_url() ) . '">' . esc_html__( 'Shop all', 'luma-commerce' ) . '</a></li><li><a href="' . esc_url( luma_commerce_category_url( 'new-in' ) ) . '">' . esc_html__( 'New in', 'luma-commerce' ) . '</a></li><li><a href="' . esc_url( home_url( '/sale/' ) ) . '">' . esc_html__( 'Sale', 'luma-commerce' ) . '</a></li></ul>';
}

function luma_commerce_shop_url() {
    if ( function_exists( 'wc_get_page_permalink' ) ) {
        $shop_url = wc_get_page_permalink( 'shop' );
        if ( $shop_url ) return $shop_url;
    }
    return home_url( '/shop/' );
}

function luma_commerce_brand_name() {
    return get_theme_mod( 'luma_brand_name', 'Luma' );
}

function luma_commerce_asset( $path ) {
    return trailingslashit( LUMA_COMMERCE_URI ) . 'assets/' . ltrim( $path, '/' );
}

function luma_commerce_category_url( $slug ) {
    $term = get_term_by( 'slug', sanitize_title( $slug ), 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        $url = get_term_link( $term );
        if ( ! is_wp_error( $url ) ) return $url;
    }
    return home_url( '/product-category/' . sanitize_title( $slug ) . '/' );
}

function luma_commerce_sanitize_container( $value ) {
    return max( 1080, min( 1800, absint( $value ) ) );
}

function luma_commerce_sanitize_scale( $value ) {
    return max( 0.85, min( 1.15, (float) $value ) );
}

function luma_commerce_sanitize_choice( $value, $choices ) {
    return array_key_exists( $value, $choices ) ? $value : array_key_first( $choices );
}

function luma_commerce_customize_register( $wp_customize ) {
    $wp_customize->add_section( 'luma_branding', array( 'title' => __( 'Luma Branding', 'luma-commerce' ), 'priority' => 30 ) );
    $wp_customize->add_setting( 'luma_brand_name', array( 'default' => 'Luma', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_brand_name', array( 'label' => __( 'Brand name', 'luma-commerce' ), 'section' => 'luma_branding', 'type' => 'text', 'description' => __( 'Used when no custom logo is set.', 'luma-commerce' ) ) );
    $wp_customize->add_setting( 'luma_show_author_credit', array( 'default' => false, 'sanitize_callback' => 'wp_validate_boolean', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_show_author_credit', array( 'label' => __( 'Show CodeWithDani credit publicly', 'luma-commerce' ), 'section' => 'luma_branding', 'type' => 'checkbox' ) );
    /* Labels are translated inline: passing a variable to __() hides the
       string from gettext extraction tools, so it never reaches the .pot. */
    $socials = array(
        'luma_instagram_url' => __( 'Instagram URL', 'luma-commerce' ),
        'luma_facebook_url'  => __( 'Facebook URL', 'luma-commerce' ),
        'luma_tiktok_url'    => __( 'TikTok URL', 'luma-commerce' ),
    );
    foreach ( $socials as $social_setting => $social_label ) { $wp_customize->add_setting( $social_setting, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ) ); $wp_customize->add_control( $social_setting, array( 'label' => $social_label, 'section' => 'luma_branding', 'type' => 'url' ) ); }
    $colors = array( 'luma_accent' => array( '#f0523d', __( 'Signal accent color', 'luma-commerce' ) ), 'luma_paper' => array( '#f5f2ec', __( 'Paper background color', 'luma-commerce' ) ) );
    foreach ( $colors as $setting => $color ) { $wp_customize->add_setting( $setting, array( 'default' => $color[0], 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'refresh' ) ); $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting, array( 'label' => $color[1], 'section' => 'luma_branding' ) ) ); }

    $wp_customize->add_section( 'luma_layout', array( 'title' => __( 'Luma Layout & UX', 'luma-commerce' ), 'priority' => 31 ) );
    $wp_customize->add_setting( 'luma_container_width', array( 'default' => 1440, 'sanitize_callback' => 'luma_commerce_sanitize_container', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_container_width', array( 'label' => __( 'Container width', 'luma-commerce' ), 'section' => 'luma_layout', 'type' => 'number', 'input_attrs' => array( 'min' => 1080, 'max' => 1800, 'step' => 10 ) ) );
    $wp_customize->add_setting( 'luma_radius', array( 'default' => '2px', 'sanitize_callback' => function( $value ) { return luma_commerce_sanitize_choice( $value, array( '0px' => 'Square', '2px' => 'Subtle', '6px' => 'Soft' ) ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_radius', array( 'label' => __( 'Corner radius', 'luma-commerce' ), 'section' => 'luma_layout', 'type' => 'select', 'choices' => array( '0px' => __( 'Square', 'luma-commerce' ), '2px' => __( 'Subtle', 'luma-commerce' ), '6px' => __( 'Soft', 'luma-commerce' ) ) ) );
    $wp_customize->add_setting( 'luma_button_style', array( 'default' => 'solid', 'sanitize_callback' => function( $value ) { return luma_commerce_sanitize_choice( $value, array( 'solid' => 'Solid', 'outline' => 'Outline' ) ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_button_style', array( 'label' => __( 'Button style', 'luma-commerce' ), 'section' => 'luma_layout', 'type' => 'select', 'choices' => array( 'solid' => __( 'Solid', 'luma-commerce' ), 'outline' => __( 'Outline', 'luma-commerce' ) ) ) );
    $wp_customize->add_setting( 'luma_sticky_header', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_sticky_header', array( 'label' => __( 'Sticky header', 'luma-commerce' ), 'section' => 'luma_layout', 'type' => 'checkbox' ) );
    $wp_customize->add_setting( 'luma_announcement_text', array( 'default' => 'New season, sharper essentials.', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_announcement_text', array( 'label' => __( 'Announcement text', 'luma-commerce' ), 'section' => 'luma_layout', 'type' => 'text' ) );
    $wp_customize->add_setting( 'luma_enable_animations', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_enable_animations', array( 'label' => __( 'Enable motion effects', 'luma-commerce' ), 'section' => 'luma_layout', 'type' => 'checkbox' ) );

    /* --- 1.33.0: color scheme + page chrome ---------------------------- */
    $scheme_choices = array( 'auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark' );
    $wp_customize->add_setting( 'luma_color_scheme', array( 'default' => 'auto', 'sanitize_callback' => function ( $value ) use ( $scheme_choices ) { return luma_commerce_sanitize_choice( $value, $scheme_choices ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_color_scheme', array(
        'label'       => __( 'Default color scheme', 'luma-commerce' ),
        'description' => __( 'Auto follows each visitor’s system preference until they choose otherwise.', 'luma-commerce' ),
        'section'     => 'luma_layout',
        'type'        => 'select',
        'choices'     => array(
            'auto'  => __( 'Auto (follow system)', 'luma-commerce' ),
            'light' => __( 'Light', 'luma-commerce' ),
            'dark'  => __( 'Dark', 'luma-commerce' ),
        ),
    ) );
    $wp_customize->add_setting( 'luma_scheme_toggle', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_scheme_toggle', array(
        'label'       => __( 'Show a light/dark switch in the header', 'luma-commerce' ),
        'description' => __( 'The visitor’s choice is remembered on their own device only.', 'luma-commerce' ),
        'section'     => 'luma_layout',
        'type'        => 'checkbox',
    ) );
    foreach ( array(
        'luma_show_breadcrumbs'  => __( 'Show breadcrumbs', 'luma-commerce' ),
        'luma_show_related_posts' => __( 'Show related posts on articles', 'luma-commerce' ),
        'luma_reading_progress'  => __( 'Show a reading progress bar on articles', 'luma-commerce' ),
        'luma_back_to_top'       => __( 'Show a back-to-top control', 'luma-commerce' ),
    ) as $chrome_setting => $chrome_label ) {
        $wp_customize->add_setting( $chrome_setting, array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean', 'transport' => 'refresh' ) );
        $wp_customize->add_control( $chrome_setting, array( 'label' => $chrome_label, 'section' => 'luma_layout', 'type' => 'checkbox' ) );
    }

    $wp_customize->add_section( 'luma_woocommerce', array( 'title' => __( 'Luma WooCommerce', 'luma-commerce' ), 'priority' => 31 ) );
    $wp_customize->add_setting( 'luma_product_columns', array( 'default' => 4, 'sanitize_callback' => function( $value ) { return max( 2, min( 5, absint( $value ) ) ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_product_columns', array( 'label' => __( 'Product columns', 'luma-commerce' ), 'section' => 'luma_woocommerce', 'type' => 'number', 'input_attrs' => array( 'min' => 2, 'max' => 5, 'step' => 1 ) ) );
    $wp_customize->add_setting( 'luma_products_per_page', array( 'default' => 12, 'sanitize_callback' => function( $value ) { return max( 4, min( 48, absint( $value ) ) ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_products_per_page', array( 'label' => __( 'Products per page', 'luma-commerce' ), 'section' => 'luma_woocommerce', 'type' => 'number', 'input_attrs' => array( 'min' => 4, 'max' => 48, 'step' => 4 ) ) );
    foreach ( array( 'luma_gallery_zoom' => __( 'Gallery zoom', 'luma-commerce' ), 'luma_gallery_lightbox' => __( 'Gallery lightbox', 'luma-commerce' ), 'luma_gallery_slider' => __( 'Gallery slider', 'luma-commerce' ) ) as $setting => $label ) { $wp_customize->add_setting( $setting, array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean', 'transport' => 'refresh' ) ); $wp_customize->add_control( $setting, array( 'label' => $label, 'section' => 'luma_woocommerce', 'type' => 'checkbox' ) ); }

    $wp_customize->add_section( 'luma_typography', array( 'title' => __( 'Luma Typography', 'luma-commerce' ), 'priority' => 32 ) );
    $heading_choices = array( 'narrow' => 'Arial Narrow, Helvetica Neue, Arial, sans-serif', 'sans' => 'Helvetica Neue, Arial, sans-serif', 'serif' => 'Georgia, serif' );
    $body_choices = array( 'system' => 'Inter, ui-sans-serif, system-ui, sans-serif', 'sans' => 'Helvetica Neue, Arial, sans-serif', 'serif' => 'Georgia, serif' );
    $wp_customize->add_setting( 'luma_heading_font', array( 'default' => 'narrow', 'sanitize_callback' => function( $value ) use ( $heading_choices ) { return luma_commerce_sanitize_choice( $value, $heading_choices ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_heading_font', array( 'label' => __( 'Heading font', 'luma-commerce' ), 'section' => 'luma_typography', 'type' => 'select', 'choices' => array( 'narrow' => __( 'Condensed sans', 'luma-commerce' ), 'sans' => __( 'Clean sans', 'luma-commerce' ), 'serif' => __( 'Editorial serif', 'luma-commerce' ) ) ) );
    $wp_customize->add_setting( 'luma_body_font', array( 'default' => 'system', 'sanitize_callback' => function( $value ) use ( $body_choices ) { return luma_commerce_sanitize_choice( $value, $body_choices ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_body_font', array( 'label' => __( 'Body font', 'luma-commerce' ), 'section' => 'luma_typography', 'type' => 'select', 'choices' => array( 'system' => __( 'System sans', 'luma-commerce' ), 'sans' => __( 'Clean sans', 'luma-commerce' ), 'serif' => __( 'Editorial serif', 'luma-commerce' ) ) ) );
    $wp_customize->add_setting( 'luma_heading_scale', array( 'default' => 1, 'sanitize_callback' => 'luma_commerce_sanitize_scale', 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_heading_scale', array( 'label' => __( 'Heading scale', 'luma-commerce' ), 'section' => 'luma_typography', 'type' => 'number', 'input_attrs' => array( 'min' => .85, 'max' => 1.15, 'step' => .05 ) ) );
    $wp_customize->add_setting( 'luma_body_size', array( 'default' => 15, 'sanitize_callback' => function( $value ) { return max( 13, min( 18, absint( $value ) ) ); }, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'luma_body_size', array( 'label' => __( 'Body size', 'luma-commerce' ), 'section' => 'luma_typography', 'type' => 'number', 'input_attrs' => array( 'min' => 13, 'max' => 18, 'step' => 1 ) ) );
}
add_action( 'customize_register', 'luma_commerce_customize_register' );

function luma_commerce_show_author_credit() {
    return (bool) get_theme_mod( 'luma_show_author_credit', false );
}

function luma_commerce_cart_count() {
    if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && is_object( WC()->cart ) ) {
        return (int) WC()->cart->get_cart_contents_count();
    }
    return 0;
}

/**
 * Accessible label for a count badge.
 *
 * The bag and wish-list links carry an aria-label, which replaces their inner
 * content for assistive technology — so the visible count was never announced.
 * This rebuilds a label that includes it.
 *
 * @param string $base  Base label, e.g. "View shopping bag".
 * @param int    $count Current item count.
 * @return string
 */
function luma_commerce_count_label( $base, $count ) {
    $count = (int) $count;
    if ( 0 === $count ) {
        return sprintf( '%1$s (%2$s)', $base, __( 'empty', 'luma-commerce' ) );
    }
    return sprintf(
        /* translators: 1: link purpose, 2: number of items. */
        _n( '%1$s, %2$d item', '%1$s, %2$d items', $count, 'luma-commerce' ),
        $base,
        $count
    );
}

function luma_commerce_cart_link() {
    $url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    $count = luma_commerce_cart_count();
    printf(
        '<a class="luma-icon-link luma-bag-link" href="%1$s" aria-label="%2$s"><span class="luma-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 8h14l-1 13H6L5 8Z"></path><path d="M9 8V6a3 3 0 0 1 6 0v2"></path></svg></span><span class="luma-icon-label">%3$s</span><span class="luma-cart-count" data-luma-cart-count>%4$d</span></a>',
        esc_url( $url ),
        esc_attr( luma_commerce_count_label( __( 'View shopping bag', 'luma-commerce' ), $count ) ),
        esc_html__( 'Bag', 'luma-commerce' ),
        $count
    );
}

function luma_commerce_comment( $comment, $args, $depth ) {
    $tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
    ?>
    <<?php echo tag_escape( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( 'luma-comment', $comment ); ?>>
        <article class="luma-comment__body">
            <header class="luma-comment__meta">
                <div class="luma-comment__author"><?php echo get_avatar( $comment, 48 ); ?><strong><?php echo wp_kses_post( get_comment_author_link() ); ?></strong></div>
                <a href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><time datetime="<?php echo esc_attr( get_comment_time( 'c' ) ); ?>"><?php echo esc_html( get_comment_date() ); ?></time></a>
            </header>
            <?php if ( '0' === (string) $comment->comment_approved ) : ?><p class="luma-comment__moderation"><?php esc_html_e( 'Your comment is awaiting moderation.', 'luma-commerce' ); ?></p><?php endif; ?>
            <div class="luma-comment__text"><?php comment_text(); ?></div>
            <div class="luma-comment__reply"><?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?></div>
        </article>
    <?php
    if ( 'div' !== $args['style'] ) echo '</' . tag_escape( $tag ) . '>';
}

function luma_commerce_comment_reply_link( $link ) {
    return str_replace( 'comment-reply-link', 'comment-reply-link luma-button luma-button--small', $link );
}
add_filter( 'comment_reply_link', 'luma_commerce_comment_reply_link' );

/* ==========================================================================
   Luma 1.33.0 — color scheme, breadcrumbs, related posts and page chrome.
   ========================================================================== */

/**
 * Merchant default color scheme: light, dark or auto (follow the OS).
 *
 * @return string
 */
function luma_commerce_color_scheme() {
    return luma_commerce_sanitize_choice(
        get_theme_mod( 'luma_color_scheme', 'auto' ),
        array( 'auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark' )
    );
}

/**
 * Expose the merchant default on <html> so the pre-paint script and CSS can
 * agree without a flash of the wrong scheme.
 *
 * @param string $output Existing language attributes.
 * @return string
 */
function luma_commerce_language_attributes( $output ) {
    return $output . ' data-luma-scheme="' . esc_attr( luma_commerce_color_scheme() ) . '"';
}
add_filter( 'language_attributes', 'luma_commerce_language_attributes' );

/**
 * Apply a stored visitor preference before first paint.
 *
 * Runs in <head> so the browser never renders the light scheme and then
 * repaints dark. Deliberately tiny and dependency-free.
 */
function luma_commerce_scheme_bootstrap() {
    if ( ! get_theme_mod( 'luma_scheme_toggle', true ) ) {
        return;
    }
    ?>
<script id="luma-scheme-bootstrap">
(function () {
    var root = document.documentElement;
    try {
        var stored = localStorage.getItem('lumaScheme');
        if (stored === 'dark' || stored === 'light') {
            root.setAttribute('data-luma-theme', stored);
        } else if (root.getAttribute('data-luma-scheme') === 'dark') {
            root.setAttribute('data-luma-theme', 'dark');
        } else if (root.getAttribute('data-luma-scheme') === 'light') {
            root.setAttribute('data-luma-theme', 'light');
        }
    } catch (error) {
        /* Private browsing or blocked storage: fall back to the CSS default. */
    }
    root.classList.add('luma-scheme-ready');
}());
</script>
    <?php
}
add_action( 'wp_head', 'luma_commerce_scheme_bootstrap', 2 );

/**
 * Light/dark toggle. Rendered by header.php; hidden by CSS until the
 * bootstrap script marks the document as scheme-ready, so a no-JS visitor
 * never sees a control that cannot work.
 */
function luma_commerce_scheme_toggle() {
    if ( ! get_theme_mod( 'luma_scheme_toggle', true ) ) {
        return;
    }
    ?>
<button class="luma-scheme-toggle" type="button" data-luma-scheme-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'Switch to dark theme', 'luma-commerce' ); ?>">
    <span class="luma-icon" aria-hidden="true">
        <svg class="luma-scheme-icon--sun" viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="4.2"></circle><path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6"></path></svg>
        <svg class="luma-scheme-icon--moon" viewBox="0 0 24 24" focusable="false"><path d="M20 14.4A8.4 8.4 0 0 1 9.6 4a8.4 8.4 0 1 0 10.4 10.4Z"></path></svg>
    </span>
</button>
    <?php
}

/**
 * Breadcrumb trail for WooCommerce, posts, archives, pages and search.
 *
 * Replaces the WooCommerce default breadcrumb so the markup matches the rest
 * of the theme and carries a valid BreadcrumbList payload.
 *
 * @param array $args Optional. Override `echo`.
 */
function luma_commerce_breadcrumbs( $args = array() ) {
    if ( ! get_theme_mod( 'luma_show_breadcrumbs', true ) ) {
        return array();
    }
    if ( is_front_page() ) {
        return array();
    }

    $args = wp_parse_args( $args, array( 'echo' => true ) );
    $home = array( 'label' => __( 'Home', 'luma-commerce' ), 'url' => home_url( '/' ) );
    $trail = array( $home );
    $current = '';

    if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
        if ( is_product() ) {
            $terms = get_the_terms( get_the_ID(), 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $term = $terms[0];
                $trail[] = array( 'label' => $term->name, 'url' => get_term_link( $term ) );
            }
            $current = get_the_title();
        } elseif ( is_product_category() || is_product_tag() ) {
            $term = get_queried_object();
            $parent_ids = $term && $term->parent ? get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) : array();
            foreach ( array_reverse( $parent_ids ) as $parent_id ) {
                $parent = get_term( $parent_id, $term->taxonomy );
                if ( $parent && ! is_wp_error( $parent ) ) {
                    $trail[] = array( 'label' => $parent->name, 'url' => get_term_link( $parent ) );
                }
            }
            $current = $term ? $term->name : '';
        } elseif ( is_cart() ) {
            $current = __( 'Shopping bag', 'luma-commerce' );
        } elseif ( is_checkout() ) {
            $current = __( 'Checkout', 'luma-commerce' );
        } elseif ( is_account_page() ) {
            $current = __( 'My account', 'luma-commerce' );
        } else {
            $current = __( 'Shop', 'luma-commerce' );
        }
    } elseif ( is_singular( 'post' ) ) {
        $blog_id = (int) get_option( 'page_for_posts' );
        if ( $blog_id ) {
            $trail[] = array( 'label' => get_the_title( $blog_id ), 'url' => get_permalink( $blog_id ) );
        }
        $categories = get_the_category();
        if ( $categories ) {
            $trail[] = array( 'label' => $categories[0]->name, 'url' => get_category_link( $categories[0] ) );
        }
        $current = get_the_title();
    } elseif ( is_page() ) {
        foreach ( array_reverse( get_ancestors( get_the_ID(), 'page', 'post_type' ) ) as $ancestor_id ) {
            $trail[] = array( 'label' => get_the_title( $ancestor_id ), 'url' => get_permalink( $ancestor_id ) );
        }
        $current = get_the_title();
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $current = wp_strip_all_tags( get_the_archive_title() );
    } elseif ( is_search() ) {
        $current = sprintf( /* translators: %s: search query. */ __( 'Search results for “%s”', 'luma-commerce' ), get_search_query() );
    } elseif ( is_404() ) {
        $current = __( 'Page not found', 'luma-commerce' );
    } elseif ( is_home() && ! is_front_page() ) {
        $current = __( 'The journal', 'luma-commerce' );
    } elseif ( is_post_type_archive() ) {
        $current = post_type_archive_title( '', false );
    } elseif ( is_author() ) {
        $current = get_the_author();
    } elseif ( is_year() || is_month() || is_day() ) {
        $current = wp_strip_all_tags( get_the_archive_title() );
    }

    if ( $current ) {
        $trail[] = array( 'label' => $current, 'url' => '' );
    }

    if ( count( $trail ) < 2 ) {
        return array();
    }

    $html = '<nav class="luma-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'luma-commerce' ) . '"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
    $position = 1;
    foreach ( $trail as $crumb ) {
        $is_last = empty( $crumb['url'] );
        $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        if ( $is_last ) {
            $html .= '<span itemprop="name" aria-current="page">' . esc_html( $crumb['label'] ) . '</span>';
        } else {
            $html .= '<a itemprop="item" href="' . esc_url( $crumb['url'] ) . '"><span itemprop="name">' . esc_html( $crumb['label'] ) . '</span></a>';
        }
        $html .= '<meta itemprop="position" content="' . esc_attr( $position ) . '"></li>';
        $position++;
    }
    $html .= '</ol></nav>';

    if ( $args['echo'] ) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url()/esc_html() above.
    }
    return $trail;
}

/**
 * WooCommerce ships its own breadcrumb on this hook; Luma renders a unified
 * trail for both commerce and editorial contexts instead.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
function luma_commerce_wc_breadcrumbs() {
    echo '<div class="luma-container">';
    luma_commerce_breadcrumbs();
    echo '</div>';
}
add_action( 'woocommerce_before_main_content', 'luma_commerce_wc_breadcrumbs', 20 );

/**
 * Related posts, matched by shared categories then tags, excluding the current
 * post. Only real published posts are shown — never padded with filler.
 *
 * @param int $limit Number of cards.
 */
function luma_commerce_related_posts( $limit = 3 ) {
    if ( ! is_singular( 'post' ) || ! get_theme_mod( 'luma_show_related_posts', true ) ) {
        return;
    }
    $post_ids = array();
    $categories = wp_get_post_categories( get_the_ID() );
    $tags = wp_get_post_tags( get_the_ID(), array( 'fields' => 'ids' ) );

    if ( $categories ) {
        $related = get_posts(
            array(
                'posts_per_page'      => $limit * 2,
                'post__not_in'        => array( get_the_ID() ),
                'category__in'        => $categories,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
                'fields'              => 'ids',
                'post_status'         => 'publish',
            )
        );
        $post_ids = array_merge( $post_ids, $related );
    }
    if ( count( $post_ids ) < $limit && $tags ) {
        $related = get_posts(
            array(
                'posts_per_page'      => $limit,
                'post__not_in'        => array_merge( array( get_the_ID() ), $post_ids ),
                'tag__in'             => $tags,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
                'fields'              => 'ids',
                'post_status'         => 'publish',
            )
        );
        $post_ids = array_merge( $post_ids, $related );
    }
    $post_ids = array_slice( array_values( array_unique( array_map( 'absint', $post_ids ) ) ), 0, $limit );
    if ( ! $post_ids ) {
        return;
    }

    $related_posts = get_posts( array( 'post__in' => $post_ids, 'posts_per_page' => $limit, 'orderby' => 'post__in', 'post_status' => 'publish' ) );
    if ( ! $related_posts ) {
        return;
    }
    ?>
<section class="luma-related" aria-labelledby="luma-related-title">
    <div class="luma-related__heading">
        <h2 id="luma-related-title"><?php esc_html_e( 'Keep reading', 'luma-commerce' ); ?></h2>
        <a class="luma-text-link" href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' ) ); ?>"><?php esc_html_e( 'All stories', 'luma-commerce' ); ?> <span aria-hidden="true">↗</span></a>
    </div>
    <div class="luma-post-grid">
        <?php
        foreach ( $related_posts as $related_post ) {
            setup_postdata( $GLOBALS['post'] = $related_post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
            get_template_part( 'template-parts/content', get_post_type() );
        }
        wp_reset_postdata();
        ?>
    </div>
</section>
    <?php
}

/**
 * Page chrome emitted once per request: reading progress, back to top.
 */
function luma_commerce_page_chrome() {
    if ( is_singular( 'post' ) && get_theme_mod( 'luma_reading_progress', true ) ) {
        echo '<div class="luma-reading-progress" aria-hidden="true"><span class="luma-reading-progress__bar" data-luma-progress></span></div>';
    }
    if ( get_theme_mod( 'luma_back_to_top', true ) ) {
        echo '<a class="luma-to-top" href="#top" data-luma-to-top aria-label="' . esc_attr__( 'Back to top', 'luma-commerce' ) . '"><span aria-hidden="true">↑</span></a>';
    }
}
add_action( 'wp_footer', 'luma_commerce_page_chrome', 5 );

/**
 * Describe Luma's data handling in Settings → Privacy so the merchant has an
 * accurate starting point for their own policy.
 */
function luma_commerce_privacy_policy_content() {
    $content = '<h2>' . esc_html__( 'Luma theme', 'luma-commerce' ) . '</h2>';
    $content .= '<p>' . esc_html__( 'The Luma theme stores a colour-scheme preference and a shop grid/list preference in your browser’s local storage. Both stay on the device, are never sent to the server and can be cleared with the browser’s site data.', 'luma-commerce' ) . '</p>';
    $content .= '<p>' . esc_html__( 'No third-party font, analytics or advertising script is loaded by the theme.', 'luma-commerce' ) . '</p>';
    wp_add_privacy_policy_content( __( 'Luma theme', 'luma-commerce' ), $content );
}
add_action( 'admin_init', 'luma_commerce_privacy_policy_content' );
