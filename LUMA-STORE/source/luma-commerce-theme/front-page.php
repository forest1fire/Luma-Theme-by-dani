<?php
/**
 * Front page fallback. The complete layout can be replaced with Elementor Pro.
 *
 * @package LumaCommerce
 */
get_header();

$elementor_editing = 'builder' === get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true );
$has_content       = trim( (string) get_post_field( 'post_content', get_queried_object_id() ) );

/*
 * When "Your homepage displays" is set to latest posts, WordPress routes the
 * front page here with no queried object. Without this branch the static Luma
 * hero would render and the blog posts would never be shown at all.
 */
$is_posts_front_page = 'posts' === get_option( 'show_on_front' );
?>
<main id="primary" class="luma-main luma-home">
<?php if ( $is_posts_front_page ) : ?>
    <div class="luma-container luma-content-wrap">
        <header class="luma-archive-header">
            <p class="luma-kicker"><?php esc_html_e( 'The journal', 'luma-commerce' ); ?></p>
            <h1 class="luma-page-title"><?php echo esc_html( luma_commerce_brand_name() ); ?></h1>
        </header>
        <?php if ( have_posts() ) : ?>
            <div class="luma-post-grid">
                <?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', get_post_type() ); endwhile; ?>
            </div>
            <div class="luma-pagination">
                <?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '← ' . esc_html__( 'Previous', 'luma-commerce' ), 'next_text' => esc_html__( 'Next', 'luma-commerce' ) . ' →' ) ); ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'No content found.', 'luma-commerce' ); ?></p>
        <?php endif; ?>
    </div>
<?php elseif ( $elementor_editing || $has_content ) : ?>
    <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
