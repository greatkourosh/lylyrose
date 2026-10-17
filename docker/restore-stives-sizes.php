<?php
/* Restore size suffixes on the 6 MC titles, then rebuild slugs canonically. */
require_once '/var/www/html/wp-load.php';

$sizes = [
    'STIVES-MC-COLLAGENELASTIN-RENEWING-90'  => '۹۰ میل',
    'STIVES-MC-ARGANGRAPE-NOURISHING-621'    => '۶۲۱ گرم',
    'STIVES-MC-ALOEVERA-LIGHTENING-621'      => '۶۲۱ گرم',
    'STIVES-MC-COLLAGENELASTIN-RENEWING-170' => '۱۷۰ میل',
    'STIVES-MC-ARGANGRAPE-NOURISHING-170'    => '۱۷۰ گرم',
    'STIVES-MC-ALOEVERA-LIGHTENING-170'      => '۱۷۰ گرم',
];

foreach ($sizes as $sku => $suffix) {
    $id = wc_get_product_id_by_sku($sku);
    if (!$id) { echo "missing $sku\n"; continue; }
    $p = get_post($id);
    $title = $p->post_title;
    if (mb_substr($title, -mb_strlen($suffix)) === $suffix) { echo "ok $sku\n"; continue; }
    $base = preg_replace('/\s+(۹۰\s+میل|۶۲۱\s+گرم|۱۷۰\s+(میل|گرم))\s*$/u', '', $title);
    $new = $base . ' ' . $suffix;
    $t = str_replace(['(', ')'], '', str_replace(' ', '-', $new));
    $slug = strtolower(rawurlencode($t));
    wp_update_post(['ID' => $id, 'post_title' => $new, 'post_name' => $slug]);
    echo "fixed $sku :: $title\n  -> $new\n";
}
echo "done\n";
