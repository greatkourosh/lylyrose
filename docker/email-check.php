<?php
define('ABSPATH', '/var/www/html/');
$_SERVER['HTTP_HOST'] = 'localhost:8080';
require '/var/www/html/wp-load.php';
$emails = WC()->mailer()->get_emails();
$e = $emails['WC_Email_Customer_Completed_Order'];
// Render against a throwaway real order so the email templates (which need
// dates, numbers, items) work even in a cleaned-up test DB; delete right after.
$order_ids = wc_get_orders( array('limit'=>1,'return'=>'ids') );
if ( ! empty( $order_ids ) ) {
    $order = wc_get_order( $order_ids[0] );
    $temp_order = false;
} else {
    $order = wc_create_order();
    $order->set_billing_email('probe@example.com');
    $order->save();
    $temp_order = true;
}
$e->object = $order;
$e->placeholders['{order_number}'] = $order->get_order_number();
echo "SUBJECT: " . $e->get_subject() . "\n";
echo "HEADING: " . $e->get_heading() . "\n";
$html = $e->style_inline( $e->get_content_html() );
echo "HAS_DIRECTION_CSS: " . (strpos($html, 'direction: rtl') !== false ? 'YES' : 'NO') . "\n";
echo "HAS_TAHOMA: " . (strpos($html, 'Tahoma') !== false ? 'YES' : 'NO') . "\n";
echo "HAS_RED: " . (strpos($html, '#ef394e') !== false ? 'YES' : 'NO') . "\n";
echo "HAS_RTL_ATTR: " . (strpos($html, 'dir="rtl"') !== false ? 'YES' : 'NO') . "\n";
// sample the style block
if (preg_match('/<style[^>]*>(.*?)<\/style>/s', $html, $m)) {
    echo "STYLE_HEAD: " . substr(preg_replace('/\s+/', ' ', $m[1]), 0, 400) . "\n";
}
if ( ! empty( $temp_order ) ) {
    $order->delete( true );
    echo "TEMP_ORDER_DELETED\n";
}
