<?php
$SECRET = 'asc-email-opt-9f3k2';
if ( ! isset($_GET['t']) || ! hash_equals($SECRET, (string) $_GET['t']) ) { http_response_code(403); exit('forbidden'); }
require dirname(__DIR__) . '/wp-load.php';
echo 'VERSION: ' . LYLYROSE_CORE_VERSION . "\n";
echo 'CLASS: ' . ( class_exists('ASC_Coupons') ? 'yes' : 'no' ) . "\n";
echo 'HOOKED: ' . ( has_action('wp_body_open', array('ASC_Coupons','render_banner')) ? 'yes' : 'no' ) . "\n";
$b = get_option('asc_campaign_banner', array());
echo 'BANNER_OPT: ' . ( is_array($b) ? 'ok' : 'missing' ) . "\n";
echo 'COUPONS_ENABLED: ' . var_export(wc_coupons_enabled(), true) . "\n";
$ids = get_posts(array('post_type'=>'shop_coupon','post_status'=>'any','numberposts'=>5,'fields'=>'ids'));
echo 'COUPON_COUNT: ' . count($ids) . "\n";
