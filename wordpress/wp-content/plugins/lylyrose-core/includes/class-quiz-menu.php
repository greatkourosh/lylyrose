<?php
/**
 * Quiz menu (منوی کوییز).
 *
 * Editable header and drawer links to the finder pages, under Settings. A row
 * whose local page does not exist on this install is skipped, so the defaults
 * can list every finder without leaving a dead link behind.
 */

defined( 'ABSPATH' ) || exit;

class ASC_Quiz_Menu {

	const OPT  = 'asc_quiz_menu';
	const MAX  = 10;
	const PAGE = 'asc-quiz-menu';

	public static function defaults() {
		return array(
			array( 'label' => 'عطرت رو پیدا کن', 'url' => '/perfume-finder/', 'enabled' => true, 'order' => 1 ),
			array( 'label' => 'پوستت چه نیازی داره', 'url' => '/skin-needs/', 'enabled' => true, 'order' => 2 ),
			array( 'label' => 'موهات چه نیازی داره', 'url' => '/hair-needs/', 'enabled' => true, 'order' => 3 ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Enabled links that resolve on this install, in display order.
	 *
	 * @return array<int, array{label: string, url: string, order: int}>
	 */
	public static function items() {
		$out = array();
		foreach ( get_option( self::OPT, self::defaults() ) as $row ) {
			if ( empty( $row['enabled'] ) ) {
				continue;
			}
			$url = $row['url'];
			if ( ! preg_match( '#^https?://#', $url ) ) {
				$path = trim( $url, '/' );
				if ( '' === $path || ! get_page_by_path( $path ) ) {
					continue;
				}
				$url = home_url( '/' . $path . '/' );
			}
			$out[] = array( 'label' => $row['label'], 'url' => $url, 'order' => (int) $row['order'] );
		}
		usort( $out, function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		} );
		return $out;
	}

	public static function sanitize( $in ) {
		$out = array();
		foreach ( array_slice( (array) ( $in['items'] ?? array() ), 0, self::MAX ) as $row ) {
			$label = sanitize_text_field( $row['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$out[] = array(
				'label'   => $label,
				'url'     => esc_url_raw( trim( $row['url'] ?? '' ) ),
				'enabled' => ! empty( $row['enabled'] ),
				'order'   => (int) ( $row['order'] ?? 0 ),
			);
		}
		return $out;
	}

	public static function admin_menu() {
		add_options_page(
			__( 'منوی کوییز', 'lylyrose-core' ),
			__( 'منوی کوییز', 'lylyrose-core' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function admin_init() {
		register_setting(
			self::OPT,
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function render_admin() {
		$rows = array_slice( get_option( self::OPT, self::defaults() ), 0, self::MAX );
		while ( count( $rows ) < self::MAX ) {
			$rows[] = array( 'label' => '', 'url' => '', 'enabled' => false, 'order' => count( $rows ) + 1 );
		}
		$name = esc_attr( self::OPT );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'منوی کوییز', 'lylyrose-core' ); ?></h1>
			<p><?php esc_html_e( 'برای حذف یک ردیف، برچسبش را خالی کنید. آدرس محلی باید به یک صفحه موجود اشاره کند؛ در غیر این صورت لینک نمایش داده نمیشود.', 'lylyrose-core' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPT ); ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'برچسب', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'آدرس', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'ترتیب', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'فعال', 'lylyrose-core' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $i => $row ) : ?>
						<tr>
							<td><input type="text" class="regular-text" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>"></td>
							<td><input type="text" class="regular-text" dir="ltr" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>"></td>
							<td><input type="number" class="small-text" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][order]" value="<?php echo (int) $row['order']; ?>"></td>
							<td><input type="checkbox" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
