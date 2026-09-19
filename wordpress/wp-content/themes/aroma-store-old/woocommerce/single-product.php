<?php
/**
 * Single Product Template - Aroma Store
 * @package Aroma_Store
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header(); ?>

<div id="content">
<div class="dk-container" style="padding: 32px 16px;">

<?php while ( have_posts() ) : the_post(); ?>

    <?php wc_get_template_part( 'content', 'single-product' ); ?>

<?php endwhile; ?>

</div>
</div>

<?php get_footer(); ?>

</content>