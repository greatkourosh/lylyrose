<?php
define('ABSPATH', '/var/www/html/');
$_SERVER['HTTP_HOST'] = 'localhost:8080';
require '/var/www/html/wp-load.php';
$existing = get_posts(array('post_type'=>'shop_coupon','post_status'=>'any','numberposts'=>1,'s'=>'WELCOME10','fields'=>'ids'));
if ($existing) { echo "EXISTS: " . $existing[0] . "\n"; exit; }
$c = new WC_Coupon();
$c->set_code('WELCOME10');
$c->set_discount_type('percent');
$c->set_amount(10);
$c->set_individual_use(true);
$c->set_usage_limit_per_user(1);
$c->save();
echo "SEEDED: " . $c->get_id() . "\n";
