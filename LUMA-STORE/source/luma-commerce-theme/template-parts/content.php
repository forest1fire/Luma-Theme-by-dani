<?php
/**
 * Post card used by the blog index, archives and search results.
 *
 * @package LumaCommerce
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'luma-post-card' ); ?>>
    <a class="luma-post-card__image" href="<?php echo esc_url( get_permalink() ); ?>" tabindex="-1" aria-hidden="true">
        <?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); } else { echo '<span class="luma-image-placeholder"></span>'; } ?>
    </a>
    <div class="luma-post-card__body">
        <p class="luma-kicker">
            <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
        </p>
        <h2 class="luma-post-card__title"><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h2>
        <?php if ( has_excerpt() || get_the_excerpt() ) : ?>
            <p class="luma-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
        <?php endif; ?>
        <a class="luma-text-link" href="<?php echo esc_url( get_permalink() ); ?>">
            <?php
            printf(
                /* translators: %s: post title. */
                esc_html__( 'Read story: %s', 'luma-commerce' ),
                esc_html( get_the_title() )
            );
            ?>
            <span aria-hidden="true">↗</span>
        </a>
    </div>
</article>
