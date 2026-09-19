<?php
/**
 * Template part for displaying no posts found
 */
?>
<section class="no-results not-found">
    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'aroma-store' ); ?></h1>
    </header>

    <div class="page-content">
        <?php if ( is_home() && ! is_front_page() ) : ?>
            <header class="page-header">
                <h1 class="page-title"><?php the_title(); ?></h1>
            </header>
            <?php
        endif;
        ?>
        <div class="page-content">
            <?php if ( is_search() ) : ?>
                <p><?php esc_html_e( 'No results found for your search query. Please try again with different keywords.', 'aroma-store' ); ?></p>
                <?php get_search_form(); ?>
            <?php else : ?>
                <p><?php esc_html_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'aroma-store' ); ?></p>
                <?php get_search_form(); ?>
            <?php endif; ?>
        </div>
    </div>
</section><!-- .no-results -->
