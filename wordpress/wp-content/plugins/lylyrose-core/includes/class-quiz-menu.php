<?php
/**
 * Quiz menu (منوی کوییز).
 *
 * Editable header and drawer links to the finder pages, under Settings. Two
 * options: the rows (asc_quiz_menu) and the rules that decide which rows render
 * (asc_quiz_menu_rules). resolve() is the only place a row's visibility is
 * decided, so the status column and the rendered menu cannot disagree.
 */

defined( 'ABSPATH' ) || exit;

class ASC_Quiz_Menu {

	const OPT   = 'asc_quiz_menu';
	const RULES = 'asc_quiz_menu_rules';
	const GROUP = 'asc_quiz_menu';
	const MAX   = 10;
	const PAGE  = 'asc-quiz-menu';

	public static function defaults() {
		return array(
			array( 'label' => 'عطرت رو پیدا کن', 'url' => '/perfume-finder/', 'enabled' => true, 'order' => 1 ),
			array( 'label' => 'پوستت چه نیازی داره', 'url' => '/skin-needs/', 'enabled' => true, 'order' => 2 ),
			array( 'label' => 'موهات چه نیازی داره', 'url' => '/hair-needs/', 'enabled' => true, 'order' => 3 ),
		);
	}

	public static function rule_defaults() {
		return array(
			'require_page'   => true,
			'allow_external' => true,
			'show_header'    => true,
			'show_drawer'    => true,
			'max_items'      => self::MAX,
		);
	}

	public static function rules() {
		return wp_parse_args( get_option( self::RULES, array() ), self::rule_defaults() );
	}

	public static function bool_rules() {
		return array(
			'require_page'   => __( 'صفحه موجود الزامی باشد', 'lylyrose-core' ),
			'allow_external' => __( 'لینک بیرونی (https) مجاز باشد', 'lylyrose-core' ),
			'show_header'    => __( 'نمایش در نوار دستهبندی بالای سایت', 'lylyrose-core' ),
			'show_drawer'    => __( 'نمایش در منوی موبایل', 'lylyrose-core' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Whether one stored row is shown under the given rules, and why not if it is not.
	 *
	 * @return array{show: bool, url: string, note: string}
	 */
	public static function resolve( $row, $rules = null ) {
		$rules = $rules ?? self::rules();
		$hide  = function ( $note ) {
			return array( 'show' => false, 'url' => '', 'note' => $note );
		};

		if ( empty( $row['enabled'] ) ) {
			return $hide( __( 'غیرفعال', 'lylyrose-core' ) );
		}
		if ( '' === trim( (string) $row['label'] ) ) {
			return $hide( __( 'برچسب خالی است', 'lylyrose-core' ) );
		}
		$url = trim( (string) $row['url'] );
		if ( preg_match( '#^https?://#', $url ) ) {
			if ( empty( $rules['allow_external'] ) ) {
				return $hide( __( 'لینک بیرونی مجاز نیست (قانون «لینک بیرونی»)', 'lylyrose-core' ) );
			}
			return array( 'show' => true, 'url' => $url, 'note' => __( 'نمایش داده میشود (لینک بیرونی)', 'lylyrose-core' ) );
		}
		$path = trim( $url, '/' );
		if ( '' === $path ) {
			return $hide( __( 'آدرسی وارد نشده است', 'lylyrose-core' ) );
		}
		if ( ! empty( $rules['require_page'] ) && ! get_page_by_path( $path ) ) {
			return $hide( __( 'صفحهای با این آدرس وجود ندارد (قانون «صفحه موجود»)', 'lylyrose-core' ) );
		}
		return array( 'show' => true, 'url' => home_url( '/' . $path . '/' ), 'note' => __( 'نمایش داده میشود', 'lylyrose-core' ) );
	}

	/**
	 * Rows that render in one place ('header' or 'drawer'), in display order.
	 *
	 * @return array<int, array{label: string, url: string, order: int}>
	 */
	public static function items( $place = 'header' ) {
		$rules = self::rules();
		if ( empty( $rules[ 'show_' . $place ] ) ) {
			return array();
		}
		$out = array();
		foreach ( get_option( self::OPT, self::defaults() ) as $row ) {
			$state = self::resolve( $row, $rules );
			if ( $state['show'] ) {
				$out[] = array( 'label' => $row['label'], 'url' => $state['url'], 'order' => (int) $row['order'] );
			}
		}
		usort( $out, function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		} );
		return array_slice( $out, 0, (int) $rules['max_items'] );
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

	public static function sanitize_rules( $in ) {
		$in = (array) $in;
		return array(
			'require_page'   => ! empty( $in['require_page'] ),
			'allow_external' => ! empty( $in['allow_external'] ),
			'show_header'    => ! empty( $in['show_header'] ),
			'show_drawer'    => ! empty( $in['show_drawer'] ),
			'max_items'      => min( self::MAX, max( 1, (int) ( $in['max_items'] ?? self::MAX ) ) ),
		);
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
			self::GROUP,
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
		register_setting(
			self::GROUP,
			self::RULES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_rules' ),
				'default'           => self::rule_defaults(),
			)
		);
	}

	public static function render_admin() {
		$rules = self::rules();
		$rows  = array_slice( get_option( self::OPT, self::defaults() ), 0, self::MAX );
		while ( count( $rows ) < self::MAX ) {
			$rows[] = array( 'label' => '', 'url' => '', 'enabled' => false, 'order' => count( $rows ) + 1 );
		}
		$opt  = esc_attr( self::OPT );
		$rule = esc_attr( self::RULES );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'منوی کوییز', 'lylyrose-core' ); ?></h1>
			<p>
				<?php
				printf(
					esc_html__( 'الان در نوار بالا %1$d ردیف و در منوی موبایل %2$d ردیف نمایش داده میشود.', 'lylyrose-core' ),
					count( self::items( 'header' ) ),
					count( self::items( 'drawer' ) )
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'قوانین', 'lylyrose-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( self::bool_rules() as $key => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><label><input type="checkbox" name="<?php echo $rule; ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $rules[ $key ] ) ); ?>> <?php esc_html_e( 'روشن', 'lylyrose-core' ); ?></label></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'حداکثر تعداد ردیف نمایش داده شده', 'lylyrose-core' ); ?></th>
						<td>
							<input type="number" min="1" max="<?php echo (int) self::MAX; ?>" class="small-text" name="<?php echo $rule; ?>[max_items]" value="<?php echo (int) $rules['max_items']; ?>">
							<span class="description"><?php printf( esc_html__( 'بین ۱ تا %d', 'lylyrose-core' ), (int) self::MAX ); ?></span>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'ردیفها', 'lylyrose-core' ); ?></h2>
				<p class="description"><?php esc_html_e( 'آدرس محلی را با / شروع و تمام کنید، مثل /skin-needs/. ترتیب: عدد کوچکتر بالاتر میآید. برای حذف یک ردیف، برچسبش را خالی بگذارید.', 'lylyrose-core' ); ?></p>
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
						$state = self::resolve( $row, $rules );
						$empty = '' === trim( (string) $row['label'] ) && '' === trim( (string) $row['url'] );
						?>
						<tr>
							<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[items][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>"></td>
							<td><input type="text" class="regular-text" dir="ltr" placeholder="/skin-needs/" name="<?php echo $opt; ?>[items][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>"></td>
							<td><input type="number" min="0" class="small-text" name="<?php echo $opt; ?>[items][<?php echo (int) $i; ?>][order]" value="<?php echo (int) $row['order']; ?>"></td>
							<td><input type="checkbox" name="<?php echo $opt; ?>[items][<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>></td>
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
