<?php
define('ABSPATH', '/var/www/html/');
$_SERVER['HTTP_HOST'] = 'localhost:8080';
require '/var/www/html/wp-load.php';
echo 'coupons_enabled=' . wc_coupons_enabled() . "\n";
$c = get_posts(array('post_type'=>'shop_coupon','post_status'=>'any','numberposts'=>5,'fields'=>'ids'));
echo 'existing_coupons=' . count($c) . "\n";
