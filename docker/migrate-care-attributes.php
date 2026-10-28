<?php
/**
 * Promote the skin/hair needs meta to real WooCommerce attributes.
 *
 * The care products (35 skin-care, 16 hair-care) carry _needs_types and
 * _needs_concerns meta, seeded per-SKU by seed-needs-data.php. Meta cannot be
 * faceted natively, so the /skincare/ and /haircare/ landing pages need real
 * taxonomies: pa_skin_type, pa_skin_concern, pa_hair_type, pa_hair_concern.
 *
 * This copies meta -> terms. It does NOT delete the meta: ASC_Needs_Quiz still
 * reads _needs_types/_needs_concerns (class-needs-quiz.php:462), and the quiz is
 * live. Point the quiz at these taxonomies in a later pass.
 *
 * Term slugs match the meta values verbatim so a later switch needs no mapping.
 *
 * Run inside the WordPress container. Dry-run by default; pass --write to store.
 * Idempotent: a second --write run changes nothing.
 *
 * Usage: php migrate-care-attributes.php [--write] [--all]
 */

require '/var/www/html/wp-load.php';

/* The dry-run flag must be matched in every spelling WordPress tooling uses;
 * a guard that accepts only one spelling writes on a "dry" run. */
$write = false;
foreach ( $argv as $arg ) {
	if ( in_array( $arg, array( '--write', '--write=true', '--write=1', '--write=yes' ), true ) ) {
		$write = true;
	}
}

/* Trees: root slug => the two attributes that tree uses. */
$trees = array(
	'skin-care' => array(
		'types'    => array(
			'attr'    => 'skin_type',
			'label'   => 'نوع پوست',
			'terms'   => array(
				'dry'         => 'خشک',
				'oily'        => 'چرب',
				'combination' => 'مختلط',
				'sensitive'   => 'حساس',
				'normal'      => 'نرمال',
			),
		),
		'concerns' => array(
			'attr'    => 'skin_concern',
			'label'   => 'نیاز پوست',
			'terms'   => array(
				'acne'        => 'جوش و آکنه',
				'dehydration' => 'کم‌آبی',
				'dullness'    => 'کدری و بیرونقی',
				'aging'       => 'چین و چروک',
				'dark_spots'  => 'لک و تیرگی',
				'redness'     => 'قرمزی',
			),
		),
	),
	'hair-care' => array(
		'types'    => array(
			'attr'    => 'hair_type',
			'label'   => 'بافت مو',
			'terms'   => array(
				'straight' => 'صاف',
				'wavy'     => 'موجدار',
				'curly'    => 'فر',
				'coily'    => 'خیلی فر',
			),
		),
		'concerns' => array(
			'attr'    => 'hair_concern',
			'label'   => 'نیاز مو',
			'terms'   => array(
				'frizz'     => 'وز و پریشی',
				'hair_fall' => 'ریزش',
				'damage'    => 'آسیب و شکنندگی',
				'dandruff'  => 'شوره',
				'dryness'   => 'خشکی',
			),
		),
	),
);

/**
 * Create the attribute if absent; never duplicate an existing one.
 *
 * @return int attribute id, or 0 on failure.
 */
function care_ensure_attribute( $name, $label, $write ) {
	$existing = wc_attribute_taxonomy_id_by_name( wc_attribute_taxonomy_name( $name ) );
	if ( $existing ) {
		// Keep the admin label in sync without touching terms or product data.
		if ( $write ) {
			$saved = wc_get_attribute( $existing );
			if ( $saved && $saved->name !== $label ) {
				wc_update_attribute( $existing, array(
					'name'     => $label,
					'slug'     => $name,
					'type'     => 'select',
					'order_by' => 'menu_order',
				) );
				delete_transient( 'wc_attribute_taxonomies' );
			}
		}
		return (int) $existing;
	}
	if ( ! $write ) {
		return -1; // Marker: "would create".
	}
	/* wc_create_attribute() puts the admin label in `name`, not `slug`: `slug`
	 * must be the ascii machine name and `name` the human label. Passing the
	 * ascii value for both (as the first version did) yields an attribute
	 * labelled "skin_type" in the admin. */
	$id = wc_create_attribute( array(
		'name'         => $label,
		'slug'         => $name,
		'type'         => 'select',
		'order_by'     => 'menu_order',
		'has_archives' => false,
	) );
	if ( is_wp_error( $id ) ) {
		echo "ERROR creating $name: " . $id->get_error_message() . "\n";
		return 0;
	}
	// wc_create_attribute() writes the row but does not register the taxonomy in
	// this process, so wp_insert_term() would fail "invalid taxonomy". Re-run the
	// registrar, then drop the transient so the cached list picks up the new row.
	delete_transient( 'wc_attribute_taxonomies' );
	if ( class_exists( 'WC_Post_Types' ) && method_exists( 'WC_Post_Types', 'register_taxonomies' ) ) {
		WC_Post_Types::register_taxonomies();
	}
	return (int) $id;
}

