<?php
/**
 * Coupon marketing surfaces (P1 #10).
 *
 * - Campaign banner strip under the announcement bar, driven by the
 *   asc_campaign_banner option ({text, url, active}) so the shop owner can
 *   publish a discount code without touching code.
 * - Styles the Digikala-style coupon field in the cart summary (see theme).
 */
class ASC_Coupons {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_option' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'render_banner' ), 20 );
	}

	/**
	 * Default banner option: inactive until the owner sets a campaign.
	 */
	public static function register_option() {
		if ( get_option( 'asc_campaign_banner' ) === false ) {
			add_option( 'asc_campaign_banner', array(
				'active' => 'no',
				'text'   => '',
				'url'    => '',
			) );
		}
	}

	/**
	 * Render the campaign strip; hidden entirely when inactive or empty.
	 */
	public static function render_banner() {
		$b = get_option( 'asc_campaign_banner', array() );
		if ( empty( $b['active'] ) || 'yes' !== $b['active'] || empty( $b['text'] ) ) {
			return;
		}
		?>
		<div class="dk-campaign">
			<div class="dk-container">
				<?php
				if ( ! empty( $b['url'] ) ) {
					echo '<a href="' . esc_url( $b['url'] ) . '">' . esc_html( $b['text'] ) . '</a>';
				} else {
					echo esc_html( $b['text'] );
				}
				?>
			</div>
		</div>
		<?php
	}
}
