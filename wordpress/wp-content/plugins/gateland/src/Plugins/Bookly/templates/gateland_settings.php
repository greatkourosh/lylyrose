<?php defined( 'ABSPATH' ) || exit;

use Bookly\Backend\Components\Settings\Inputs;
use Bookly\Backend\Components\Settings\Selects;
use Bookly\Backend\Components\Controls\Elements;
use Nabik\Gateland\Plugins\Bookly\Load;

?>

	<div class="card bookly-collapse-with-arrow gateland-container" data-gateway="gateland">
		<div class="card-header d-flex align-items-center">
			<?php Elements::renderReorder() ?>
			<a href="#bookly_pmt_gateland" class="ml-2" role="button" data-toggle="bookly-collapse">گیت لند</a>
			<img class="ml-auto" src="<?php echo esc_url( GATELAND_URL . '/assets/images/gateland.png' ) ?>" alt="افزونه جامع درگاه های پرداخت گیت‌لند"/>
		</div>
		<div id="bookly_pmt_gateland" class="bookly-collapse bookly-show">
			<div class="card-body">

				<?php Selects::renderSingle( 'bookly_gateland_enabled', null, null, [
					[ '0', 'غیرفعال' ],
					[ '1', 'فعال' ]
				] ) ?>

				<br>
				<div class="bookly-gateland-settings">

					<?php
					$show_price = get_option( 'bookly_gateland_show_price', '0' );
					$checkbox   = '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="bookly_gateland_show_price" name="bookly_gateland_show_price" value="1" ' . checked( $show_price, '1', false ) . ' /> <label class="custom-control-label" for="bookly_gateland_show_price">نمایش قیمت</label></div>';

					echo Inputs::buildControl(
						'bookly_gateland_show_price',
						'',
						'نمایش هزینه نهایی کنار درگاه پرداخت.',
						$checkbox
					);
					?>

				</div>

				<br>
				<div class="bookly-gateland-settings">

					<?php
					Selects::renderSingle(
						'bookly_gateland_selected_gateway',
						'درگاه پرداخت',
						'یکی از درگاه های تنظیم شده را انتخاب کنید، درگاه هوشمند به صورت خودکار یکی از درگاه های فعال را انتخاب می‌کند.',
						Load::gateways()
					);
					?>

				</div>

			</div>
		</div>
	</div>


<?php

add_action( 'admin_footer', function () {
	?>
	<script type="text/javascript">

        jQuery(function ($) {

            function toggleGatelandSettings() {
                $('.bookly-gateland-settings').toggle($('#bookly_gateland_enabled').val() === '1');
            }

            $('#bookly_gateland_enabled').on('change', toggleGatelandSettings);
            toggleGatelandSettings();

        });

	</script>
	<?php
} );