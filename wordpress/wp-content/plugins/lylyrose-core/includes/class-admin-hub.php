<?php
/**
 * One admin page for every finder setting (Settings → گزینههای کوییزها).
 *
 * Each tab renders the existing class's own form. Every form posts to
 * options.php with its own settings group, so a save only touches the options
 * on that tab. The old standalone pages redirect here, so bookmarks still work.
 *
 * @package lylyrose-core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Admin_Hub {

	const PAGE = 'asc-finder-hub';

	/** Old standalone slug => tab that replaces it. */
	const LEGACY = array(
		'asc-finder-weights'  => 'perfume',
		'asc-finder-settings' => 'perfume',
		'asc-quiz-menu'       => 'perfume',
		'asc-finder-tiers'    => 'perfume',
	);

	public static function tabs() {
		return array(
			'perfume' => __( 'عطر', 'lylyrose-core' ),
			'skin'    => __( 'پوست', 'lylyrose-core' ),
			'hair'    => __( 'مو', 'lylyrose-core' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy' ) );
	}

	public static function admin_menu() {
		add_options_page(
			__( 'گزینههای کوییزها', 'lylyrose-core' ),
			__( 'گزینههای کوییزها', 'lylyrose-core' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		foreach ( array_keys( self::LEGACY ) as $slug ) {
			remove_submenu_page( 'options-general.php', $slug );
		}
	}

	public static function redirect_legacy() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! isset( self::LEGACY[ $page ] ) ) {
			return;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'tab' => self::LEGACY[ $page ] ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'perfume';
		return isset( self::tabs()[ $tab ] ) ? $tab : 'perfume';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$current = self::current_tab();
		echo '<div class="wrap" dir="rtl"><h1>' . esc_html__( 'گزینههای کوییزها', 'lylyrose-core' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( self::tabs() as $key => $label ) {
			$url = add_query_arg( array( 'page' => self::PAGE, 'tab' => $key ), admin_url( 'options-general.php' ) );
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( $url ),
				$key === $current ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( 'perfume' === $current ) {
			self::render_perfume();
		} else {
			self::render_needs( $current );
		}
		echo '</div>';
	}

	private static function render_perfume() {
		$parts = array(
			'ASC_Finder_Weights'  => __( 'وزنها', 'lylyrose-core' ),
			'ASC_Finder_Settings' => __( 'گزینههای کوییز', 'lylyrose-core' ),
			'ASC_Quiz_Menu'       => __( 'منوی کوییز', 'lylyrose-core' ),
			'ASC_Finder_Tiers'    => __( 'رده‌های قیمتی', 'lylyrose-core' ),
		);
		foreach ( $parts as $class => $title ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			echo '<h2>' . esc_html( $title ) . '</h2>';
			// Each section renders its own <div class="wrap"> and <h1> for the
			// standalone page. Inside the hub both are wrong — nested wraps and a
			// second h1 — so the outer wrapper is dropped and the heading demoted.
			$html = self::unwrap( self::capture( $class ) );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- section markup
		}
	}

	private static function capture( $class ) {
		ob_start();
		call_user_func( array( $class, 'render_admin' ) );
		return ob_get_clean();
	}

	/**
	 * Anchored, so only the section's own outer wrapper is removed.
	 */
	private static function unwrap( $html ) {
		$html = preg_replace( '/^\s*<div class="wrap"[^>]*>/', '', $html, 1 );
		$html = preg_replace( '/<\/div>\s*$/', '', $html, 1 );
		return preg_replace( '/<h1([^>]*)>/', '<h2$1>', $html, 1 );
	}

	private static function render_needs( $tab ) {
		$class = 'ASC_Needs_Quiz';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'render_settings' ) ) {
			echo '<p>' . esc_html__( 'این کوییز در این فروشگاه فعال نیست.', 'lylyrose-core' ) . '</p>';
			return;
		}
		call_user_func( array( $class, 'render_settings' ), $tab );
	}
}
