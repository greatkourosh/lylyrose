<?php
/**
 * Quiz menu (منوی کوییز).
 *
 * Editable header and drawer links to the finder pages, under Settings. resolve()
 * is the one place that decides whether a row shows, so the settings page status
 * column and the menu itself cannot disagree.
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
	 * Whether one stored row is shown, and why not if it is not.
	 *
	 * @return array{show: bool, url: string, note: string}
	 */
	public static function resolve( $row ) {
		if ( empty( $row['enabled'] ) ) {
			return array( 'show' => false, 'url' => '', 'note' => __( 'غیرفعال', 'lylyrose-core' ) );
		}
		if ( '' === trim( (string) $row['label'] ) ) {
			return array( 'show' => false, 'url' => '', 'note' => __( 'برچسب خالی است؛ ردیف نادیده گرفته میشود', 'lylyrose-core' ) );
		}
		$url = trim( (string) $row['url'] );
		if ( preg_match( '#^https?://#', $url ) ) {
			return array( 'show' => true, 'url' => $url, 'note' => __( 'نمایش داده میشود (لینک بیرونی)', 'lylyrose-core' ) );
		}
		$path = trim( $url, '/' );
		if ( '' === $path ) {
			return array( 'show' => false, 'url' => '', 'note' => __( 'آدرسی وارد نشده است', 'lylyrose-core' ) );
		}
		if ( ! get_page_by_path( $path ) ) {
			return array( 'show' => false, 'url' => '', 'note' => __( 'صفحهای با این آدرس وجود ندارد', 'lylyrose-core' ) );
		}
		return array( 'show' => true, 'url' => home_url( '/' . $path . '/' ), 'note' => __( 'نمایش داده میشود', 'lylyrose-core' ) );
	}

	/**
	 * Rows that will render, in display order.
	 *
	 * @return array<int, array{label: string, url: string, order: int}>
	 */
	public static function items() {
		$out = array();
		foreach ( get_option( self::OPT, self::defaults() ) as $row ) {
			$state = self::resolve( $row );
			if ( $state['show'] ) {
				$out[] = array( 'label' => $row['label'], 'url' => $state['url'], 'order' => (int) $row['order'] );
			}
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
				'order'   => max( 0, (int) ( $row['order'] ?? 0 ) ),
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
			<ul style="list-style:disc;padding-right:20px">
				<li><?php esc_html_e( 'یک ردیف فقط وقتی نمایش داده میشود که فعال باشد، برچسب داشته باشد، و آدرسش به یک صفحه موجود یا یک لینک کامل https اشاره کند.', 'lylyrose-core' ); ?></li>
				<li><?php esc_html_e( 'آدرس محلی را با / شروع و تمام کنید، مثل /skin-needs/. آدرس کامل هم پذیرفته میشود، مثل https://example.com/page.', 'lylyrose-core' ); ?></li>
				<li><?php esc_html_e( 'ترتیب: عدد کوچکتر بالاتر میآید. مقدار منفی مجاز نیست.', 'lylyrose-core' ); ?></li>
				<li><?php esc_html_e( 'حداکثر ۱۰ ردیف. برای حذف یک ردیف، برچسبش را خالی بگذارید.', 'lylyrose-core' ); ?></li>
				<li><?php esc_html_e( 'این ردیفها هم در نوار دستهبندی بالای سایت و هم در منوی موبایل نمایش داده میشوند.', 'lylyrose-core' ); ?></li>
				<li><?php esc_html_e( 'ستون وضعیت بر اساس مقدار ذخیرهشده است؛ بعد از ذخیره، دوباره بررسی میشود.', 'lylyrose-core' ); ?></li>
			</ul>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPT ); ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'برچسب', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'آدرس', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'ترتیب', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'فعال', 'lylyrose-core' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'lylyrose-core' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $i => $row ) : ?>
						<?php
						$state = self::resolve( $row );
						$empty = '' === trim( (string) $row['label'] ) && '' === trim( (string) $row['url'] );
						?>
						<tr>
							<td><input type="text" class="regular-text" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>"></td>
							<td><input type="text" class="regular-text" dir="ltr" placeholder="/skin-needs/" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>"></td>
							<td><input type="number" min="0" class="small-text" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][order]" value="<?php echo (int) $row['order']; ?>"></td>
							<td><input type="checkbox" name="<?php echo $name; ?>[items][<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>></td>
							<td>
								<?php if ( $empty ) : ?>
									&mdash;
								<?php else : ?>
									<strong style="color:<?php echo $state['show'] ? '#1e7e34' : '#b32d2e'; ?>"><?php echo esc_html( $state['note'] ); ?></strong>
									<?php if ( $state['show'] ) : ?>
										<br><code dir="ltr"><?php echo esc_html( $state['url'] ); ?></code>
									<?php endif; ?>
								<?php endif; ?>
							</td>
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
