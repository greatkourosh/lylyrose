<?php

defined( 'ABSPATH' ) || exit;

?>
<fieldset id="gateland_form">
	<h2>گیت‌لند</h2>

	<p class="description">
		<label for="gateland_enable">فعالسازی گیت‌لند<br/>
			<input type="checkbox" id="gateland_enable" name="gateland[enable]" value="1"
				<?php checked( 1, $options['enable'] ); ?>
			/>
		</label>
		<span style="color: black;">
			با فعالسازی گیت‌لند، کاربر پس از ثبت فرم به درگاه پرداخت منتقل می‌شود.
		</span>
	</p>

	<p class="description">
		<label for="gateland_price_tag">انتخاب مبلغ<br/>
			<select id="gateland_price_tag" name="gateland[price_tag]">
				<option value="___">مبلغ ثابت</option>

				<?php

				foreach ( $price_tags as $name => $value ) {
					printf( '<option value="%s" %s>%s</option>',
						esc_attr( $name ),
						selected( $name, $options['price_tag'] ),
						esc_html( $value )
					);
				}

				?>

			</select>
		</label>
	</p>

	<p class="description"
	   style="display: <?php echo $options['price_tag'] == '___' ? 'block' : 'none'; ?>;">
		<label for="gateland_price">مبلغ (تومان)<br/>
			<input type="number" id="gateland_price" name="gateland[price]"
				   value="<?php echo intval( $options['price'] ); ?>">
		</label>
	</p>

	<p class="description">
		<label for="gateland_phone_tag">تلفن همراه<br/>
			<select id="gateland_phone_tag" name="gateland[phone_tag]">
				<option value="___">هیچ‌کدام</option>

				<?php

				foreach ( $phone_tags as $name => $value ) {
					printf( '<option value="%s" %s>%s</option>',
						esc_attr( $name ),
						selected( $name, $options['phone_tag'] ),
						esc_html( $value )
					);
				}

				?>

			</select>
		</label>
	</p>

	<p class="description">
		<label for="gateland_email_tag">ایمیل<br/>
			<select id="gateland_email_tag" name="gateland[email_tag]">
				<option value="___">هیچ‌کدام</option>

				<?php

				foreach ( $email_tags as $name => $value ) {
					printf( '<option value="%s" %s>%s</option>',
						esc_attr( $name ),
						selected( $name, $options['email_tag'] ),
						esc_html( $value )
					);
				}

				?>

			</select>
		</label>
	</p>

</fieldset>

<script>
    jQuery(document).ready(function ($) {

        $('#gateland_price_tag').on('change', function (e) {
            if (this.value === '___') {
                $('#gateland_price').parents('p').show();
            } else {
                $('#gateland_price').parents('p').hide();
            }
        });

    });
</script>

<style>


    #gateland_form, #gateland_form h2, #contact-form-editor-tabs a {
        font-family: IRANYekanX, Vazirmatn, Sahel, serif !important;
    }

    #gateland_form input[type=number], #gateland_form select {
        min-width: 50%;
    }
</style>