<?php
/**
 * Persian sales reports + Excel-friendly CSV export (P2 #15).
 *
 * Admin page (menu «گزارش فروش») with a from/to date range over completed +
 * processing orders: order count, revenue, items sold, average order value,
 * top products — plus a CSV download (UTF-8 BOM so Excel renders Persian).
 * All queries go through wc_get_orders (HPOS-safe).
 */
class ASC_Reports {

	const PAGE_SLUG = 'asc-reports';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_csv' ) );
	}

	public static function admin_menu() {
		add_menu_page( __( 'گزارش فروش لیلی رز', 'lylyrose-core' ), __( 'گزارش فروش', 'lylyrose-core' ), 'manage_options', self::PAGE_SLUG, array( __CLASS__, 'render_page' ), 'dashicons-chart-bar', 56 );
	}

	/**
	 * Parse the range GET params; defaults to the current month.
	 *
	 * @return array{from: string, to: string} Y-m-d strings.
	 */
	public static function get_range() {
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$from = ( $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) ? $from : gmdate( 'Y-m-01' );
		$to   = ( $to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) ? $to : gmdate( 'Y-m-d' );
		return array( 'from' => $from, 'to' => $to );
	}

	/**
	 * Aggregate the paid orders in the range.
	 *
	 * @return array{orders:int,revenue:float,items:int,products:array<int,int>} products = pid => qty.
	 */
	public static function get_stats( $from, $to ) {
		$orders = wc_get_orders(
			array(
				'status'      => array( 'wc-completed', 'wc-processing' ),
				'limit'       => -1,
				'date_created' => $from . '...' . $to . ' 23:59:59',
				'return'      => 'objects',
			)
		);

		$stats = array( 'orders' => 0, 'revenue' => 0.0, 'items' => 0, 'products' => array() );
		foreach ( $orders as $order ) {
			$stats['orders']++;
			$stats['revenue'] += (float) $order->get_total();
			foreach ( $order->get_items() as $item ) {
				$qty = (int) $item->get_quantity();
				$pid = $item->get_product_id();
				$stats['items'] += $qty;
				if ( $pid ) {
					$stats['products'][ $pid ] = ( $stats['products'][ $pid ] ?? 0 ) + $qty;
				}
			}
		}
		arsort( $stats['products'] );
		return $stats;
	}

	/**
	 * Signed export token: HMAC over user + range + day, stable across
	 * requests even where sessions rotate per request (some security
	 * plugins regenerate the WP session token on every admin page load,
	 * which breaks nonce-based links).
	 */
	public static function export_token( $user_id, $from, $to ) {
		return hash_hmac( 'sha256', $user_id . '|' . $from . '|' . $to . '|' . gmdate( 'Y-m-d' ), wp_salt( 'auth' ) );
	}

	/**
	 * Stream the CSV (orders in range) before any output; exits.
	 */
	public static function maybe_export_csv() {
		if ( ! isset( $_GET['page'], $_GET['asc_export'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'دسترسی ندارید', 'lylyrose-core' ), __( 'خطا', 'lylyrose-core' ), array( 'response' => 403 ) );
		}
		$range = self::get_range();
		if ( ! hash_equals( self::export_token( get_current_user_id(), $range['from'], $range['to'] ), (string) $_GET['asc_export'] ) ) {
			wp_die( __( 'دسترسی ندارید', 'lylyrose-core' ), __( 'خطا', 'lylyrose-core' ), array( 'response' => 403 ) );
		}
		$orders = wc_get_orders(
			array(
				'status'      => array( 'wc-completed', 'wc-processing' ),
				'limit'       => -1,
				'date_created' => $range['from'] . '...' . $range['to'] . ' 23:59:59',
				'return'      => 'objects',
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=lylyrose-sales-' . $range['from'] . '_' . $range['to'] . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel renders Persian.
		fputcsv( $out, array( __( 'شماره سفارش', 'lylyrose-core' ), __( 'تاریخ', 'lylyrose-core' ), __( 'وضعیت', 'lylyrose-core' ), __( 'مشتری', 'lylyrose-core' ), __( 'موبایل', 'lylyrose-core' ), __( 'اقلام', 'lylyrose-core' ), __( 'مبلغ کل (تومان)', 'lylyrose-core' ) ) );
		foreach ( $orders as $order ) {
			$items = 0;
			foreach ( $order->get_items() as $item ) {
				$items += (int) $item->get_quantity();
			}
			fputcsv(
				$out,
				array(
					$order->get_order_number(),
					$order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
					wc_get_order_status_name( $order->get_status() ),
					$order->get_formatted_billing_full_name(),
					$order->get_billing_phone(),
					$items,
					number_format( (float) $order->get_total(), 0, '.', '' ),
				)
			);
		}
		exit;
	}

	/**
	 * Dashboard page: range form, summary cards, top products table.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$range = self::get_range();
		$stats = self::get_stats( $range['from'], $range['to'] );
		$avg   = $stats['orders'] ? $stats['revenue'] / $stats['orders'] : 0;
		$export_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&asc_export=' . self::export_token( get_current_user_id(), $range['from'], $range['to'] ) . '&from=' . $range['from'] . '&to=' . $range['to'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'گزارش فروش', 'lylyrose-core' ); ?></h1>
			<form method="get" style="margin:16px 0">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<label><?php esc_html_e( 'از', 'lylyrose-core' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $range['from'] ); ?>"></label>
				<label><?php esc_html_e( 'تا', 'lylyrose-core' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $range['to'] ); ?>"></label>
				<?php submit_button( __( 'اعمال', 'lylyrose-core' ), 'primary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'خروجی اکسل (CSV)', 'lylyrose-core' ); ?></a>
			</form>

			<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:160px">
					<div style="color:#646970;font-size:12px"><?php esc_html_e( 'تعداد سفارش', 'lylyrose-core' ); ?></div>
					<div style="font-size:24px;font-weight:700"><?php echo esc_html( number_format_i18n( $stats['orders'] ) ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:160px">
					<div style="color:#646970;font-size:12px"><?php esc_html_e( 'درآمد (تومان)', 'lylyrose-core' ); ?></div>
					<div style="font-size:24px;font-weight:700"><?php echo esc_html( number_format_i18n( (int) $stats['revenue'] ) ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:160px">
					<div style="color:#646970;font-size:12px"><?php esc_html_e( 'اقلام فروخته‌شده', 'lylyrose-core' ); ?></div>
					<div style="font-size:24px;font-weight:700"><?php echo esc_html( number_format_i18n( $stats['items'] ) ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:160px">
					<div style="color:#646970;font-size:12px"><?php esc_html_e( 'میانگین سفارش (تومان)', 'lylyrose-core' ); ?></div>
					<div style="font-size:24px;font-weight:700"><?php echo esc_html( number_format_i18n( (int) $avg ) ); ?></div>
				</div>
			</div>

			<h2><?php esc_html_e( 'پرفروش‌ترین کالاها', 'lylyrose-core' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr><th><?php esc_html_e( 'کالا', 'lylyrose-core' ); ?></th><th><?php esc_html_e( 'تعداد', 'lylyrose-core' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $stats['products'] ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'در این بازه فروشی ثبت نشده است.', 'lylyrose-core' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( array_slice( $stats['products'], 0, 10, true ) as $pid => $qty ) : ?>
						<tr>
							<td><?php
								$p = wc_get_product( $pid );
								echo $p ? '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( $p->get_name() ) . '</a>' : '#' . (int) $pid;
							?></td>
							<td><?php echo esc_html( number_format_i18n( $qty ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
