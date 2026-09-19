<?php
$SECRET = 'asc-email-opt-9f3k2';
if ( ! isset($_GET['t']) || ! hash_equals($SECRET, (string) $_GET['t']) ) { http_response_code(403); exit('forbidden'); }
require dirname(__DIR__) . '/wp-load.php';

// Seed WELCOME10 if absent
$existing = get_posts(array('post_type'=>'shop_coupon','post_status'=>'any','numberposts'=>1,'s'=>'welcome10','fields'=>'ids'));
if ($existing) {
    echo 'COUPON_EXISTS: ' . $existing[0] . "\n";
} else {
    $c = new WC_Coupon();
    $c->set_code('WELCOME10');
    $c->set_discount_type('percent');
    $c->set_amount(10);
    $c->set_individual_use(true);
    $c->set_usage_limit_per_user(1);
    $c->save();
    echo 'COUPON_SEEDED: ' . $c->get_id() . "\n";
}

// Register + activate campaign banner
$b = get_option('asc_campaign_banner', array());
if (!is_array($b)) { $b = array(); }
$b['active'] = 'yes';
$b['text']   = '🎉 کد تخفیف WELCOME10 — ۱۰٪ تخفیف اولین خرید';
$b['url']    = '';
update_option('asc_campaign_banner', $b);
echo 'BANNER_SET' . "\n";

// flush object cache so coupon code lookups are not stale
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo 'CACHE_FLUSHED' . "\n"; }
