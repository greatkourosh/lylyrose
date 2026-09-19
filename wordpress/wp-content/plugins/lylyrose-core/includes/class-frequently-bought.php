<?php
/**
 * Frequently bought together (اکثراً با هم خریداری شده‌اند) — P2 #12.
 *
 * Co-purchase analysis over completed orders: for the current product, count
 * how often each other product line-item appeared in the same order, keep the
 * top scorers, and render them Digikala-style on the single product page.
 *
 * Storage: transient `asc_fbt_<product_id>` (1 day) — recomputed lazily;
 * invalidated for the products of an order when the order completes.
 */
class ASC_Frequently_Bought {

	const MIN_SCORE = 1;
	const MAX_ITEMS = 8;
	const CACHE_TTL = DAY_IN_SECONDS;

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ) );
	}

	/**
	 * Recompute product IDs frequently bought with $product_id.
	 *
	 * @return int[] Product IDs ordered by co-purchase score desc, capped.
	 */
	public static function get_partners( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return array();
		}

		$transient = 'asc_fbt_' . $product_id;
		$cached    = get_transient( $transient );
		if ( false !== $cached ) {
			return is_array( $cached ) ? array_map( 'absint', $cached ) : array();
		}

		$partners = self::query_partners( $product_id );
		set_transient( $transient, $partners, self::CACHE_TTL );
		return $partners;
	}

	/**
	 * Raw co-purchase query: completed orders containing $product_id, minus
	 * itself, top MAX_ITEMS by frequency.
	 */
	private static function query_partners( $product_id ) {
		$order_ids = wc_get_orders(
			array(
				'status'  => array( 'wc-completed' ),
				'limit'   => 500,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);

		if ( ! $order_ids ) {
			return array();
		}

		$scores = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$contains_target = false;
			$others          = array();

			foreach ( $order->get_items() as $item ) {
				$pid = $item->get_product_id();
				if ( ! $pid ) {
					continue;
				}
				if ( $pid === $product_id ) {
					$contains_target = true;
				} else {
					$others[ $pid ] = true;
				}
			}

			if ( ! $contains_target ) {
				continue;
			}

			foreach ( array_keys( $others ) as $pid ) {
				$scores[ $pid ] = ( $scores[ $pid ] ?? 0 ) + 1;
			}
		}

		if ( ! $scores ) {
			return array();
		}

		arsort( $scores );
		$scores = array_filter( $scores, function ( $s ) {
			return $s >= self::MIN_SCORE;
		} );

		return array_slice( array_keys( $scores ), 0, self::MAX_ITEMS );
	}

	/**
	 * On order completion: drop cached partner lists for that order's products.
	 */
	public static function on_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( $pid ) {
				delete_transient( 'asc_fbt_' . absint( $pid ) );
			}
		}
	}
}
