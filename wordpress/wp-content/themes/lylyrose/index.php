<?php
/**
 * Blog index / fallback template.
 *
 * @package Digikala
 */

get_header();
?>
<main class="dk-main">
    <div class="dk-container">
        <?php if ( have_posts() ) : ?>
            <div class="dk-editorial">
                <?php while ( have_posts() ) : the_post(); ?>
                    <article class="dk-editorial-card">
                        <span class="dk-editorial-tag"><?php echo esc_html( get_the_date() ); ?></span>
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
                        <a class="dk-section-more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'ادامه مطلب ←', 'lylyrose' ); ?></a>
                    </article>
                <?php endwhile; ?>
            </div>
            <div class="dk-pagination">
                <?php the_posts_pagination(); ?>
            </div>
        <?php else : ?>
            <div class="dk-no-products">
                <h2><?php esc_html_e( 'مطلبی یافت نشد', 'lylyrose' ); ?></h2>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
