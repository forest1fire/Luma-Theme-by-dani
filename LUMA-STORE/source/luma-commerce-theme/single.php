<?php
/**
 * Single post template.
 *
 * @package Luma
 */
get_header();
if ( ! luma_commerce_elementor_location( 'single' ) ) :
?>
<main id="primary" class="luma-main"><div class="luma-container luma-content-wrap">
<?php luma_commerce_breadcrumbs(); ?>
<?php while ( have_posts() ) : the_post(); ?>
    <article <?php post_class( 'luma-article' ); ?>>
        <header class="luma-article__header">
            <p class="luma-kicker"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_author() ); ?></p>
            <?php the_title( '<h1 class="luma-page-title">', '</h1>' ); ?>
        </header>
        <?php if ( has_post_thumbnail() ) : ?><figure class="luma-article__image"><?php the_post_thumbnail( 'full' ); ?></figure><?php endif; ?>
        <div class="luma-entry-content">
            <?php the_content(); ?>
            <?php wp_link_pages( array( 'before' => '<nav class="luma-page-links" aria-label="' . esc_attr__( 'Post pages', 'luma-commerce' ) . '"><span>' . esc_html__( 'Pages:', 'luma-commerce' ) . '</span>', 'after' => '</nav>' ) ); ?>
        </div>
        <?php $tags = get_the_tags(); if ( $tags ) : ?><footer class="luma-article__footer"><div class="luma-article__tags"><?php foreach ( $tags as $tag ) : ?><a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a><?php endforeach; ?></div></footer><?php endif; ?>
    </article>
    <div class="luma-post-navigation"><?php the_post_navigation( array( 'prev_text' => '<span class="luma-kicker">' . esc_html__( 'Previous', 'luma-commerce' ) . '</span><strong>%title</strong>', 'next_text' => '<span class="luma-kicker">' . esc_html__( 'Next', 'luma-commerce' ) . '</span><strong>%title</strong>' ) ); ?></div>
    <?php if ( comments_open() || get_comments_number() ) comments_template(); ?>
    <?php luma_commerce_related_posts(); ?>
<?php endwhile; ?>
</div></main>
<?php endif; get_footer(); ?>
