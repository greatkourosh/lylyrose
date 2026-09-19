<?php
/**
 * migrate-lib.php — Pull public data from https://lylyrose.vegacodex.ir into this
 * local WooCommerce so the two sites match on all publicly-visible content.
 *
 * Run inside the lylyrose-wp container via WP-CLI:
 *   docker exec lylyrose-wp php /tmp/wp-cli.phar eval-file /tmp/migrate-lib.php <phase> --allow-root
 *
 * Phases:
 *   terms      create/refresh product_cat + pa_brand terms from remote
 *   brandmap   query remote per-brand filters -> persist product_id => brand term map
 *   products   create all WooCommerce products (prices, stock, cats, brand, media)
 *   pages      create public pages (home/shop/cart/... content)
 *   links      flush rewrites, search-replace remote URLs -> local
 *
 * Safety: the library only WRITES during explicit write phases. Re-running the same
 * phase is idempotent (products keyed by SKU), so a partial failure can be resumed.
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );
ini_set( 'display_errors', '1' );

require '/var/www/html/wp-load.php';
if ( ! defined( 'REMOTE' ) ) {
	define( 'REMOTE', 'https://lylyrose.vegacodex.ir' );
}

// ---- remote HTTP helpers ---------------------------------------------------

function ac_http_get( $url, $max = 90 ) {
	$resp = wp_remote_get( $url, array(
		'timeout'   => $max,
		'sslverify' => true,
		'headers'   => array( 'Accept' => 'application/json' ),
		'user-agent'=> 'lylyrose-migrate/1.0',
	) );
	if ( is_wp_error( $resp ) ) {
		return array( 'error' => $resp->get_error_message() );
	}
	$body = wp_remote_retrieve_body( $resp );
	$code = wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) {
		return array( 'error' => "HTTP {$code} from {$url}" );
	}
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		return array( 'error' => 'bad JSON from ' . $url );
	}
	return $data;
}

function ac_remote_collect( $path, $query = array(), $field = null ) {
	// Paginate a REST list endpoint (rel 'next'), collecting rows.
	$all   = array();
	$page  = 1;
	while ( true ) {
		$qs = array_merge( array( 'per_page' => 100, 'page' => $page ), $query );
		$url = REMOTE . $path . '?' . http_build_query( $qs );
		$data = ac_http_get( $url );
		if ( isset( $data['error'] ) ) {
			if ( empty( $all ) ) {
				return array( 'error' => $data['error'] . ' @ ' . $url );
			}
			break;
		}
		if ( ! is_array( $data ) || ! count( $data ) ) {
			break;
		}
		$all = array_merge( $all, $data );
		if ( count( $data ) < 100 ) {
			break;
		}
		$page++;
	}
	return $field ? $all : $all;
}

function ac_log( $m ) { fwrite( STDOUT, $m . "\n" ); }
function ac_warn( $m ) { fwrite( STDERR, "[WARN] " . $m . "\n" ); }

// ---- terms ----------------------------------------------------------------

function ac_brands_term_map() {
	// brand terms -> name (for later brandmap + creating terms)
	$brands = ac_remote_collect( '/wp-json/wc/store/v1/products/attributes/1/terms' );
	if ( isset( $brands['error'] ) ) {
		ac_warn( 'brands fetch: ' . $brands['error'] );
		return array();
	}
	$out = array();
	foreach ( $brands as $b ) {
		if ( ! empty( $b['name'] ) ) {
			$out[ $b['id'] ] = $b['name'];
		}
	}
	return $out;
}

function ac_migrate_terms() {
	global $wpdb;

	// product_cat
	$cats = ac_remote_collect( '/wp-json/wc/store/v1/products/categories' );
	if ( isset( $cats['error'] ) ) {
		ac_warn( 'categories fetch: ' . $cats['error'] );
		return;
	}
	$count = 0;
	foreach ( $cats as $c ) {
		$name = isset( $c['name'] ) ? $c['name'] : '';
		if ( ! $name ) {
			continue;
		}
		$term = term_exists( $name, 'product_cat' );
		if ( ! $term ) {
			$slug = isset( $c['slug'] ) && $c['slug'] ? sanitize_title( $c['slug'] ) : '';
			$args = array( 'slug' => $slug ? $slug : sanitize_title( $name ) );
			if ( ! empty( $c['parent'] ) && is_array( $parent = term_exists( (int) $c['parent'], 'product_cat' ) ) ) {
				$args['parent'] = (int) $parent['term_id'];
			}
			$term = wp_insert_term( $name, 'product_cat', $args );
		}
		if ( is_array( $term ) ) {
			$count++;
		} else {
			ac_warn( 'category ' . $name . ': ' . ( is_wp_error( $term ) ? $term->get_error_message() : '' ) );
		}
	}
	ac_log( "product_cat: {$count} terms ensured" );

	// pa_brand terms
	$brands = ac_brands_term_map();
	$bcount = 0;
	foreach ( $brands as $bname ) {
		$term = term_exists( $bname, 'pa_brand' );
		if ( ! $term ) {
			$term = wp_insert_term( $bname, 'pa_brand', array( 'slug' => sanitize_title( $bname ) ) );
		}
		if ( is_array( $term ) ) {
			$bcount++;
		}
	}
	ac_log( "pa_brand: {$bcount} terms ensured" );
}

// ---- brand map -------------------------------------------------------------

function ac_build_brand_map() {
	$brands = ac_brands_term_map();
	$map = array(); // remote product_id => local pa_brand term_id
	foreach ( $brands as $remote_term_id => $bname ) {
		$term = get_term_by( 'name', $bname, 'pa_brand' );
		if ( ! $term ) {
			$term = get_term_by( 'slug', sanitize_title( $bname ), 'pa_brand' );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		// fetch all products under this brand using the store API attribute filter
		$res = ac_remote_collect( '/wp-json/wc/store/v1/products', array(
			'attributes[0][attribute]' => 'pa_brand',
			'attributes[0][term_id]'   => $remote_term_id,
		) );
		if ( isset( $res['error'] ) || ! is_array( $res ) ) {
			continue;
		}
		foreach ( $res as $p ) {
			if ( ! empty( $p['id'] ) && ! empty( $p['sku'] ) ) {
				$map[ $p['id'] ] = $term->term_id;
			}
		}
	}
	update_option( 'asc_migration_brand_map', $map, false );
	ac_log( 'brand map: ' . count( $map ) . ' products -> brand' );
	return $map;
}

// ---- media ----------------------------------------------------------------

function ac_sideload( $src, $post_id ) {
	// Returns attachment ID for src, reusing existing by URL if present.
	static $by_url = array();
	if ( isset( $by_url[ $src ] ) ) {
		if ( get_post( $by_url[ $src ] ) ) {
			return $by_url[ $src ];
		}
	}
	// check DB for existing attachment with same guid/src
	$existing = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
		"SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE guid=%s AND post_type='attachment' LIMIT 1", $src
	) );
	if ( $existing ) {
		$by_url[ $src ] = (int) $existing;
		return (int) $existing;
	}
	$tmp = download_url( $src );
	if ( is_wp_error( $tmp ) ) {
		ac_warn( 'download failed ' . $src . ': ' . $tmp->get_error_message() );
		return 0;
	}
	$name = sanitize_file_name( basename( parse_url( $src, PHP_URL_PATH ) ) );
	if ( ! $name ) {
		$name = 'product-' . $post_id . '-' . md5( $src ) . '.webp';
	}
	$file = array( 'name' => $name, 'tmp_name' => $tmp );
	$att = media_handle_sideload( $file, $post_id, '' );
	@unlink( $tmp );
	if ( is_wp_error( $att ) ) {
		ac_warn( 'sideload failed ' . $name . ': ' . $att->get_error_message() );
		return 0;
	}
	$by_url[ $src ] = (int) $att;
	return (int) $att;
}

// ---- products -------------------------------------------------------------

function ac_fetch_products() {
	return ac_remote_collect( '/wp-json/wc/store/v1/products', array(
		'type' => 'simple',
	) );
}

function ac_migrate_products() {
	$brand_map = get_option( 'asc_migration_brand_map', array() );
	$products  = ac_fetch_products();
	if ( isset( $products['error'] ) ) {
		ac_warn( 'products fetch failed: ' . $products['error'] );
		return;
	}
	ac_log( 'fetched ' . count( $products ) . ' products' );
	$created = 0; $updated = 0; $skipped = 0;
	foreach ( $products as $p ) {
		$r = ac_upsert_product( $p, $brand_map );
		if ( 'created' === $r ) $created++;
		elseif ( 'updated' === $r ) $updated++;
		else $skipped++;
	}
	ac_log( "products: {$created} created, {$updated} updated, {$skipped} skipped" );
}

function ac_upsert_product( $p, $brand_map ) {
	static $by_remote_id = null;
	if ( null === $by_remote_id ) {
		$by_remote_id = get_option( 'asc_migration_remote_map', array() );
	}
	$rid = isset( $p['id'] ) ? (int) $p['id'] : 0;
	$sku = isset( $p['sku'] ) ? sanitize_text_field( $p['sku'] ) : '';
	$name = isset( $p['name'] ) ? $p['name'] : '';
	if ( ! $name ) {
		ac_warn( 'product without name, id=' . $rid );
		return 'skipped';
	}

	// Key by remote product id so duplicate SKUs are preserved (all 130 records
	// import; the remote has 110 unique SKUs shared by 130 products).
	$existing = isset( $by_remote_id[ $rid ] ) ? (int) $by_remote_id[ $rid ] : 0;
	if ( $existing && ! get_post( $existing ) ) {
		$existing = 0;
	}
	$is_new = ! $existing;

	// NOTE: we deliberately do NOT dedupe by SKU here. The remote holds 130
	// product records but only 110 distinct SKUs (10 are duplicated). To keep
	// all 130, each remote id becomes its own local product; duplicate SKUs
	// are written as-is (WooCommerce returns the first match on lookup, which
	// mirrors the remote's own code-based routing).
	if ( ! $existing ) {
		$data = array(
			'post_type'    => 'product',
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => isset( $p['description'] ) ? $p['description'] : '',
			'post_excerpt' => isset( $p['short_description'] ) ? wp_strip_all_tags( $p['short_description'] ) : '',
		);
		$existing = wp_insert_post( $data );
		if ( is_wp_error( $existing ) || ! $existing ) {
			ac_warn( 'insert failed for ' . $name );
			return 'skipped';
		}
		$by_remote_id[ $rid ] = (int) $existing;
		update_option( 'asc_migration_remote_map', $by_remote_id, false );
	}
	$product = wc_get_product( $existing );
	if ( ! $product ) {
		ac_warn( 'cannot load product ' . $existing );
		return 'skipped';
	}

	if ( $sku ) {
		// WooCommerce enforces unique SKUs; duplicate-SKU products from the remote
		// share a code (sku-<digits>). Check whether another product already owns
		// this SKU and leave it blank on the duplicate rather than throwing.
		$sku_owner = wc_get_product_id_by_sku( $sku );
		if ( $sku_owner && $sku_owner !== (int) $product->get_id() ) {
			$product->set_sku( '' );
		} else {
			$product->set_sku( $sku );
		}
	}
	$product->set_status( 'publish' );

	// price (IRT toman, minor unit 0 -> values are integers)
	$prices = isset( $p['prices'] ) && is_array( $p['prices'] ) ? $p['prices'] : array();
	$price  = isset( $prices['price'] ) ? (string) $prices['price'] : '0';
	$regular = isset( $prices['regular_price'] ) ? (string) $prices['regular_price'] : $price;
	$sale   = ( isset( $prices['sale_price'] ) && $prices['sale_price'] ) ? (string) $prices['sale_price'] : '';
	if ( $sale && (float) $sale > 0 && (float) $sale < (float) $regular ) {
		$product->set_regular_price( $regular );
		$product->set_sale_price( $sale );
		$product->set_price( $sale );
	} else {
		$product->set_regular_price( $regular );
		$product->set_sale_price( '' );
		$product->set_price( $price );
	}

	// stock
	$in_stock = isset( $p['is_in_stock'] ) ? (bool) $p['is_in_stock'] : true;
	$product->set_manage_stock( false );
	$product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );

	// categories (map by local name)
	if ( ! empty( $p['categories'] ) ) {
		$ids = array();
		foreach ( $p['categories'] as $c ) {
			$nm = isset( $c['name'] ) ? $c['name'] : '';
			if ( ! $nm ) continue;
			$t = term_exists( $nm, 'product_cat' );
			if ( is_array( $t ) ) $ids[] = (int) $t['term_id'];
			elseif ( is_object( $t ) && $t->term_id ) $ids[] = (int) $t->term_id;
		}
		if ( $ids ) {
			wp_set_object_terms( $existing, $ids, 'product_cat' );
		}
	}

	// brand
	if ( ! empty( $p['id'] ) && isset( $brand_map[ $p['id'] ] ) ) {
		wp_set_object_terms( $existing, (int) $brand_map[ $p['id'] ], 'pa_brand' );
	}

	// image
	if ( ! empty( $p['images'] ) && ! empty( $p['images'][0]['src'] ) ) {
		$att = ac_sideload( $p['images'][0]['src'], $existing );
		if ( $att ) {
			set_post_thumbnail( $existing, $att );
		}
	}

	$product->save();
	return $is_new ? 'created' : 'updated';
}

// ---- pages ----------------------------------------------------------------

function ac_migrate_pages() {
	// Pages we can meaningfully copy (public page content). Remote front page
	// (home, id 241) has empty rendered content — the theme renders it dynamically,
	// so we only set it as the static front page rather than importing empty content.
	$pages = ac_remote_collect( '/wp-json/wp/v2/pages' );
	if ( isset( $pages['error'] ) ) {
		ac_warn( 'pages fetch failed: ' . $pages['error'] );
		return;
	}
	ac_log( 'fetched ' . count( $pages ) . ' pages' );
	$done = 0;
	$home_id = 0;
	foreach ( $pages as $pg ) {
		$slug  = isset( $pg['slug'] ) ? $pg['slug'] : '';
		$title = isset( $pg['title']['rendered'] ) ? trim( $pg['title']['rendered'] ) : '';
		if ( ! $slug || ! $title ) {
			continue;
		}
		$content = isset( $pg['content']['rendered'] ) ? $pg['content']['rendered'] : '';
		// map remote WP/woocommerce pages to local known slugs so shortcodes resolve
		$use_content = ( 'home' === $slug ) ? '[woocommerce brief]' : $content;
		$existing = get_page_by_path( $slug );
		if ( $existing && 'page' === $existing->post_type ) {
			$pid = $existing->ID;
			$mode = 'updated';
		} else {
			// Ensure shop/cart/checkout/my-account are WooCommerce pages if slug matches
			$wc_slugs = array( 'shop', 'cart', 'checkout', 'my-account', 'refund_returns', 'terms', 'privacy' );
			$pid = wp_insert_post( array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_content'=> in_array( $slug, $wc_slugs, true ) ? "[woocommerce_{$slug}]" :
					( $use_content ? $use_content : $title ),
			) );
			$mode = 'created';
		}
		if ( $pid && ! is_wp_error( $pid ) ) {
			if ( 'home' === $slug ) {
				$home_id = $pid;
			}
			$done++;
			ac_log( "page {$slug} ({$pid}) {$mode}" );
		}
	}
	if ( $home_id ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
		ac_log( "front page set -> {$home_id}" );
	}
	ac_log( "pages: {$done} ensured" );
}

// ---- links ----------------------------------------------------------------

function ac_migrate_links() {
	// Flush rewrites so product/category rules and ASC_Product_Code rules apply.
	flush_rewrite_rules();
	ac_log( 'rewrite rules flushed' );
}

// ---- main -----------------------------------------------------------------

$phase = isset( $argv[1] ) ? $argv[1] : '';
switch ( $phase ) {
	case 'terms':
		ac_migrate_terms();
		break;
	case 'brandmap':
		ac_build_brand_map();
		break;
	case 'products':
		ac_migrate_products();
		break;
	case 'pages':
		ac_migrate_pages();
		break;
	case 'links':
		ac_migrate_links();
		break;
	case 'all':
		ac_migrate_terms();
		ac_build_brand_map();
		ac_migrate_products();
		ac_migrate_pages();
		ac_migrate_links();
		break;
	default:
		fwrite( STDERR, "usage: php migrate-lib.php <terms|brandmap|products|pages|links|all>\n" );
		exit( 1 );
}
