<?php
/**
 * SKU-based public product codes ("sku-123456") with Digikala-style URLs.
 *
 * Canonical permalink: /product/sku-<digits>/<slug>/ (like digikala's
 * /product/dkp-20435041/<name>/). Legacy /product/<slug>/ and short
 * /product/sku-<digits>/ both 301 to the canonical URL.
 *
 * @package Lylyrose_Core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Product_Code {

	/**
	 * Per-request cache of code-digits => product ID (or 0 for miss).
	 *
	 * @var array<string,int>
	 */
	private static $resolved = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'parse_request' ), 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'search_redirect' ), 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'permalink' ), 10, 2 );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'admin_column' ) );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'admin_column_value' ), 10, 2 );
	}

	/**
	 * Public code for a product: first digit run of the SKU, else the ID.
	 *
	 * When several products share the same digit run the oldest one keeps the
	 * run-based code and the rest fall back to their ID-based code. An ID can
	 * coincide with another product's digit run (rare), so codes alone are not
	 * always unique — parse_request() then disambiguates by slug, which is
	 * always unique.
	 */
	public static function get_code( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		$sku = (string) $product->get_sku();
		if ( preg_match( '/(\d+)/', $sku, $m ) ) {
			$digits = ltrim( $m[1], '0' );
			if ( '' === $digits ) {
				$digits = $m[1];
			}
			if ( self::resolve( $digits ) === (int) $product->get_id() ) {
				return 'sku-' . $digits;
			}
		}
		return 'sku-' . $product->get_id();
	}

	/**
	 * Resolve a code's digits to a product ID; null when missing.
	 *
	 * Shared digit runs resolve deterministically to the oldest product ID,
	 * mirroring get_code()'s ownership rule. Codes that match no SKU digit
	 * run fall back to a published product with that exact ID.
	 */
	public static function resolve( $digits ) {
		$digits = (string) $digits;
		if ( isset( self::$resolved[ $digits ] ) ) {
			return self::$resolved[ $digits ] ?: null;
		}

		global $wpdb;
		$pid = null;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				 FROM {$wpdb->posts} AS p
				 INNER JOIN {$wpdb->postmeta} AS m ON m.post_id = p.ID AND m.meta_key = '_sku'
				 WHERE p.post_type = 'product'
				   AND p.post_status = 'publish'
				   AND m.meta_value LIKE %s",
				'%' . $wpdb->esc_like( $digits ) . '%'
			)
		);

		$matches = array();
		foreach ( (array) $rows as $row_id ) {
			$product = wc_get_product( (int) $row_id );
			if ( $product && self::extract_digits( $product ) === $digits ) {
				$matches[] = (int) $row_id;
			}
		}
		if ( $matches ) {
			sort( $matches );
			$pid = $matches[0];
		} elseif ( ctype_digit( $digits ) ) {
			$pid = (int) $digits;
			if ( 'product' !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
				$pid = null;
			}
		}

		self::$resolved[ $digits ] = $pid ? $pid : 0;
		return $pid;
	}

	/**
	 * Find a published product by its URL slug; null when none.
	 */
	private static function product_by_slug( $slug ) {
		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'name'           => sanitize_title( $slug ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return $posts ? (int) $posts[0] : null;
	}

	private static function extract_digits( $product ) {
		$sku = (string) $product->get_sku();
		if ( preg_match( '/(\d+)/', $sku, $m ) ) {
			$digits = ltrim( $m[1], '0' );
			return '' === $digits ? $m[1] : $digits;
		}
		return (string) $product->get_id();
	}

	public static function add_rewrite() {
		add_rewrite_tag( '%product_code%', '([0-9]+)' );
		// Canonical: /product/sku-123456/<slug>/
		add_rewrite_rule(
			'^product/sku-([0-9]+)/([^/]+)/?$',
			'index.php?product_code=$matches[1]&product=$matches[2]',
			'top'
		);
		// Short: /product/sku-123456/ -> 301 to canonical.
		add_rewrite_rule(
			'^product/sku-([0-9]+)/?$',
			'index.php?product_code=$matches[1]',
			'top'
		);
	}

	/**
	 * One-time flush when the plugin version changes (self-heals after DB resets).
	 */
	public static function maybe_flush() {
		$done = get_option( 'asc_rewrite_version', '' );
		if ( LYLYROSE_CORE_VERSION !== $done ) {
			self::add_rewrite();
			flush_rewrite_rules();
			update_option( 'asc_rewrite_version', LYLYROSE_CORE_VERSION );
		}
	}

	/**
	 * Canonical permalink: /product/sku-<digits>/<slug>/
	 */
	public static function permalink( $url, $post ) {
		if ( ! $post || 'product' !== $post->post_type || is_admin() ) {
			return $url;
		}
		$product = wc_get_product( $post );
		if ( ! $product ) {
			return $url;
		}
		// post_name is stored percent-encoded by WP (utf8_uri_encode) — use as-is.
		$base = trailingslashit( home_url( '/product/' . self::get_code( $product ) ) );
		return user_trailingslashit( $base . $post->post_name );
	}

	/**
	 * Route /product/sku-N/ requests:
	 * - code + slug  -> single-product query (or 301 to canonical slug if code wrong)
	 * - code only    -> 301 to canonical /product/sku-N/<slug>/
	 */
	public static function parse_request( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}

		// Legacy /product/<slug>/ -> 301 to canonical /product/sku-N/<slug>/.
		if ( ! get_query_var( 'product_code' ) ) {
			$slug = (string) $q->get( 'product' );
			if ( '' === $slug || 'product' !== $q->get( 'post_type' ) || $q->is_feed() ) {
				return;
			}
			$posts = get_posts(
				array(
					'post_type'      => 'product',
					'name'           => sanitize_title( $slug ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( $posts ) {
				wp_safe_redirect( self::permalink( '', get_post( $posts[0] ) ), 301 );
				exit;
			}
			return;
		}
		$pid = self::resolve( get_query_var( 'product_code' ) );

		// Codes can collide (an ID fallback that equals another product's digit
		// run, or the same digit run on several products). The slug in the URL
		// is unique, so when it points at a different product than the default
		// owner — and that product is itself reachable at this path — serve it.
		$slug_in = (string) get_query_var( 'product' );
		if ( '' !== $slug_in ) {
			$slug_pid = self::product_by_slug( $slug_in );
			if ( $slug_pid && $slug_pid !== $pid && self::get_code( wc_get_product( $slug_pid ) ) === 'sku-' . get_query_var( 'product_code' ) ) {
				$pid = $slug_pid;
			}
		}

		if ( ! $pid ) {
			$q->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$canonical = self::permalink( '', get_post( $pid ) );

		if ( '' === $slug_in ) {
			// Short alias: redirect to the full canonical URL.
			wp_safe_redirect( $canonical, 301 );
			exit;
		}

		if ( sanitize_title( $slug_in ) !== sanitize_title( get_post( $pid )->post_name ) ) {
			// Right code, wrong/missing slug -> correct it (Digikala does the same).
			wp_safe_redirect( $canonical, 301 );
			exit;
		}

		$q->set( 'p', $pid );
		$q->set( 'post_type', 'product' );

		// On static-front-page sites WP_Query decides is_home/is_page in
		// parse_query (before pre_get_posts), so correct the flags here or
		// the homepage renders instead of the product.
		if ( $q->is_home || $q->is_page || $q->is_front_page() ) {
			$q->is_home      = false;
			$q->is_page      = false;
			$q->is_singular  = true;
			$q->is_single    = true;
		}
	}

	/**
	 * Site search for a product code ("sku-123" or bare digits) redirects to
	 * the product's canonical URL instead of a generic search results page.
	 */
	public static function search_redirect( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public search.
		$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( ! preg_match( '/^(?:sku[-_ ]?)?([0-9]{1,12})$/u', trim( $s ), $m ) ) {
			return;
		}
		$pid = self::resolve( $m[1] );
		if ( ! $pid ) {
			return;
		}
		wp_safe_redirect( self::permalink( '', get_post( $pid ) ), 301 );
		exit;
	}

	public static function admin_column( $columns ) {
		$columns['dk_code'] = __( 'کد کالا', 'lylyrose-core' );
		return $columns;
	}

	public static function admin_column_value( $column, $post_id ) {
		if ( 'dk_code' !== $column ) {
			return;
		}
		$product = wc_get_product( $post_id );
		echo esc_html( $product ? self::get_code( $product ) : '—' );
	}
}
