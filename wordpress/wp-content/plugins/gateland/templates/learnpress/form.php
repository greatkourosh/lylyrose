<?php

defined( 'ABSPATH' ) || exit;

?>

<?php $settings = LP()->settings; ?>

<?php if ( strlen( $this->get_description() ) ) { ?>
	<p><?php echo esc_html( $this->get_description() ); ?></p>
<?php } ?>

<?php if ( $settings->get( 'gateland.mobile', 'yes' ) == 'yes' ) { ?>
	<div id="learn-press-gateland-form" class="<?php echo is_rtl() ? ' learn-press-form-gateland-rtl' : ''; ?>">
		<p class="learn-press-form-row">
			<label><?php echo wp_kses( __( 'تلفن همراه', 'gateland' ), [ 'span' => [] ] ); ?></label>
			<input type="text" name="learn-press-gateland[mobile]" id="learn-press-gateland-payment-mobile" value=""
				   placeholder="مثال: 09136667788"/>
		<div class="learn-press-gateland-form-clear"></div>
		</p>
		<div class="learn-press-gateland-form-clear"></div>
	</div>
<?php } ?>