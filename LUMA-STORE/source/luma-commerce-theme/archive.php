<?php
get_header();
if ( ! luma_commerce_elementor_location( 'archive' ) ) :
?>
<main id="primary" class="luma-main"><div class="luma-container luma-content-wrap">
<?php luma_commerce_breadcrumbs(); ?>
<header class="luma-archive-header"><p class="luma-kicker"><?php esc_html_e( 'The journal', 'luma-commerce' ); ?></p><?php the_archive_title( '<h1 class="luma-page-title">', '</h1>' ); ?><?php the_archive_description( '<div class="luma-archive-description">', '</div>' ); ?></header>
<?php if ( have_posts() ) : ?><div class="luma-post-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', get_post_type() ); endwhile; ?></div><div class="luma-pagination"><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '← ' . esc_html__( 'Previous', 'luma-commerce' ), 'next_text' => esc_html__( 'Next', 'luma-commerce' ) . ' →' ) ); ?></div><?php else : ?><p><?php esc_html_e( 'Nothing here yet.', 'luma-commerce' ); ?></p><?php endif; ?>
</div></main>
<?php endif; get_footer(); ?>
