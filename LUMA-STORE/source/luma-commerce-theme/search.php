<?php
get_header();
?>
<main id="primary" class="luma-main"><div class="luma-container luma-content-wrap"><?php luma_commerce_breadcrumbs(); ?><p class="luma-kicker"><?php esc_html_e( 'Search', 'luma-commerce' ); ?></p><h1 class="luma-page-title"><?php printf( esc_html__( 'Results for “%s”', 'luma-commerce' ), esc_html( get_search_query() ) ); ?></h1><?php if ( have_posts() ) : ?><div class="luma-post-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', get_post_type() ); endwhile; ?></div><div class="luma-pagination"><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '← ' . esc_html__( 'Previous', 'luma-commerce' ), 'next_text' => esc_html__( 'Next', 'luma-commerce' ) . ' →' ) ); ?></div><?php else : ?><div class="luma-empty-state"><p><?php esc_html_e( 'No results found. Try another search.', 'luma-commerce' ); ?></p><?php get_search_form(); ?></div><?php endif; ?></div></main>
<?php get_footer(); ?>
