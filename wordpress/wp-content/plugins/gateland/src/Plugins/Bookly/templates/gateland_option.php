<?php defined( 'ABSPATH' ) || exit;

/** @var Bookly\Lib\CartInfo $cart_info */
/** @var int $form_id */

/** @var bool $show_price */

use Bookly\Lib\Utils\Price;

$selected_gateway = get_option( 'bookly_gateland_selected_gateway' );
$total_price      = Price::format( $cart_info->getPayNow() );
$logo_url         = GATELAND_URL . '/assets/images/gateland.png';
?>

<div class="bookly-box bookly-list">
	<label>
		<input type="radio" class="bookly-js-payment" name="payment-method-<?php echo $form_id ?>" value="gateland"/>
		<input type="hidden" class="" name="gateland-gateway" value="<?php echo $selected_gateway; ?>"/>
		<span>
			<?php echo esc_html( get_option( 'bookly_gateland_option_name', 'پرداخت با گیت‌لند' ) ) ?>
			<?php if ( $show_price ) : ?>
				<span class="bookly-js-pay">(<?php echo $total_price ?>)</span>
			<?php endif ?>
		</span>
		<img src="<?php echo $logo_url ?>" alt="گیت لند"/>
	</label>
</div>