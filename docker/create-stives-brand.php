<?php
/**
 * Create St. Ives brand and assign all St. Ives products to it
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/create-stives-brand.php
 */

require_once '/var/www/html/wp-load.php';

if (!function_exists('wc_get_product')) {
    die("WooCommerce not active\n");
}

echo "Creating St. Ives brand taxonomy term...\n\n";

// Create or get the St. Ives brand term
$term = term_exists('st-ives', 'product_brand');

if ($term === null || $term === 0) {
    $term = wp_insert_term(
        'St. Ives',
        'product_brand',
        [
            'slug' => 'st-ives',
            'description' => 'محصولات برند St. Ives'
        ]
    );

    if (is_wp_error($term)) {
        die("Error creating brand: " . $term->get_error_message() . "\n");
    }

    $term_id = $term['term_id'];
    echo "✓ Created St. Ives brand (ID: $term_id)\n\n";
} else {
    $term_id = is_array($term) ? $term['term_id'] : $term;
    echo "✓ St. Ives brand already exists (ID: $term_id)\n\n";
}

// Get all products with STIVES SKU prefix
echo "Assigning St. Ives products to brand...\n\n";

$args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'meta_query' => [
        [
            'key' => '_sku',
            'value' => 'STIVES-',
            'compare' => 'LIKE'
        ]
    ]
];

$products = get_posts($args);
$updated = 0;

foreach ($products as $product) {
    $result = wp_set_object_terms($product->ID, $term_id, 'product_brand');

    if (!is_wp_error($result)) {
        $sku = get_post_meta($product->ID, '_sku', true);
        echo "  ✓ $sku - {$product->post_title}\n";
        $updated++;
    } else {
        echo "  ✗ Error: " . $result->get_error_message() . "\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Assigned $updated products to St. Ives brand\n";
echo "Brand URL: http://lylyrose.local/برند/st-ives/\n";
