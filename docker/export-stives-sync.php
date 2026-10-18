<?php
/* Export local St. Ives state for the live sync probe, reading _digikala_* meta
   from the DB (not the source JSON, which predates the enrichment).
   Prints JSON to stdout. */
require_once '/var/www/html/wp-load.php';

$args = [
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'meta_query'     => [['key' => '_sku', 'value' => 'STIVES-', 'compare' => 'LIKE']],
];

$out = [];
foreach (get_posts($args) as $p) {
    $dkid = get_post_meta($p->ID, '_digikala_id', true);
    $dg = null;
    if ($dkid) {
        $dg = [
            'id'    => (int) $dkid,
            'url'   => get_post_meta($p->ID, '_digikala_url', true),
            'title_fa' => get_post_meta($p->ID, '_digikala_title', true),
            'gallery'  => (array) get_post_meta($p->ID, '_digikala_image_urls', true),
        ];
    }
    $out[] = [
        'sku'   => get_post_meta($p->ID, '_sku', true),
        'title' => $p->post_title,
        'slug'  => $p->post_name,
        'digikala' => $dg,
    ];
}

echo json_encode([
    'brand'    => ['name' => 'St. Ives', 'slug' => 'st-ives'],
    'products' => $out,
], JSON_UNESCAPED_UNICODE);