<?php
/**
 * Typo-tolerant product search. Runs only when the exact search returns nothing,
 * so a query that already matches is never rewritten.
 *
 * @package Lylyrose_Core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Fuzzy_Search {

	public static function init() {
		add_filter( 'the_posts', array( __CLASS__, 'rescue' ), 10, 2 );
	}

	public static function rescue( $posts, $q ) {
		if ( $posts || is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
			return $posts;
		}
		$query = self::tokens( (string) $q->get( 's' ) );
		if ( ! $query ) {
			return $posts;
		}

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
		$ids  = array();
		foreach ( $rows as $row ) {
			if ( self::matches( $query, self::tokens( $row->post_title ) ) ) {
				$ids[] = (int) $row->ID;
			}
		}
		if ( ! $ids ) {
			return $posts;
		}

		$found = get_posts( array(
			'post_type'      => 'product',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
		) );
		$q->found_posts   = count( $found );
		$q->max_num_pages = 1;
		return $found;
	}

	private static function matches( $query, $words ) {
		foreach ( $query as $want ) {
			$ok = false;
			foreach ( $words as $have ) {
				if ( self::distance( $want, $have ) <= self::allowed( $want ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return false;
			}
		}
		return true;
	}

	/** Persian letter variants (ي ك ى) fold to the forms the catalogue uses. */
	private static function tokens( $text ) {
		$text = strtr( mb_strtolower( $text, 'UTF-8' ), array( 'ي' => 'ی', 'ك' => 'ک', 'ى' => 'ی', "\u{200C}" => '' ) );
		return array_values( array_filter( preg_split( '/[^\p{L}\p{N}]+/u', $text ) ) );
	}

	private static function allowed( $word ) {
		$len = mb_strlen( $word, 'UTF-8' );
		return $len <= 3 ? 0 : ( $len <= 6 ? 1 : 2 );
	}

	/** Levenshtein over characters, not bytes, so Persian letters count as one edit. */
	private static function distance( $a, $b ) {
		$a    = preg_split( '//u', $a, -1, PREG_SPLIT_NO_EMPTY );
		$b    = preg_split( '//u', $b, -1, PREG_SPLIT_NO_EMPTY );
		$prev = range( 0, count( $b ) );
		foreach ( $a as $i => $ca ) {
			$cur = array( $i + 1 );
			foreach ( $b as $j => $cb ) {
				$cur[] = min( $prev[ $j + 1 ] + 1, $cur[ $j ] + 1, $prev[ $j ] + ( $ca === $cb ? 0 : 1 ) );
			}
			$prev = $cur;
		}
		return end( $prev );
	}
}
