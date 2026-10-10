<?php
/**
 * Template Name: hair-needs
 *
 * @package lylyrose
 */

defined( 'ABSPATH' ) || exit;
get_header();

if ( ! class_exists( 'ASC_Needs_Finder' ) || ! function_exists( 'WC' ) ) {
	echo '<main class="dk-container"><p>' . esc_html__( 'این بخش در دسترس نیست.', 'lylyrose' ) . '</p></main>';
	get_footer();
	return;
}
?>
<main class="dk-container">
	<?php ASC_Needs_Finder::render( 'hair' ); ?>
</main>
<?php
get_footer();
