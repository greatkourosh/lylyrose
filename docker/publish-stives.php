<?php
/**
 * Publish all draft St. Ives products
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/publish-stives.php
 */

require_once '/var/www/html/wp-load.php';

if (!function_exists('wc_get_product')) {
    die("WooCommerce not active\n");
}

echo "Publishing St. Ives products...\n\n";

// Get all draft St. Ives products
$args = [
    'post_type' => 'product',
    'post_status' => 'draft',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => '_sku',
            'value' => 'STIVES',
            'compare' => 'LIKE'
        ]
    ]
];

$query = new WP_Query($args);
$published = 0;

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
        $sku = $product->get_sku();
        $name = $product->get_name();

        // Publish
        wp_update_post([
            'ID' => $product_id,
            'post_status' => 'publish'
        ]);

        echo "✓ Published: $sku - $name\n";
        echo "  URL: " . get_permalink($product_id) . "\n\n";
        $published++;
    }
    wp_reset_postdata();
}

echo str_repeat('=', 60) . "\n";
echo "Published $published St. Ives products\n";
echo "\nView all at: " . home_url('/برند/st-ives/') . "\n";
