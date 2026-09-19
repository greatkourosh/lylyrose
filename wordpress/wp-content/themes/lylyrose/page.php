<?php
/**
 * Static page template.
 *
 * WooCommerce pages (checkout, cart, my-account, wishlist) fall through to
 * this template when WC doesn't remap them, so render the content full-width
 * instead of the blog-style editorial cards from index.php.
 *
 * @package Digikala
 */

get_header();
?>
<main class="dk-main">
	<div class="dk-container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'dk-page-content' ); ?> style="background:#fff;border-radius:var(--dk-radius);box-shadow:var(--dk-shadow);padding:24px;">
				<h1 class="dk-page-title" style="font-size:17px;color:var(--dk-ink);margin:0 0 16px;"><?php the_title(); ?></h1>
				<?php
				the_content();
				wp_link_pages();
				?>
			</article>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