<?php else : ?>
    <section class="luma-home-hero">
        <div class="luma-home-hero__copy">
            <p class="luma-kicker"><?php esc_html_e( 'Autumn / winter 2026', 'luma-commerce' ); ?></p>
            <h1><?php esc_html_e( 'Make', 'luma-commerce' ); ?><br><em><?php esc_html_e( 'room', 'luma-commerce' ); ?></em><br><?php esc_html_e( 'for more.', 'luma-commerce' ); ?></h1>
            <p class="luma-home-hero__intro"><?php esc_html_e( 'A sharper edit of everyday pieces, designed to move with you from first light to last call.', 'luma-commerce' ); ?></p>
            <a class="luma-button" href="<?php echo esc_url( luma_commerce_shop_url() ); ?>"><?php esc_html_e( 'Explore the edit', 'luma-commerce' ); ?> <span>↗</span></a>
        </div>
        <div class="luma-home-hero__visual"><img src="<?php echo esc_url( luma_commerce_asset( 'images/luma-hero.jpg' ) ); ?>" alt="<?php esc_attr_e( 'Luma autumn winter campaign', 'luma-commerce' ); ?>" width="1584" height="672" loading="eager" fetchpriority="high" decoding="async"><span class="luma-home-hero__stamp"><?php esc_html_e( 'Built for', 'luma-commerce' ); ?><br><?php esc_html_e( 'everyday', 'luma-commerce' ); ?><br><?php esc_html_e( 'motion', 'luma-commerce' ); ?></span></div>
    </section>

    <section class="luma-home-section luma-home-categories"><div class="luma-container">
        <div class="luma-section-heading"><h2><?php esc_html_e( 'Shop by mood', 'luma-commerce' ); ?></h2><a class="luma-text-link" href="<?php echo esc_url( luma_commerce_shop_url() ); ?>"><?php esc_html_e( 'View all', 'luma-commerce' ); ?> <span>↗</span></a></div>
        <div class="luma-category-grid">
            <a class="luma-category-card" style="--luma-category-image:url('<?php echo esc_url( luma_commerce_asset( 'images/luma-men.jpg' ) ); ?>')" href="<?php echo esc_url( luma_commerce_category_url( 'men' ) ); ?>"><span><strong><?php esc_html_e( 'Men', 'luma-commerce' ); ?></strong><small><?php esc_html_e( 'Explore the edit', 'luma-commerce' ); ?> ↗</small></span></a>
            <a class="luma-category-card" style="--luma-category-image:url('<?php echo esc_url( luma_commerce_asset( 'images/luma-women.jpg' ) ); ?>')" href="<?php echo esc_url( luma_commerce_category_url( 'women' ) ); ?>"><span><strong><?php esc_html_e( 'Women', 'luma-commerce' ); ?></strong><small><?php esc_html_e( 'Explore the edit', 'luma-commerce' ); ?> ↗</small></span></a>
            <a class="luma-category-card" style="--luma-category-image:url('<?php echo esc_url( luma_commerce_asset( 'images/luma-drop.jpg' ) ); ?>')" href="<?php echo esc_url( luma_commerce_category_url( 'juniors' ) ); ?>"><span><strong><?php esc_html_e( 'Juniors', 'luma-commerce' ); ?></strong><small><?php esc_html_e( 'Explore the edit', 'luma-commerce' ); ?> ↗</small></span></a>
        </div>
    </div></section>

    <?php if ( shortcode_exists( 'luma_sale_bar' ) ) echo do_shortcode( '[luma_sale_bar]' ); ?>
    <?php if ( shortcode_exists( 'luma_coupon' ) || shortcode_exists( 'luma_countdown' ) ) : ?><div class="luma-container luma-home-conversion"><?php if ( shortcode_exists( 'luma_coupon' ) ) echo do_shortcode( '[luma_coupon]' ); if ( shortcode_exists( 'luma_countdown' ) ) echo do_shortcode( '[luma_countdown]' ); ?></div><?php endif; ?>

    <section class="luma-home-section"><div class="luma-container">
        <div class="luma-section-heading"><h2><?php esc_html_e( 'Just in', 'luma-commerce' ); ?></h2><a class="luma-text-link" href="<?php echo esc_url( luma_commerce_shop_url() ); ?>"><?php esc_html_e( 'Shop all', 'luma-commerce' ); ?> <span>↗</span></a></div>
        <div class="luma-home-products">
        <?php
        $products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => 8, 'status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
        if ( $products ) : foreach ( $products as $product ) :
            $badge = $product->is_on_sale() ? 'Sale' : ( $product->get_date_created() && $product->get_date_created()->getTimestamp() > strtotime( '-30 days' ) ? 'New' : '' );
            ?>
            <article class="luma-home-product"><a class="luma-home-product__image" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?><?php if ( $badge ) : ?><span><?php echo esc_html( $badge ); ?></span><?php endif; ?></a><div class="luma-home-product__meta"><h3><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3><div><?php echo wp_kses_post( $product->get_price_html() ); ?></div></div><div class="luma-home-product__actions"><a class="luma-home-product__view" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'View piece', 'luma-commerce' ); ?> <span>↗</span></a><?php if ( function_exists( 'luma_core_option' ) && luma_core_option( 'module_quick_view', true ) && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) : ?><button class="luma-quick-add luma-home-product__quick-add" type="button" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ esc_html__( 'Quick add %s to your bag', 'luma-commerce' ), $product->get_name() ) ); ?>"><?php esc_html_e( 'Quick add', 'luma-commerce' ); ?> <span>+</span></button><?php else : ?><a class="luma-home-product__options" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo $product->is_type( 'simple' ) ? esc_html__( 'View details', 'luma-commerce' ) : esc_html__( 'Choose options', 'luma-commerce' ); ?> <span>↗</span></a><?php endif; ?></div></article>
        <?php endforeach; else : ?>
            <div class="luma-home-empty"><p class="luma-kicker"><?php esc_html_e( 'Your first drop starts here', 'luma-commerce' ); ?></p><h3><?php esc_html_e( 'Add products in WooCommerce to populate this section.', 'luma-commerce' ); ?></h3><a class="luma-button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Add a product', 'luma-commerce' ); ?> <span>↗</span></a></div>
        <?php endif; ?>
        </div>
    </div></section>

    <?php if ( shortcode_exists( 'luma_trust_bar' ) ) : ?><div class="luma-container luma-home-trust"><?php echo do_shortcode( '[luma_trust_bar]' ); ?></div><?php endif; ?>

    <section class="luma-home-editorial"><div class="luma-home-editorial__copy"><p class="luma-kicker"><?php esc_html_e( 'The Luma uniform / 01', 'luma-commerce' ); ?></p><h2><?php esc_html_e( 'Less', 'luma-commerce' ); ?><br><?php esc_html_e( 'noise.', 'luma-commerce' ); ?><br><?php esc_html_e( 'More', 'luma-commerce' ); ?><br><?php esc_html_e( 'you.', 'luma-commerce' ); ?></h2><p><?php esc_html_e( 'Quiet textures, confident proportions and the pieces you reach for on repeat.', 'luma-commerce' ); ?></p><a class="luma-button" href="<?php echo esc_url( luma_commerce_shop_url() ); ?>"><?php esc_html_e( 'Meet the uniform', 'luma-commerce' ); ?> <span>↗</span></a></div><div class="luma-home-editorial__image"><img src="<?php echo esc_url( luma_commerce_asset( 'images/luma-drop.jpg' ) ); ?>" alt="<?php esc_attr_e( 'Luma streetwear detail', 'luma-commerce' ); ?>" width="768" height="1376" loading="lazy" decoding="async"></div></section>
<?php endif; ?>
</main>
<?php get_footer(); ?>
