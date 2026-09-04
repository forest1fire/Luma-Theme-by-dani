<?php
/**
 * Comments template.
 *
 * @package LumaCommerce
 */
defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) return;
?>
<section id="comments" class="luma-comments" aria-labelledby="comments-title">
    <?php if ( have_comments() ) : ?>
        <p class="luma-kicker"><?php esc_html_e( 'The conversation', 'luma-commerce' ); ?></p>
        <h2 id="comments-title" class="luma-comments__title">
            <?php
            printf(
                esc_html( _n( '%s response', '%s responses', get_comments_number(), 'luma-commerce' ) ),
                number_format_i18n( get_comments_number() )
            );
            ?>
        </h2>
        <ol class="luma-comment-list">
            <?php
            wp_list_comments(
                array(
                    'style'       => 'ol',
                    'short_ping'  => true,
                    'avatar_size' => 48,
                    'callback'    => 'luma_commerce_comment',
                )
            );
            ?>
        </ol>
        <?php the_comments_navigation(); ?>
    <?php endif; ?>

    <?php if ( comments_open() ) : ?>
        <div class="luma-comment-form">
            <?php comment_form( array( 'class_form' => 'luma-comment-form__form' ) ); ?>
        </div>
    <?php elseif ( get_comments_number() ) : ?>
        <p class="luma-comments-closed"><?php esc_html_e( 'Comments are closed.', 'luma-commerce' ); ?></p>
    <?php endif; ?>
</section>
