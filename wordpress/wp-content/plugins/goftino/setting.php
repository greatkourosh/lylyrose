<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
    .goftino {
        background-color: #ebebeb;
        padding: 20px; color: #444444;
    }
    .goftino h1{font-size: 23px}
    .goftino_installed{float:left;background: #4caf50;padding:4px 10px;border-radius: 4px;color: white;font-family: tahoma;font-size: 60%;font-weight: normal}
    #goftino_id{width:200px}
</style>
<div class="wrap">
    <h1>
        <a href="https://www.goftino.com" target="_blank">
            <img src="<?php echo GOFTINO_IMG_URL ?>logo.png" />
        </a>
    </h1>
		<?php if($error = get_transient('error_goftino')){ ?>
            <div class="error">
                <p><?php echo $error ?></p>
            </div>
		<?php set_transient('error_goftino', ''); } ?>

        <div class="goftino">
            <h1>تنظیمات
                <?php  if ( $widget_id ) {  ?><div class="goftino_installed">نصب شده</div><?php } ?>
            </h1>
            <hr>
            <p>

                <?php  if ( !$widget_id ) { ?>                برای نصب ابزارک گفتینو در سایتتان فقط یک قدم باقیست. اکنون
                <?php } ?>
                   در پنل مدیریت سامانه، به صفحه
                <a href="https://my.goftino.com/app/?go=widget" target="_blank">نصب ابزارک</a>
                مراجعه و شناسه 6 حرفی گفتینو خود را در کادر زیر وارد کنید.
            </p>
            <br>
            <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post" id="form-token">
                <input type="hidden" value="wp_save_goftino" name="action">
                <div>
                    <label for="goftino_id">شناسه گفتینو : </label>
                    <input type="text" id="goftino_id" name="widget_id" maxlength="6" value="<?php if ($widget_id) {echo $widget_id;} ?>" />
                    <br><br><br>
                    <input id="goftino_sd" type="checkbox" name="send_userdata" <?php if ($send_userdata == 1) echo 'checked="checked"'; ?> value="1" />
                    <label for="goftino_sd">ارسال داده های کاربران عضو شده به سامانه شما (شامل : نام ، ایمیل ، user_id)</label>
                    <br><br><br><br>
                    <b>نحوه نمایش ابزارک</b><br><br>
                    <input id="goftino_wtype1" type="radio" name="widget_type" <?php if ($widget_type == 'default' || !$widget_type) echo 'checked="checked"'; ?> value="default" />
                    <label for="goftino_wtype1">حالت پیش فرض : ابزارک بعد از اتمام بارگزاری کل صفحه شما نمایش داده شود.</label>
                    <br><br>
                    <input id="goftino_wtype2" type="radio" name="widget_type" <?php if ($widget_type == 'fast') echo 'checked="checked"'; ?> value="fast" />
                    <label for="goftino_wtype2">حالت نمایش سریع : ابزارک در اولین فرصت نمایش داده شود و منتظر اتمام بارگزاری صفحه نماند.</label>
                    <br><br>
                    <input id="goftino_wtype3" type="radio" name="widget_type" <?php if ($widget_type == 'pagespeed') echo 'checked="checked"'; ?> value="pagespeed" />
                    <label for="goftino_wtype3"> حالت بهینه سرعت : ابزارک در ابتدا نمایش داده نشده و زمانی که کاربر روی صفحه سایت شما عکس العملی مثل حرکت موس یا جابجایی روی صفحه نمایش انجام دهد، نمایش داده خواهد شد.<br>
                        (اگر روی پارامترهای Page Speed Optimize حساست بالایی دارید این حالت پیشنهاد می شود)</label>

                    <br><br><br>
                    <?php wp_nonce_field( 'goftino_nonce'.get_current_user_id() ); ?>
                    <input type="submit" name="submit" class="button button-primary" value="ثبت تنظیمات">
                </div>

                <br><br>
                <?php  if ( !$widget_id ) { ?>
                <hr>
                <p>
                    اگر هنوز در گفتینو ثبت نام نکردید، می توانید
                    <a href="https://www.goftino.com/register" target="_blank">عضو شوید</a>
                    و با کاربرانتان مکالمه و فروش  خدمات / محصولات خود را چند برابر کنید
                </p>
                <?php } ?>
            </form>

        </div>
	<?php  if ( $widget_id ) { ?>
        <br><div class="goftino">
 برای شروع پاسخگویی به کاربران، شخصی سازی ابزارک، مدیریت اپراتورها و استفاده از سایر امکانات گفتینو، وارد <a href="https://my.goftino.com" target="_blank">پنل مدیریت سامانه</a> شوید.
            <br><br>
        </div>

	<?php } ?>
    <p style="font-size: 12px;text-align: center">
        گفتینو، سامانه گفتگوی آنلاین |
        <a href="https://www.goftino.com/" target="_blank">Goftino.com</a>
    <p>
</div>