$total_changed = 0;
$total_skipped = 0;

foreach ( $trees as $tree_slug => $tree ) {
	echo "\n===== $tree_slug =====\n";

	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( array(
			'taxonomy'         => 'product_cat',
			'field'            => 'slug',
			'terms'            => $tree_slug,
			'include_children' => true,
		) ),
	) );
	echo count( $ids ) . " products\n";

	foreach ( $tree as $meta_branch => $spec ) {
		$attr_id = care_ensure_attribute( $spec['attr'], $spec['label'], $write );
		$tax     = wc_attribute_taxonomy_name( $spec['attr'] );
		$created = ( -1 === $attr_id ) ? 'would-create' : ( $attr_id ? "id=$attr_id" : 'FAILED' );
		echo "  {$spec['attr']} ($tax): $created\n";
		if ( ! $attr_id ) {
			continue;
		}

		// Ensure every declared term exists, keyed by slug.
		$term_ids = array();
		foreach ( $spec['terms'] as $slug => $label ) {
			if ( -1 === $attr_id ) {
				$term_ids[ $slug ] = -1;
				continue;
			}
			$term = get_term_by( 'slug', $slug, $tax );
			if ( $term ) {
				$term_ids[ $slug ] = (int) $term->term_id;
				continue;
			}
			$new = wp_insert_term( $label, $tax, array( 'slug' => $slug ) );
			if ( is_wp_error( $new ) ) {
				echo "    ERROR term $slug: " . $new->get_error_message() . "\n";
				continue;
			}
			$term_ids[ $slug ] = (int) $new['term_id'];
			echo "    created term $slug\n";
		}

		$meta_key = ( 'types' === $meta_branch ) ? '_needs_types' : '_needs_concerns';
		$per_term = array();
		$changed  = 0;

		foreach ( $ids as $id ) {
			$values = array_values( array_filter( array_map( 'strval', (array) get_post_meta( $id, $meta_key, true ) ) ) );
			$have   = array();
			foreach ( $values as $v ) {
				if ( isset( $term_ids[ $v ] ) ) {
					$per_term[ $v ] = ( $per_term[ $v ] ?? 0 ) + 1;
					if ( $term_ids[ $v ] > 0 ) {
						$have[] = $term_ids[ $v ];
					}
				} else {
					echo "    WARN unmapped value '$v' on $id\n";
				}
			}
			if ( ! $values ) {
				$total_skipped++;
				continue;
			}
			// Dry run, or an attribute that does not exist yet: count the change
			// but write nothing.
			if ( ! $write || $attr_id < 0 ) {
				$changed++;
				$total_changed++;
				continue;
			}
			$before = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $before ) ) {
				echo "    ERROR reading terms on $id: " . $before->get_error_message() . "\n";
				continue;
			}
			$before = array_map( 'intval', $before );
			sort( $before );
			$after = $have;
			sort( $after );
			if ( $before === $after ) {
				continue;
			}
			$res = wp_set_object_terms( $id, $have, $tax, false );
			if ( is_wp_error( $res ) ) {
				echo "    ERROR set terms on $id: " . $res->get_error_message() . "\n";
				continue;
			}
			$changed++;
			$total_changed++;
		}

		echo "    per-term: " . json_encode( $per_term, JSON_UNESCAPED_UNICODE ) . "\n";
		echo "    " . ( $write ? "wrote" : "would write" ) . " $changed products\n";
	}
}

echo "\n===== summary =====\n";
echo ( $write ? "WROTE" : "DRY RUN (nothing written)" ) . ": $total_changed product-term changes, $total_skipped products with no meta\n";
if ( ! $write ) {
	echo "Re-run with --write to apply.\n";
}
