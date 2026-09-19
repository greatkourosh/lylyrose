<?php
/**
 * Login form — Digikala-style card for the My Account page.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'wc-password-strength-meter' );
?>
<div class="dk-account-page">
    <div class="dk-container">
        <h1 class="dk-cart-title">حساب کاربری</h1>

        <div class="dk-account-login">
            <div class="dk-account-login-card">
                <div class="dk-account-login-head">
                    <span class="dk-account-login-ic" aria-hidden="true">👤</span>
                    <h2>ورود یا ثبت‌نام</h2>
                    <p>برای مشاهده سفارش‌ها و ادامه خرید وارد حساب خود شوید.</p>
                </div>

                <?php wc_print_notices(); ?>

                <form class="woocommerce-form woocommerce-form-login login" method="post">

                    <?php do_action( 'woocommerce_login_form_start' ); ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="username"><?php esc_html_e( 'نام کاربری یا آدرس ایمیل', 'digikala' ); ?>&nbsp;<span class="required">*</span></label>
                        <input type="text" class="woocommerce-Input input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" /><?php // @codingStandardsIgnoreLine ?>
                    </p>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="password"><?php esc_html_e( 'گذرواژه', 'digikala' ); ?>&nbsp;<span class="required">*</span></label>
                        <input class="woocommerce-Input input-text" type="password" name="password" id="password" autocomplete="current-password" />
                    </p>

                    <?php do_action( 'woocommerce_login_form' ); ?>

                    <p class="form-row">
                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                            <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                            <span><?php esc_html_e( 'مرا به خاطر بسپار', 'digikala' ); ?></span>
                        </label>
                        <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                        <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'ورود', 'digikala' ); ?>"><?php esc_html_e( 'ورود', 'digikala' ); ?></button>
                    </p>
                    <p class="woocommerce-LostPassword lost_password">
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'گذرواژه خود را فراموش کرده اید؟', 'digikala' ); ?></a>
                    </p>

                    <?php do_action( 'woocommerce_login_form_end' ); ?>

                </form>

                <?php if ( get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) : ?>
                    <div class="dk-account-register-hint">
                        <span>حساب کاربری ندارید؟</span>
                        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">ثبت‌نام</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
