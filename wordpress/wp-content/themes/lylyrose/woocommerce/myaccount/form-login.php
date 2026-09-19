<?php
/**
 * Login form — Digikala-style card for the My Account page.
 *
 * Two tabs: quick mobile-OTP login (default) and classic password login.
 * The OTP tab posts to the lylyrose-core AJAX endpoints (asc_otp_request /
 * asc_otp_verify); when registration is enabled an unknown mobile creates an
 * account automatically on verify.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'wc-password-strength-meter' );

$dk_otp_enabled = class_exists( 'ASC_OTP' );
$dk_otp_nonce   = $dk_otp_enabled ? wp_create_nonce( 'asc_otp' ) : '';
?>
<div class="dk-account-page">
    <div class="dk-container">
        <h1 class="dk-cart-title"><?php esc_html_e( 'حساب کاربری', 'lylyrose' ); ?></h1>

        <div class="dk-account-login">
            <div class="dk-account-login-card" <?php echo $dk_otp_enabled ? 'data-otp-nonce="' . esc_attr( $dk_otp_nonce ) . '"' : ''; ?>>
                <div class="dk-account-login-head">
                    <span class="dk-account-login-ic" aria-hidden="true">👤</span>
                    <h2><?php esc_html_e( 'ورود یا ثبت‌نام', 'lylyrose' ); ?></h2>
                    <p><?php esc_html_e( 'برای مشاهده سفارش‌ها و ادامه خرید وارد حساب خود شوید.', 'lylyrose' ); ?></p>
                </div>

                <?php wc_print_notices(); ?>

                <?php if ( $dk_otp_enabled ) : ?>
                <div class="dk-otp-tabs" role="tablist">                    <button type="button" class="dk-otp-tab is-active" data-tab="otp" role="tab"><?php esc_html_e( 'ورود سریع', 'lylyrose' ); ?></button>
                    <button type="button" class="dk-otp-tab" data-tab="password" role="tab"><?php esc_html_e( 'ورود با گذرواژه', 'lylyrose' ); ?></button>
                </div>

                <div class="dk-otp-panel" data-panel="otp">
                    <form class="dk-otp-form" method="post" autocomplete="off">
                        <div class="dk-otp-step dk-otp-step--mobile">
                            <p class="dk-otp-hint"><?php esc_html_e( 'شماره موبایل خود را وارد کنید', 'lylyrose' ); ?></p>
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                                <input type="tel" class="woocommerce-Input input-text dk-otp-mobile" name="dk_otp_mobile" id="dk_otp_mobile" inputmode="numeric" maxlength="13" placeholder="۰۹۱۲۱۲۳۴۵۶۷" autocomplete="tel" dir="ltr" />
                            </p>
                            <p class="form-row">
                                <button type="submit" class="woocommerce-button button woocommerce-form-login__submit dk-otp-send"><?php esc_html_e( 'دریافت کد تایید', 'lylyrose' ); ?></button>
                            </p>
                        </div>
                        <div class="dk-otp-step dk-otp-step--code" hidden>
                            <p class="dk-otp-hint"><?php esc_html_e( 'کد ۶ رقمی پیامک‌شده به', 'lylyrose' ); ?> <span class="dk-otp-masked" dir="ltr"></span> <?php esc_html_e( 'را وارد کنید', 'lylyrose' ); ?> <a href="#" class="dk-otp-back"><?php esc_html_e( 'ویرایش شماره', 'lylyrose' ); ?></a></p>
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                                <input type="text" class="woocommerce-Input input-text dk-otp-code" name="dk_otp_code" id="dk_otp_code" inputmode="numeric" maxlength="6" placeholder="- - - - - -" autocomplete="one-time-code" dir="ltr" />
                            </p>
                            <p class="form-row">
                                <button type="submit" class="woocommerce-button button woocommerce-form-login__submit dk-otp-verify"><?php esc_html_e( 'ورود', 'lylyrose' ); ?></button>
                                <button type="button" class="dk-otp-resend" hidden><?php esc_html_e( 'ارسال مجدد کد', 'lylyrose' ); ?></button>
                            </p>
                        </div>
                        <p class="dk-otp-msg" role="alert" hidden></p>
                    </form>
                </div>
                <?php endif; ?>

                <div class="dk-otp-panel" data-panel="password" <?php echo $dk_otp_enabled ? 'hidden' : ''; ?>>

                <form class="woocommerce-form woocommerce-form-login login" method="post">

                    <?php do_action( 'woocommerce_login_form_start' ); ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="username"><?php esc_html_e( 'نام کاربری یا آدرس ایمیل', 'lylyrose' ); ?>&nbsp;<span class="required">*</span></label>
                        <input type="text" class="woocommerce-Input input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" /><?php // @codingStandardsIgnoreLine ?>
                    </p>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="password"><?php esc_html_e( 'گذرواژه', 'lylyrose' ); ?>&nbsp;<span class="required">*</span></label>
                        <input class="woocommerce-Input input-text" type="password" name="password" id="password" autocomplete="current-password" />
                    </p>

                    <?php do_action( 'woocommerce_login_form' ); ?>

                    <p class="form-row">
                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                            <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                            <span><?php esc_html_e( 'مرا به خاطر بسپار', 'lylyrose' ); ?></span>
                        </label>
                        <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                        <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'ورود', 'lylyrose' ); ?>"><?php esc_html_e( 'ورود', 'lylyrose' ); ?></button>
                    </p>
                    <p class="woocommerce-LostPassword lost_password">
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'گذرواژه خود را فراموش کرده اید؟', 'lylyrose' ); ?></a>
                    </p>

                    <?php do_action( 'woocommerce_login_form_end' ); ?>

                </form>

                </div><!-- /data-panel=password -->

                <?php if ( get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) : ?>
                    <div class="dk-account-register-hint">
                        <span><?php esc_html_e( 'حساب کاربری ندارید؟', 'lylyrose' ); ?></span>
                        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>"><?php esc_html_e( 'ثبت‌نام', 'lylyrose' ); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
