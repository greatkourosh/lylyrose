<?php
/**
 * Template Name: عطرت رو پیدا کن
 *
 * The finder is a guided flow, so the page is the quiz or the result and
 * nothing else — no page content, no sidebar. Rendering lives in the plugin so
 * the scoring stays testable without a browser.
 *
 * @package lylyrose
 */

defined( 'ABSPATH' ) || exit;
get_header();

if ( ! class_exists( 'ASC_Perfume_Finder' ) || ! function_exists( 'WC' ) ) {
	echo '<main class="dk-container"><p>' . esc_html__( 'ابزار پیشنهاد عطر در دسترس نیست.', 'lylyrose' ) . '</p></main>';
	get_footer();
	return;
}
?>
<main class="asc-finder-page dk-container">
	<?php ASC_Perfume_Finder::render(); ?>
</main>
<?php
get_footer();
