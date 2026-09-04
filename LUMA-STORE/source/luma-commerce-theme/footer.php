<?php
/**
 * The footer.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! luma_commerce_elementor_location( 'footer' ) ) : ?>
    <footer class="luma-site-footer">
        <div class="luma-container">
            <div class="luma-footer-grid">
                <div class="luma-footer-brand">
                    <a class="luma-wordmark luma-wordmark--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="luma-wordmark__name"><?php echo esc_html( luma_commerce_brand_name() ); ?><sup>®</sup></span><?php if ( luma_commerce_show_author_credit() ) : ?><span class="luma-wordmark__byline">By CodeWithDani</span><?php endif; ?></a>
                    <p><?php esc_html_e( 'Modern wardrobe pieces with a considered point of view.', 'luma-commerce' ); ?></p>
                    <?php $luma_socials = array(
                        'luma_instagram_url' => array( 'label' => __( 'Instagram', 'luma-commerce' ), 'short' => 'ig' ),
                        'luma_facebook_url'  => array( 'label' => __( 'Facebook', 'luma-commerce' ), 'short' => 'fb' ),
                        'luma_tiktok_url'    => array( 'label' => __( 'TikTok', 'luma-commerce' ), 'short' => 'tk' ),
                    ); $luma_social_links = array(); foreach ( $luma_socials as $setting => $social ) { $url = esc_url( get_theme_mod( $setting, '' ) ); if ( $url ) $luma_social_links[] = '<a href="' . $url . '" aria-label="' . esc_attr( $social['label'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $social['short'] ) . '</a>'; } if ( $luma_social_links ) : ?><div class="luma-socials"><?php echo wp_kses( implode( '', $luma_social_links ), array( 'a' => array( 'href' => true, 'aria-label' => true, 'target' => true, 'rel' => true ) ) ); ?></div><?php endif; ?>
                </div>
                <div><h2><?php esc_html_e( 'Explore', 'luma-commerce' ); ?></h2><?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'fallback_cb' => 'luma_commerce_fallback_footer_menu', 'menu_class' => 'luma-footer-menu' ) ); ?></div>
                <div><h2><?php esc_html_e( 'Need help?', 'luma-commerce' ); ?></h2><ul class="luma-footer-menu"><?php
                    $luma_help_links = array(
                        '/contact/'          => __( 'Contact us', 'luma-commerce' ),
                        '/shipping-returns/' => __( 'Shipping & returns', 'luma-commerce' ),
                        '/size-guide/'       => __( 'Size guide', 'luma-commerce' ),
                        '/faqs/'             => __( 'FAQs', 'luma-commerce' ),
                    );
                    foreach ( $luma_help_links as $luma_help_path => $luma_help_label ) :
                        ?><li><a href="<?php echo esc_url( home_url( $luma_help_path ) ); ?>"><?php echo esc_html( $luma_help_label ); ?></a></li><?php
                    endforeach;
                ?></ul></div>
                <div class="luma-footer-newsletter"><h2><?php esc_html_e( 'The Luma edit', 'luma-commerce' ); ?></h2><p><?php esc_html_e( 'Sign up for first access to drops, edits, and private offers.', 'luma-commerce' ); ?></p><?php
                    /*
                     * The lead-capture handler ships with Luma Core. Rendering the
                     * form without it produced an action="#" form that jumped to the
                     * top of the page and silently discarded the address.
                     */
                    $luma_lead_ready = class_exists( 'WooCommerce' ) && function_exists( 'luma_core_option' );
                    $luma_email_id   = wp_unique_id( 'luma-email-' );
                    if ( $luma_lead_ready ) :
                        ?><form class="luma-newsletter-form" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" data-luma-lead><label class="screen-reader-text" for="<?php echo esc_attr( $luma_email_id ); ?>"><?php esc_html_e( 'Email address', 'luma-commerce' ); ?></label><input id="<?php echo esc_attr( $luma_email_id ); ?>" type="email" name="email" placeholder="<?php esc_attr_e( 'Email address', 'luma-commerce' ); ?>" autocomplete="email" required><input class="luma-form-trap" type="text" name="luma_website" tabindex="-1" autocomplete="off" aria-hidden="true"><button type="submit" aria-label="<?php esc_attr_e( 'Subscribe', 'luma-commerce' ); ?>">↗</button><span class="luma-lead-status" aria-live="polite"></span></form><?php
                    else :
                        ?><p class="luma-footer-note"><?php esc_html_e( 'Activate Luma Core to enable newsletter sign-up.', 'luma-commerce' ); ?></p><?php
                    endif;
                ?></div>
            </div>
            <?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?><div class="luma-footer-widgets"><?php dynamic_sidebar( 'footer-widgets' ); ?></div><?php endif; ?>
            <div class="luma-footer-bottom"><span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( luma_commerce_brand_name() ); ?></span><span><?php esc_html_e( 'Designed for WooCommerce · Built for Elementor', 'luma-commerce' ); ?></span></div>
        </div>
    </footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
