<?php
/**
 * Static page template.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="luma-main luma-page-main"><div class="luma-container luma-content-wrap">
<?php luma_commerce_breadcrumbs(); ?>
<?php
while ( have_posts() ) :
    the_post();

    // Elementor-built pages carry their own headings, so only print the title
    // when the page is not being rendered by the builder.
    $luma_is_builder = 'builder' === get_post_meta( get_the_ID(), '_elementor_edit_mode', true );
    $luma_has_canvas = 'elementor_canvas' === get_post_meta( get_the_ID(), '_wp_page_template', true );
    ?>
    <article <?php post_class( 'luma-page-article' ); ?>>
        <?php if ( ! $luma_is_builder && ! $luma_has_canvas ) : ?>
            <header class="luma-page-header">
                <?php the_title( '<h1 class="luma-page-title">', '</h1>' ); ?>
            </header>
        <?php endif; ?>
        <div class="luma-entry-content">
            <?php
            the_content();
            wp_link_pages(
                array(
                    'before' => '<nav class="luma-page-links" aria-label="' . esc_attr__( 'Page sections', 'luma-commerce' ) . '"><span>' . esc_html__( 'Pages:', 'luma-commerce' ) . '</span>',
                    'after'  => '</nav>',
                )
            );
            ?>
        </div>
        <?php if ( comments_open() || get_comments_number() ) : comments_template(); endif; ?>
    </article>
<?php endwhile; ?>
</div></main>
<?php get_footer(); ?>
