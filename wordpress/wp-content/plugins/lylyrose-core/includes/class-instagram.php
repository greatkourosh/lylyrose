<?php
/**
 * Instagram / social proof strip (P2 #14).
 *
 * Shop-the-grid strip on the homepage, driven by the `asc_instagram` option:
 * {handle, items: [{image_url, post_url}...]} — the owner fills it in the
 * admin (Settings → اینستاگرام لیلی رز) without touching code. No Instagram
 * API: Iran traffic can't reach instagram.com reliably, and the grid works
 * with any image host (the owner's CDN / IG proxy images).
 */
class ASC_Instagram {

	const OPT = 'asc_instagram';
	const MAX_ITEMS = 6;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Normalized option getter: handle + items array (image/post URLs).
	 */
	public static function get_feed() {
		$o = get_option( self::OPT, array() );
		if ( ! is_array( $o ) ) {
			return array( 'handle' => '', 'items' => array() );
		}
		$items = array();
		foreach ( (array) ( $o['items'] ?? array() ) as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$img = esc_url_raw( $it['image_url'] ?? '' );
			if ( ! $img ) {
				continue;
			}
			$items[] = array(
				'image_url' => $img,
				'post_url'  => esc_url_raw( $it['post_url'] ?? '' ),
			);
		}
		return array(
			'handle' => sanitize_text_field( $o['handle'] ?? '' ),
			'items'  => array_slice( $items, 0, self::MAX_ITEMS ),
		);
	}

	/**
	 * Render the strip (called from front-page.php). Silent when no items.
	 */
	public static function render() {
		$feed = self::get_feed();
		if ( empty( $feed['items'] ) ) {
			return;
		}
		?>
		<div class="dk-container">
			<section class="dk-insta" aria-label="<?php echo esc_attr__( 'اینستاگرام لیلی رز', 'lylyrose-core' ); ?>">
				<div class="dk-section-head">
					<h2><?php esc_html_e( 'اینستاگرام ما', 'lylyrose-core' ); ?></h2>
					<?php if ( $feed['handle'] ) : ?>
						<span class="dk-insta-handle" dir="ltr"><?php echo esc_html( $feed['handle'] ); ?></span>
						<a class="dk-section-more" href="https://instagram.com/<?php echo esc_attr( ltrim( $feed['handle'], '@' ) ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'مشاهده پیج ‹', 'lylyrose-core' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="dk-insta-grid">
					<?php foreach ( $feed['items'] as $it ) : ?>
						<a class="dk-insta-cell" href="<?php echo esc_url( $it['post_url'] ? $it['post_url'] : '#' ); ?>" target="_blank" rel="noopener nofollow">
							<img src="<?php echo esc_url( $it['image_url'] ); ?>" alt="<?php echo esc_attr__( 'پست اینستاگرام لیلی رز', 'lylyrose-core' ); ?>" loading="lazy" decoding="async">
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/* ---------------- Admin ---------------- */

	public static function admin_menu() {
		add_options_page(
			__( 'اینستاگرام لیلی رز', 'lylyrose-core' ),
			__( 'اینستاگرام لیلی رز', 'lylyrose-core' ),
			'manage_options',
			'asc-instagram',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function admin_init() {
		register_setting( 'asc_instagram', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	/**
	 * Settings API sanitizer: handle + max 6 {image_url, post_url} rows.
	 */
	public static function sanitize( $in ) {
		$out = array( 'handle' => sanitize_text_field( $in['handle'] ?? '' ), 'items' => array() );
		foreach ( (array) ( $in['items'] ?? array() ) as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$img = esc_url_raw( $it['image_url'] ?? '' );
			if ( ! $img ) {
				continue;
			}
			$out['items'][] = array(
				'image_url' => $img,
				'post_url'  => esc_url_raw( $it['post_url'] ?? '' ),
			);
			if ( count( $out['items'] ) >= self::MAX_ITEMS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Settings page: handle field + 6 image/post URL rows.
	 */
	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$feed = self::get_feed();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'اینستاگرام لیلی رز', 'lylyrose-core' ); ?></h1>
			<p><?php esc_html_e( 'تصاویر پست‌های اینستاگرام را (مثلاً از یک CDN یا ذخیره‌ساز تصویر) اینجا وارد کنید تا نوار «اینستاگرام ما» در صفحه اصلی نمایش داده شود. خالی بودن = مخفی.', 'lylyrose-core' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'asc_instagram' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="asc_insta_handle"><?php esc_html_e( 'آیدی پیج', 'lylyrose-core' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPT ); ?>[handle]" id="asc_insta_handle" type="text" dir="ltr" value="<?php echo esc_attr( $feed['handle'] ); ?>" class="regular-text" placeholder="@lylyrose.ir">
							<p class="description"><?php esc_html_e( 'اختیاری — لینک «مشاهده پیج» را می‌سازد.', 'lylyrose-core' ); ?></p>
						</td>
					</tr>
				</table>
				<h2><?php printf( esc_html__( 'پست‌ها (حداکثر %d)', 'lylyrose-core' ), (int) self::MAX_ITEMS ); ?></h2>
				<table class="widefat striped" style="max-width:720px">
					<thead><tr><th><?php esc_html_e( 'تصویر (URL)', 'lylyrose-core' ); ?></th><th><?php esc_html_e( 'لینک پست (اختیاری)', 'lylyrose-core' ); ?></th></tr></thead>
					<tbody>
					<?php for ( $i = 0; $i < self::MAX_ITEMS; $i++ ) :
						$img = $feed['items'][ $i ]['image_url'] ?? '';
						$url = $feed['items'][ $i ]['post_url'] ?? '';
						?>
						<tr>
							<td><input name="<?php echo esc_attr( self::OPT ); ?>[items][<?php echo $i; ?>][image_url]" type="url" dir="ltr" class="large-text" value="<?php echo esc_attr( $img ); ?>" placeholder="https://…/post-1.jpg"></td>
							<td><input name="<?php echo esc_attr( self::OPT ); ?>[items][<?php echo $i; ?>][post_url]" type="url" dir="ltr" class="large-text" value="<?php echo esc_attr( $url ); ?>" placeholder="https://instagram.com/p/…"></td>
						</tr>
					<?php endfor; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'ذخیره', 'lylyrose-core' ) ); ?>
			</form>
		</div>
		<?php
	}
}
