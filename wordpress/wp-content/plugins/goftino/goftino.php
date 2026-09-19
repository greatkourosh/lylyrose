<?php

/**
 * Plugin Name: Goftino
 * Author: Ali Rahimi
 * Plugin URI: https://www.goftino.com
 * Description: افزونه گفتینو | با کاربران خود آنلاین صحبت کنید ، پشتیبانی کنید و از فروش بیشتر لذت ببرید
 * Version: 1.8
 *
 * Text Domain:   goftino
 * Domain Path:   /
 */
if (!defined('ABSPATH')) {
    die("Error!");
}

load_plugin_textdomain('goftino');
define("GOFTINO_IMG_URL", plugin_dir_url(__FILE__) . "/img/");
register_activation_hook(__FILE__, 'goftinoInstall');
register_uninstall_hook(__FILE__, 'goftinoDelete');

function menu_goftino() {
    load_plugin_textdomain('goftino');
    add_menu_page(__('گفتینو', 'goftino'), __('گفتینو', 'goftino'), 'manage_options', basename(__FILE__), 'goftinoPreferences', GOFTINO_IMG_URL . "logo-m.png");
}
add_action('admin_menu', 'menu_goftino');
function goftino_validate($a) {
    return $a;
}
add_action('admin_init', 'goftino_register_settings');
function goftino_register_settings() {
    register_setting('goftino_widget_type', 'goftino_widget_type', 'goftino_validate');
    register_setting('goftino_send_userdata', 'goftino_send_userdata', 'goftino_validate');
    register_setting('goftino_widget_id', 'goftino_widget_id', 'goftino_validate');
}
add_action('admin_post_wp_save_goftino', 'wp_save_goftino');
add_action('admin_post_nopriv_wp_save_goftino', 'wp_save_goftino');
add_action('wp_footer', 'goftinoAppend', 100000);

function goftinoInstall() {
    return goftino::getInstance()->install();
}
function goftinoDelete() {
    return goftino::getInstance()->delete();
}
function goftinoAppend() {
    echo goftino::getInstance()->append(goftino::getInstance()->getId(),goftino::getInstance()->getData(),goftino::getInstance()->getWidgetType());
}
function goftinoPreferences() {
    if (isset($_POST["widget_id"]) || isset($_POST["send_userdata"])) {
        goftino::getInstance()->save();
    }
    load_plugin_textdomain('goftino');
    echo goftino::getInstance()->render();
}
function wp_save_goftino() {
    $goftinoError = null;
    $wt='default';
    if (trim($_POST['submit']) !== '' && wp_verify_nonce( $_POST['_wpnonce'], 'goftino_nonce'.get_current_user_id())) {
        $g_id = trim(sanitize_text_field($_POST['widget_id']));
        if ($g_id !== '') {
            if ($_POST['send_userdata'] == 1) {$dt='1';}else{$dt='0';}
            if ($_POST['widget_type'] == 'fast') {$wt='fast';}elseif($_POST['widget_type'] == 'pagespeed'){$wt='pagespeed';}
            if (preg_match("/^[0-9a-zA-Z]{6}$/", $g_id)) {
                if (get_option('goftino_widget_id') !== false) {
                    update_option('goftino_widget_id', $g_id);
                    update_option('goftino_send_userdata', $dt);
                    update_option('goftino_widget_type', $wt);
                } else {
                    add_option('goftino_widget_id', $g_id, null, 'no');
                    add_option('goftino_send_userdata', $dt, null, 'no');
                    add_option('goftino_widget_type', $wt, null, 'no');
                }
                $goftino = goftino::getInstance();
                $goftino->install();
                // Clear WP Rocket Cache if needed
                if (function_exists("rocket_clean_domain")) {
                    rocket_clean_domain();
                }
                // Clear WP Super Cache if needed
                if (function_exists("wp_cache_clean_cache")) {
                    global $file_prefix;
                    wp_cache_clean_cache($file_prefix, true);
                }

            } else {
                $goftinoError = "شناسه نامعتبر است.";
            }
        } else {
            $goftinoError = "شناسه نمی تواند خالی باشد.";
        }
        set_transient('error_goftino', $goftinoError);
    }
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
}

