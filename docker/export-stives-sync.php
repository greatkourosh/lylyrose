<?php
/* Export local St. Ives state for the live sync probe.
   Prints JSON to stdout: sku, title, slug, digikala block. */
require_once '/var/www/html/wp-load.php';

$json = json_decode(file_get_contents('/var/www/html/wp-content/stives-products.json'), true);
$by_sku = [];
foreach ($json['products'] as $p) {
    $by_sku[$p['sku']] = $p['digikala'] ?? null;
}

$args = [
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'meta_query'     => [['key' => '_sku', 'value' => 'STIVES-', 'compare' => 'LIKE']],
];

$out = [];
foreach (get_posts($args) as $p) {
    $sku = get_post_meta($p->ID, '_sku', true);
    $out[] = [
        'sku'   => $sku,
        'title' => $p->post_title,
        'slug'  => $p->post_name,
        'digikala' => $by_sku[$sku] ?? null,
    ];
}

echo json_encode([
    'brand'    => ['name' => 'St. Ives', 'slug' => 'st-ives'],
    'products' => $out,
], JSON_UNESCAPED_UNICODE);
