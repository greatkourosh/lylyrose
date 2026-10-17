<?php
/**
 * Rebuild canonical slugs for all St. Ives products.
 * Original convention (verified against pre-change URLs):
 * post_name = strtolower(rawurlencode(title with ' '->'-', '(' and ')' removed))
 * The per-product 'sku-{ID}' path segment comes from the permalink structure, NOT post_name.
 */

require_once '/var/www/html/wp-load.php';

$args = [
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'meta_query'     => [
        ['key' => '_sku', 'value' => 'STIVES-', 'compare' => 'LIKE'],
    ],
];

$fixed = 0;

foreach (get_posts($args) as $p) {
    $t = str_replace(['(', ')'], '', $p->post_title);
    $t = str_replace(' ', '-', $t);
    $slug = strtolower(rawurlencode($t));

    if ($p->post_name !== $slug) {
        wp_update_post(['ID' => $p->ID, 'post_name' => $slug]);
        $fixed++;
    }

    printf("%s | %s\n", get_post_meta($p->ID, '_sku', true), get_permalink($p->ID));
}

echo "\nFixed $fixed slugs.\n";