class goftino {
    protected static $instance;

    private function __construct()
    {
        $this->widget_type = get_option('goftino_widget_type');
        $this->send_userdata = get_option('goftino_send_userdata');
        $this->widget_id = get_option('goftino_widget_id');
    }

    private $widget_id = '';
    private $widget_type = '';
    private $send_userdata = '';

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new goftino();
        }
        return self::$instance;
    }
    public function install()
    {
        if (!$this->widget_id) {
            if (($out = get_option('goftino_widget_id')) !== false) {
                $this->widget_id = $out;
            }
        }
        $this->save();
    }
    public function delete()
    {
        delete_transient('error_goftino');
        if (get_option('goftino_widget_id') !== false) {
            delete_option('goftino_widget_id');
            delete_option('goftino_send_userdata');
            delete_option('goftino_widget_type');
        }
    }

    public function getId()
    {
        return $this->widget_id;
    }
    public function getData()
    {
        return $this->send_userdata;
    }
    public function getWidgetType()
    {
        return $this->widget_type;
    }
    public function render()
    {
        $widget_id = $this->widget_id;
        $send_userdata = $this->send_userdata;
        $widget_type = $this->widget_type;
        require_once "setting.php";
    }
    public function append($widget_id = false, $send_userdata = 0,$widget_type='default')
    {
        if ($widget_id) {

            echo '<script type="text/javascript" data-goftinoplugin="1">';
            if ($widget_type==='pagespeed') {
                echo '["keydown","touchmove","touchstart","mouseover"].forEach(function(v){window.addEventListener(v,function(){if(!window.isGoftinoAdded){window.isGoftinoAdded=1;var i="'.$widget_id.'",d=document,g=d.createElement("script"),s="https://www.goftino.com/widget/"+i,l=localStorage.getItem("goftino_"+i);g.type="text/javascript",g.async=!0,g.src=l?s+"?o="+l:s;d.getElementsByTagName("head")[0].appendChild(g);}})});';
            }elseif ($widget_type==='fast'){
                echo '!function(){var i="'.$widget_id.'",d=document,g=d.createElement("script"),s="https://www.goftino.com/widget/"+i,l=localStorage.getItem("goftino_"+i);g.type="text/javascript",g.async=!0,g.src=l?s+"?o="+l:s;d.getElementsByTagName("head")[0].appendChild(g);}();';
            }else{
                echo '!function(){var a=window,d=document,u="https://www.goftino.com/",h=d.getElementsByTagName("head")[0];function p(q){var k=d.createElement("link");k.href =q,k.rel="preconnect";h.appendChild(k);}p(u);p(u.replace("www","cdn"));function g(){var g=d.createElement("script"),i="'.$widget_id.'",s=u+"widget/"+i,l=localStorage.getItem("goftino_"+i);g.async=!0,g.src=l?s+"?o="+l:s;h.appendChild(g);}"complete"===d.readyState?g():a.attachEvent?a.attachEvent("onload",g):a.addEventListener("load",g,!1);}();';
            }

            if ($send_userdata > 0 && is_user_logged_in()) {
                $user = wp_get_current_user();
                $full_name  = trim( $user->first_name .' ' . $user->last_name );

                echo "
window.addEventListener('goftino_ready', function () { Goftino.setUser({
    email: '" . $user->user_email . "',
    name: '" . esc_js($full_name ? $full_name : $user->display_name) . "'
});});
";
            }
            echo '</script>';
        }
    }

    public function save() {
        update_option('goftino_widget_id', $this->widget_id);
        update_option('goftino_send_userdata', $this->send_userdata);
        update_option('goftino_widget_type', $this->widget_type);
    }

}

